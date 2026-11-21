<?php

namespace App\Http\Controllers;

use App\Models\QrisTransaction;
use App\Traits\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QrisTransactionController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        try {
            $transactions = QrisTransaction::query()
                ->with(['user:id,name,email,role', 'qrisCode:id,merchant_id,reference_code'])
                ->select('id', 'user_id', 'qris_code_id', 'reference_number', 'amount', 'status')
                ->when($request->query('status'), fn($q, $status) => $q->where('status', $status))
                ->latest('id')
                ->get();

            return $this->success($transactions, 'Daftar transaksi QRIS');
        } catch (QueryException $e) {
            Log::error('Gagal membaca transaksi QRIS: ' . $e->getMessage());
            return $this->error('Terjadi kesalahan pada database', 500);
        }
    }

    public function show(string $id): JsonResponse
    {
        try {
            $transaction = QrisTransaction::with(['user:id,name,email,role', 'qrisCode:id,merchant_id,reference_code'])
                ->select('id', 'user_id', 'qris_code_id', 'reference_number', 'amount', 'status')
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

    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id'          => 'required|integer|exists:users,id',
                'qris_code_id'     => 'required|integer|exists:qris_codes,id',
                'reference_number' => 'required|string|max:50|unique:qris_transactions,reference_number',
                'amount'           => 'required|integer|min:100',
                'status'           => 'sometimes|in:pending,paid,failed',
            ]);

            if ($validator->fails()) {
                return $this->error('Data tidak valid', 422, $validator->errors());
            }

            $transaction = QrisTransaction::create($validator->validated());
            return $this->success($transaction, 'Pembayaran QRIS berhasil dicatat', 201);
        } catch (QueryException $e) {
            Log::error('Gagal menyimpan transaksi QRIS: ' . $e->getMessage());
            return $this->error('Terjadi kesalahan pada database', 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $transaction = QrisTransaction::find($id);
            if (!$transaction) {
                return $this->error('Transaksi QRIS tidak ditemukan', 404);
            }

            $validator = Validator::make($request->all(), [
                'amount' => 'sometimes|required|integer|min:100',
                'status' => 'sometimes|required|in:pending,paid,failed',
            ]);

            if ($validator->fails()) {
                return $this->error('Data tidak valid', 422, $validator->errors());
            }

            $transaction->update($validator->validated());
            return $this->success($transaction, 'Transaksi QRIS berhasil diperbarui');
        } catch (QueryException $e) {
            Log::error('Gagal mengubah transaksi QRIS: ' . $e->getMessage());
            return $this->error('Terjadi kesalahan pada database', 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            $deleted = QrisTransaction::whereKey($id)->delete();
            if ($deleted === 0) {
                return $this->error('Transaksi QRIS tidak ditemukan', 404);
            }

            return $this->success(null, 'Transaksi QRIS berhasil dihapus');
        } catch (QueryException $e) {
            Log::error('Gagal menghapus transaksi QRIS: ' . $e->getMessage());
            return $this->error('Terjadi kesalahan pada database', 500);
        }
    }
}