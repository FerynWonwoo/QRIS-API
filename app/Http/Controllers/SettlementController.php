<?php

namespace App\Http\Controllers;

use App\Models\Settlement;
use App\Models\SettlementTransaction;
use App\Models\QrisTransaction;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SettlementController extends Controller
{
    use ApiResponse;

            public function index(Request $request): JsonResponse
        {
            try {
                $user = $request->user('api');

                $query = Settlement::with([
                    'user:id,name,email',
                    'merchant:id,merchant_name,nmid',
                    'settlementTransactions.qrisTransaction'
                ]);

                if ($user->role !== 'admin') {
                    $query->where('user_id', $user->id);
                }

                $settlements = $query->latest()->get();

                return $this->success(
                    $settlements,
                    'Daftar settlement berhasil ditampilkan'
                );

            } catch (QueryException $e) {
                Log::error('Gagal mengambil settlement: ' . $e->getMessage());

                return $this->error(
                    'Terjadi kesalahan pada database',
                    500
                );
            }
        }

    public function show(Request $request, string $id): JsonResponse
{
    try {
        $user = $request->user('api');

        $query = Settlement::with([
            'user:id,name,email',
            'merchant:id,merchant_name,nmid',
            'settlementTransactions.qrisTransaction'
        ]);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $settlement = $query->find($id);

        if (!$settlement) {
            return $this->error(
                'Settlement tidak ditemukan',
                404
            );
        }

        return $this->success(
            $settlement,
            'Detail settlement berhasil ditampilkan'
        );

    } catch (QueryException $e) {
        Log::error('Gagal mengambil detail settlement: ' . $e->getMessage());

        return $this->error(
            'Terjadi kesalahan pada database',
            500
        );
    }
}

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'merchant_id' => 'required|integer|exists:merchants,id',

            'transaction_ids' => 'required|array|min:1',

            'transaction_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:qris_transactions,id'
            ],

            'bank_name' => 'required|string|max:100',

            'account_number' => 'required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->error(
                'Data settlement tidak valid',
                422,
                $validator->errors()
            );
        }

        try {
            $data = $validator->validated();
            $user = $request->user('api');

            $merchant = \App\Models\Merchant::find($data['merchant_id']);

            if (!$merchant) {
                return $this->error('Merchant tidak ditemukan', 404);
            }

            if (
                $user->role !== 'admin' &&
                (int) $merchant->user_id !== (int) $user->id
            ) {
                return $this->error(
                    'Anda tidak memiliki akses ke merchant ini',
                    403
                );
            }

            $settlement = DB::transaction(function () use ($data, $user) {

                $transactions = QrisTransaction::whereIn(
                    'id',
                    $data['transaction_ids']
                )
                    ->lockForUpdate()
                    ->get();

                if ($user->role !== 'admin') {
                    foreach ($transactions as $transaction) {
                        if ((int) $transaction->user_id !== (int) $user->id) {
                            throw new \RuntimeException(
                                'Anda tidak memiliki akses ke transaksi ini'
                            );
                        }
                    }
                }

                if ($transactions->count() !== count($data['transaction_ids'])) {
                    throw new \RuntimeException(
                        'Beberapa transaksi tidak ditemukan'
                    );
                }

                foreach ($transactions as $transaction) {

                    if ($transaction->status !== 'paid') {
                        throw new \RuntimeException(
                            'Hanya transaksi dengan status paid yang dapat disettlement'
                        );
                    }

                    if (
                        $transaction->qrisCode()->value('merchant_id')
                        != $data['merchant_id']
                    ) {
                        throw new \RuntimeException(
                            'Transaksi tidak sesuai dengan merchant yang dipilih'
                        );
                    }

                    if (
                        SettlementTransaction::where(
                            'qris_transaction_id',
                            $transaction->id
                        )->exists()
                    ) {
                        throw new \RuntimeException(
                            'Transaksi sudah dimasukkan ke settlement'
                        );
                    }
                }

                $totalAmount = $transactions->sum('amount');

                $settlement = Settlement::create([
                    'user_id' => $user->id,
                    'merchant_id' => $data['merchant_id'],
                    'batch_code' => 'STL-' . strtoupper(Str::random(12)),
                    'total_amount' => $totalAmount,
                    'bank_name' => $data['bank_name'],
                    'account_number' => $data['account_number'],
                    'status' => 'pending',
                ]);

                foreach ($transactions as $transaction) {
                    SettlementTransaction::create([
                        'settlement_id' => $settlement->id,
                        'qris_transaction_id' => $transaction->id,
                    ]);
                }

                return $settlement;
            });

            return $this->success(
                $settlement->load([
                    'merchant',
                    'settlementTransactions.qrisTransaction'
                ]),
                'Settlement berhasil dibuat',
                201
            );

        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);

        } catch (QueryException $e) {
            Log::error('Gagal membuat settlement: ' . $e->getMessage());

            return $this->error(
                'Terjadi kesalahan pada database',
                500
            );
        }
    }

