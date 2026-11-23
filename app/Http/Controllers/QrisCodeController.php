<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\QrisCode;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Throwable;

class QrisCodeController extends Controller
{
    use ApiResponse;

    private function expireOldCodes(): void
    {
        QrisCode::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    private function canAccess(Request $request, QrisCode $code): bool
    {
        $user = $request->user('api');

        return $user->role === 'admin'
            || (int) $code->user_id === (int) $user->id;
    }

    // GET /api/qris-codes
    public function index(Request $request): JsonResponse
    {
        try {
            $this->expireOldCodes();

            $user = $request->user('api');

            $query = QrisCode::with([
                'user:id,name,email',
                'merchant:id,user_id,merchant_name,nmid',
            ]);

            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }

            return $this->success(
                $query->latest()->get(),
                'Data QRIS berhasil ditampilkan'
            );
        } catch (Throwable $e) {
            report($e);
            return $this->error('Gagal mengambil data QRIS', 500);
        }
    }

    // POST /api/qris-codes
    // Setiap request membuat payload unik yang baru
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user('api');

            $validator = Validator::make($request->all(), [
                'merchant_id' => [
                    'required',
                    'integer',
                    'exists:merchants,id',
                ],
                'nominal' => [
                    'required',
                    'numeric',
                    'min:1',
                    'max:10000000',
                    'decimal:0,2',
                ],
                'user_id' => 'prohibited',
                'reference_code' => 'prohibited',
                'payload_string' => 'prohibited',
                'status' => 'prohibited',
            ]);

            if ($validator->fails()) {
                return $this->error(
                    'Validasi gagal',
                    422,
                    $validator->errors()
                );
            }

            $data = $validator->validated();

            $merchant = Merchant::find($data['merchant_id']);

            if (
                $user->role !== 'admin' &&
                (int) $merchant->user_id !== (int) $user->id
            ) {
                return $this->error(
                    'Anda tidak memiliki akses ke merchant ini',
                    403
                );
            }

            if ($merchant->status !== 'active') {
                return $this->error(
                    'Merchant tidak aktif',
                    422
                );
            }

            // UUID baru setiap pembuatan QR
            $reference = 'QRIS-' . Str::uuid()->toString();

            $expiresAt = now()->addMinutes(5);

            // Payload simulasi, bukan format QRIS resmi
            $payload = json_encode([
                'type' => 'QRIS_SIMULATION',
                'version' => 1,
                'reference' => $reference,
                'merchant_id' => $merchant->id,
                'merchant_nmid' => $merchant->nmid,
                'merchant_name' => $merchant->merchant_name,
                'amount' => number_format(
                    (float) $data['nominal'], 2, '.', ''
                ),
                'currency' => 'IDR',
                'expires_at' => $expiresAt->toIso8601String(),
            ], JSON_THROW_ON_ERROR);

            $code = QrisCode::create([
                'user_id' => $merchant->user_id,
                'merchant_id' => $merchant->id,
                'reference_code' => $reference,
                'payload_string' => $payload,
                'nominal' => $data['nominal'],
                'status' => 'pending',
                'expires_at' => $expiresAt,
            ]);

            return $this->success(
                $code->load('merchant:id,user_id,merchant_name,nmid'),
                'QRIS dinamis baru berhasil dibuat',
                201
            );
        } catch (Throwable $e) {
            report($e);
            return $this->error('Gagal membuat QRIS', 500);
        }
    }

    // GET /api/qris-codes/{id}
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $this->expireOldCodes();

            $code = QrisCode::with([
                'user:id,name,email',
                'merchant:id,user_id,merchant_name,nmid',
            ])->find($id);

            if (!$code) {
                return $this->error('QRIS tidak ditemukan', 404);
            }

            if (!$this->canAccess($request, $code)) {
                return $this->error('Akses ditolak', 403);
            }

            return $this->success(
                $code,
                'Detail QRIS berhasil ditampilkan'
            );
        } catch (Throwable $e) {
            report($e);
            return $this->error('Gagal mengambil detail QRIS', 500);
        }
    }

    // PUT/PATCH /api/qris-codes/{id}
    // Hanya boleh membatalkan QR pending dengan status expired
    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $this->expireOldCodes();

            $code = QrisCode::find($id);

            if (!$code) {
                return $this->error('QRIS tidak ditemukan', 404);
            }

            if (!$this->canAccess($request, $code)) {
                return $this->error('Akses ditolak', 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => ['required', Rule::in(['expired'])],
                'user_id' => 'prohibited',
                'merchant_id' => 'prohibited',
                'nominal' => 'prohibited',
                'payload_string' => 'prohibited',
                'reference_code' => 'prohibited',
                'used_at' => 'prohibited',
                'expires_at' => 'prohibited',
            ]);

            if ($validator->fails()) {
                return $this->error(
                    'Validasi gagal',
                    422,
                    $validator->errors()
                );
            }

            $updated = QrisCode::whereKey($code->id)
                ->where('status', 'pending')
                ->update(['status' => 'expired']);

            if (!$updated) {
                return $this->error(
                    'QRIS sudah digunakan atau kedaluwarsa',
                    409
                );
            }

            return $this->success(
                $code->fresh(),
                'QRIS berhasil dibatalkan'
            );
        } catch (Throwable $e) {
            report($e);
            return $this->error('Gagal memperbarui QRIS', 500);
        }
    }

    // DELETE /api/qris-codes/{id}
    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $code = QrisCode::find($id);

            if (!$code) {
                return $this->error('QRIS tidak ditemukan', 404);
            }

            if (!$this->canAccess($request, $code)) {
                return $this->error('Akses ditolak', 403);
            }

            // Jangan hapus QR yang sudah digunakan atau expired
            if ($code->status !== 'pending' ||
                $code->expires_at->isPast()) {
                return $this->error(
                    'QRIS ini tidak dapat dihapus',
                    409
                );
            }

            // Hard delete hanya untuk QR simulasi pending
            // Belum boleh ada transaksi yang merujuk ke QR ini
            $code->delete();

            return $this->success(
                null,
                'QRIS berhasil dihapus'
            );
        } catch (Throwable $e) {
            report($e);
            return $this->error(
                'Gagal menghapus QRIS atau terdapat transaksi terkait',
                500
            );
        }
    }
}