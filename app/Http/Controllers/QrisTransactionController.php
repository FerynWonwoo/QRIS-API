<?php

namespace App\Http\Controllers;

use App\Models\QrisCode;
use App\Models\QrisTransaction;
use App\Traits\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class QrisTransactionController extends Controller
{
    use ApiResponse;

    private const COLUMNS = [
        'id', 'user_id', 'qris_code_id', 'reference_number',
        'amount', 'status', 'created_at', 'updated_at',
    ];

    // Aturan transisi status: paid & failed bersifat final
    private const TRANSITIONS = [
        'pending' => ['paid', 'failed'],
        'paid'    => [],
        'failed'  => [],
    ];

    // GET /api/qris-transactions
    public function index(Request $request): JsonResponse
    {
        try {
            $transactions = QrisTransaction::query()
                ->with(['user:id,name,email,role', 'qrisCode:id,merchant_id,nominal,status'])
                ->select(self::COLUMNS)
                ->when($request->query('status'), fn($q, $status) => $q->where('status', $status))
                ->latest('id')
                ->get();

            return $this->success($transactions, 'Daftar transaksi QRIS');
        } catch (QueryException $e) {
            Log::error('Gagal membaca transaksi QRIS: ' . $e->getMessage());
            return $this->error('Terjadi kesalahan pada database', 500);
        }
    }

    // GET /api/qris-transactions/{id}
    public function show(string $id): JsonResponse
    {
        try {
            $transaction = QrisTransaction::with(['user:id,name,email,role', 'qrisCode:id,merchant_id,nominal,status'])
                ->select(self::COLUMNS)
                ->find($id);

            if (!$transaction) {
                return $this->error('Transaksi QRIS tidak ditemukan', 404);
            }

            return $this->success($transaction, 'Detail transaksi QRIS');
        } catch (QueryException $e) {
            Log::error('Gagal membaca detail transaksi QRIS: ' . $e->getMessage());
            return $this->error('Terjadi kesalahan pada database', 500);
        }
    }

    // POST /api/qris-transactions
    // Pembayar = user yang sedang login. Status awal selalu "pending".
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'qris_code_id'     => 'required|integer|exists:qris_codes,id',
                'amount'           => 'sometimes|numeric|min:100|decimal:0,2',
                'user_id'          => 'prohibited',
                'reference_number' => 'prohibited',
                'status'           => 'prohibited',
            ]);

            if ($validator->fails()) {
                return $this->error('Data tidak valid', 422, $validator->errors());
            }

            $data = $validator->validated();
            $code = QrisCode::find($data['qris_code_id']);

            // QRIS sudah dipakai
            if ($code->status === 'used') {
                return $this->error('Kode QRIS sudah digunakan', 422);
            }

            // QRIS kedaluwarsa (status belum diperbarui atau sudah expired)
            if ($code->status === 'expired' || $code->expires_at->isPast()) {
                return $this->error('Kode QRIS sudah kedaluwarsa', 422);
            }

            // Nominal QRIS terkunci: jika amount dikirim, harus sama persis
            if (isset($data['amount']) && abs((float) $data['amount'] - (float) $code->nominal) >= 0.005) {
                return $this->error(
                    'Nominal tidak sesuai dengan nominal kode QRIS',
                    422,
                    ['amount' => ['Nominal harus ' . number_format((float) $code->nominal, 2, '.', '')]]
                );
            }

            $transaction = QrisTransaction::create([
                'user_id'          => $request->user('api')->id,
                'qris_code_id'     => $code->id,
                'reference_number' => 'TRX-' . Str::upper(Str::random(20)),
                'amount'           => $code->nominal,
                'status'           => 'pending',
            ]);

            return $this->success($transaction->fresh(), 'Pembayaran QRIS berhasil dicatat', 201);
        } catch (QueryException $e) {
            Log::error('Gagal menyimpan transaksi QRIS: ' . $e->getMessage());
            return $this->error('Terjadi kesalahan pada database', 500);
        }
    }

    // PUT/PATCH /api/qris-transactions/{id}  (admin)
    // Hanya status yang dapat diubah, mengikuti aturan transisi.
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,paid,failed',
                'amount' => 'prohibited',
            ]);

            if ($validator->fails()) {
                return $this->error('Data tidak valid', 422, $validator->errors());
            }

            $newStatus = $validator->validated()['status'];

            $result = DB::transaction(function () use ($id, $newStatus) {
                $transaction = QrisTransaction::lockForUpdate()->find($id);
                if (!$transaction) {
                    return ['error' => ['Transaksi QRIS tidak ditemukan', 404]];
                }

                if ($newStatus === $transaction->status) {
                    return ['ok' => $transaction];
                }

                if (!in_array($newStatus, self::TRANSITIONS[$transaction->status], true)) {
                    return ['error' => [
                        "Status tidak dapat diubah dari '{$transaction->status}' ke '{$newStatus}'",
                        422,
                    ]];
                }

                if ($newStatus === 'paid') {
                    $code = QrisCode::lockForUpdate()->find($transaction->qris_code_id);

                    if ($code->status === 'used') {
                        return ['error' => ['Kode QRIS sudah dibayar oleh transaksi lain', 409]];
                    }
                    if ($code->status === 'expired' || $code->expires_at->isPast()) {
                        return ['error' => ['Kode QRIS sudah kedaluwarsa', 422]];
                    }

                    // Tandai QRIS sudah dipakai
                    $code->update(['status' => 'used', 'used_at' => now()]);
                }

                $transaction->update(['status' => $newStatus]);

                return ['ok' => $transaction];
            });

            if (isset($result['error'])) {
                return $this->error(...$result['error']);
            }

            return $this->success($result['ok']->fresh(), 'Transaksi QRIS berhasil diperbarui');
        } catch (QueryException $e) {
            Log::error('Gagal mengubah transaksi QRIS: ' . $e->getMessage());
            return $this->error('Terjadi kesalahan pada database', 500);
        }
    }

    // DELETE /api/qris-transactions/{id}  (admin)
    public function destroy(string $id): JsonResponse
    {
        try {
            $transaction = QrisTransaction::find($id);
            if (!$transaction) {
                return $this->error('Transaksi QRIS tidak ditemukan', 404);
            }

            if ($transaction->settlementTransaction()->exists()) {
                return $this->error(
                    'Transaksi sudah masuk settlement dan tidak dapat dihapus',
                    409
                );
            }

            $transaction->delete();
            return $this->success(null, 'Transaksi QRIS berhasil dihapus');
        } catch (QueryException $e) {
            Log::error('Gagal menghapus transaksi QRIS: ' . $e->getMessage());
            return $this->error('Terjadi kesalahan pada database', 500);
        }
    }
}