public function update(Request $request, string $id): JsonResponse
{
    $user = $request->user('api');

    $query = Settlement::query();

    if ($user->role !== 'admin') {
        $query->where('user_id', $user->id);
    }

    $settlement = $query->find($id);

    if (!$settlement) {
        return $this->error(
            'Settlement tidak ditemukan',
            404
        );
    }

    if ($user->role !== 'admin') {

        if ($settlement->status !== 'pending') {
            return $this->error(
                'Hanya settlement berstatus pending yang dapat diubah',
                422
            );
        }

        $validator = Validator::make($request->all(), [
            'bank_name' => 'sometimes|required|string|max:100',
            'account_number' => 'sometimes|required|string|max:50',
        ]);

        if ($validator->fails()) {
            return $this->error(
                'Data tidak valid',
                422,
                $validator->errors()
            );
        }

        $settlement->fill($validator->validated());
    }

    else {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:processed,completed,failed',
        ]);

        if ($validator->fails()) {
            return $this->error(
                'Data tidak valid',
                422,
                $validator->errors()
            );
        }

        $newStatus = $validator->validated()['status'];

        $allowedTransitions = [
            'pending' => ['processed', 'failed'],
            'processed' => ['completed', 'failed'],
            'completed' => [],
            'failed' => [],
        ];

        if (!in_array(
            $newStatus,
            $allowedTransitions[$settlement->status] ?? [],
            true
        )) {
            return $this->error(
                'Perubahan status settlement tidak diizinkan',
                422
            );
        }

        $settlement->status = $newStatus;

        if ($newStatus === 'processed') {
            $settlement->processed_at = now();
        }

        if ($newStatus === 'completed') {
            $settlement->completed_at = now();
        }
    }

    try {
        $settlement->save();

        return $this->success(
            $settlement,
            'Settlement berhasil diperbarui'
        );

    } catch (QueryException $e) {
        Log::error(
            'Gagal memperbarui settlement: ' . $e->getMessage()
        );

        return $this->error(
            'Terjadi kesalahan pada database',
            500
        );
    }
}

    public function destroy(string $id): JsonResponse
    {
        try {
            $settlement = Settlement::find($id);

            if (!$settlement) {
                return $this->error(
                    'Settlement tidak ditemukan',
                    404
                );
            }

            if ($settlement->status !== 'pending') {
                return $this->error(
                    'Hanya settlement berstatus pending yang dapat dihapus',
                    422
                );
            }

            $user = auth('api')->user();

            if ($user->role !== 'admin') {
                return $this->error(
                    'Hanya admin yang dapat menghapus settlement',
                    403
                );
            }

            DB::transaction(function () use ($settlement) {
                $settlement->settlementTransactions()->delete();
                $settlement->delete();
            });

            return $this->success(
                null,
                'Settlement berhasil dihapus'
            );

        } catch (QueryException $e) {
            Log::error('Gagal menghapus settlement: ' . $e->getMessage());

            return $this->error(
                'Terjadi kesalahan pada database',
                500
            );
        }
    }
}