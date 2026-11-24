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
        'id',
        'user_id',
        'qris_code_id',
        'reference_number',
        'amount',
        'status',
        'created_at',
        'updated_at',
    ];

    private const TRANSITIONS = [
        'pending' => ['paid', 'failed'],
        'paid' => [],
        'failed' => [],
    ];

    // GET /api/qris-transactions
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user('api');

            $transactions = QrisTransaction::query()
                ->with([
                    'user:id,name,email,role',
                    'qrisCode:id,merchant_id,nominal,status'
                ])
                ->select(self::COLUMNS)
                ->when(
                    $user->role !== 'admin',
                    fn($q) => $q->where('user_id', $user->id)
                )
                ->when(
                    $request->query('status'),
                    fn($q, $status) => $q->where('status', $status)
                )
                ->latest('id')
                ->get();

            return $this->success(
                $transactions,
                'Daftar transaksi QRIS'
            );

        } catch (QueryException $e) {
            Log::error(
                'Gagal membaca transaksi QRIS: ' . $e->getMessage()
            );

            return $this->error(
                'Terjadi kesalahan pada database',
                500
            );
        }
    }

    // GET /api/qris-transactions/{id}
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user('api');

            $transaction = QrisTransaction::with([
                'user:id,name,email,role',
                'qrisCode:id,merchant_id,nominal,status'
            ])
                ->select(self::COLUMNS)
                ->find($id);

            if (!$transaction) {
                return $this->error(
                    'Transaksi QRIS tidak ditemukan',
                    404
                );
            }

            // Ownership
            if (
                $user->role !== 'admin' &&
                (int) $transaction->user_id !== (int) $user->id
            ) {
                return $this->error(
                    'Anda tidak memiliki akses ke transaksi ini',
                    403
                );
            }

            return $this->success(
                $transaction,
                'Detail transaksi QRIS'
            );

        } catch (QueryException $e) {
            Log::error(
                'Gagal membaca detail transaksi QRIS: ' . $e->getMessage()
            );

            return $this->error(
                'Terjadi kesalahan pada database',
                500
            );
        }
    }

    // POST /api/qris-transactions
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'qris_code_id' => 'required|integer|exists:qris_codes,id',
                'amount' => 'sometimes|numeric|min:1|decimal:0,2',
                'user_id' => 'prohibited',
                'reference_number' => 'prohibited',
                'status' => 'prohibited',
            ]);

            if ($validator->fails()) {
                return $this->error(
                    'Data tidak valid',
                    422,
                    $validator->errors()
                );
            }

            $data = $validator->validated();

            $code = QrisCode::find($data['qris_code_id']);

            if (!$code) {
                return $this->error(
                    'Kode QRIS tidak ditemukan',
                    404
                );
            }

            // QRIS sudah digunakan
            if ($code->status === 'used') {
                return $this->error(
                    'Kode QRIS sudah digunakan',
                    422
                );
            }

            // QRIS sudah kedaluwarsa
            if (
                $code->status === 'expired' ||
                $code->expires_at->isPast()
            ) {
                return $this->error(
                    'Kode QRIS sudah kedaluwarsa',
                    422
                );
            }

            // Nominal harus sesuai dengan kode QRIS
            if (
                isset($data['amount']) &&
                abs(
                    (float) $data['amount'] -
                    (float) $code->nominal
                ) >= 0.005
            ) {
                return $this->error(
                    'Nominal tidak sesuai dengan nominal kode QRIS',
                    422,
                    [
                        'amount' => [
                            'Nominal harus ' .
                            number_format(
                                (float) $code->nominal,
                                2,
                                '.',
                                ''
                            )
                        ]
                    ]
                );
            }

            $transaction = QrisTransaction::create([
                'user_id' => $request->user('api')->id,
                'qris_code_id' => $code->id,
                'reference_number' => 'TRX-' .
                    Str::upper(Str::random(20)),
                'amount' => $code->nominal,
                'status' => 'pending',
            ]);

            return $this->success(
                $transaction->fresh(),
                'Pembayaran QRIS berhasil dicatat',
                201
            );

        } catch (QueryException $e) {
            Log::error(
                'Gagal menyimpan transaksi QRIS: ' . $e->getMessage()
            );

            return $this->error(
                'Terjadi kesalahan pada database',
                500
            );
        }
    }

    // PUT/PATCH /api/qris-transactions/{id}
    // User: membatalkan transaksi milik sendiri
    // Admin: mengelola status seluruh transaksi
    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $user = $request->user('api');

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:pending,paid,failed',
                'amount' => 'prohibited',
                'user_id' => 'prohibited',
                'qris_code_id' => 'prohibited',
                'reference_number' => 'prohibited',
            ]);

            if ($validator->fails()) {
                return $this->error(
                    'Data tidak valid',
                    422,
                    $validator->errors()
                );
            }

            $newStatus = $validator->validated()['status'];

            $result = DB::transaction(
                function () use ($id, $newStatus, $user) {

                    $transaction = QrisTransaction::lockForUpdate()
                        ->find($id);

                    if (!$transaction) {
                        return [
                            'error' => [
                                'Transaksi QRIS tidak ditemukan',
                                404
                            ]
                        ];
                    }

                    // Ownership
                    if (
                        $user->role !== 'admin' &&
                        (int) $transaction->user_id !== (int) $user->id
                    ) {
                        return [
                            'error' => [
                                'Anda tidak memiliki izin mengubah transaksi ini',
                                403
                            ]
                        ];
                    }

                    // User hanya dapat membatalkan transaksi
                    if (
                        $user->role !== 'admin' &&
                        $newStatus !== 'failed'
                    ) {
                        return [
                            'error' => [
                                'User hanya dapat membatalkan transaksi',
                                403
                            ]
                        ];
                    }

                    // Status yang sama tidak perlu diubah
                    if ($newStatus === $transaction->status) {
                        return ['ok' => $transaction];
                    }

                    // Validasi transisi status
                    if (
                        !in_array(
                            $newStatus,
                            self::TRANSITIONS[$transaction->status],
                            true
                        )
                    ) {
                        return [
                            'error' => [
                                "Status tidak dapat diubah dari '{$transaction->status}' ke '{$newStatus}'",
                                422
                            ]
                        ];
                    }

                    // Konfirmasi pembayaran oleh admin
                    if ($newStatus === 'paid') {

                        $code = QrisCode::lockForUpdate()
                            ->find($transaction->qris_code_id);

                        if (!$code) {
                            return [
                                'error' => [
                                    'Kode QRIS tidak ditemukan',
                                    404
                                ]
                            ];
                        }

                        if ($code->status === 'used') {
                            return [
                                'error' => [
                                    'Kode QRIS sudah dibayar oleh transaksi lain',
                                    409
                                ]
                            ];
                        }

                        if (
                            $code->status === 'expired' ||
                            $code->expires_at->isPast()
                        ) {
                            return [
                                'error' => [
                                    'Kode QRIS sudah kedaluwarsa',
                                    422
                                ]
                            ];
                        }

                        $code->update([
                            'status' => 'used',
                            'used_at' => now()
                        ]);
                    }

                    $transaction->update([
                        'status' => $newStatus
                    ]);

                    return ['ok' => $transaction];
                }
            );

            if (isset($result['error'])) {
                return $this->error(...$result['error']);
            }

            return $this->success(
                $result['ok']->fresh(),
                'Transaksi QRIS berhasil diperbarui'
            );

        } catch (QueryException $e) {
            Log::error(
                'Gagal mengubah transaksi QRIS: ' . $e->getMessage()
            );

            return $this->error(
                'Terjadi kesalahan pada database',
                500
            );
        }
    }

    // DELETE /api/qris-transactions/{id}
    // Khusus admin
    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $user = $request->user('api');

            // Perlindungan tambahan RBAC
            if ($user->role !== 'admin') {
                return $this->error(
                    'Hanya admin yang dapat menghapus transaksi',
                    403
                );
            }

            $transaction = QrisTransaction::find($id);

            if (!$transaction) {
                return $this->error(
                    'Transaksi QRIS tidak ditemukan',
                    404
                );
            }

            if ($transaction->settlementTransaction()->exists()) {
                return $this->error(
                    'Transaksi sudah masuk settlement dan tidak dapat dihapus',
                    409
                );
            }

            $transaction->delete();

            return $this->success(
                null,
                'Transaksi QRIS berhasil dihapus'
            );

        } catch (QueryException $e) {
            Log::error(
                'Gagal menghapus transaksi QRIS: ' . $e->getMessage()
            );

            return $this->error(
                'Terjadi kesalahan pada database',
                500
            );
        }
    }
}
