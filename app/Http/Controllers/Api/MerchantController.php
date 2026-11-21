<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class MerchantController extends Controller
{
    use ApiResponse;

    /*
    |--------------------------------------------------------------------------
    | GET - Menampilkan Semua Merchant
    |--------------------------------------------------------------------------
    */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user('api');

            $query = Merchant::with('user:id,name,email');

            // Merchant hanya melihat toko miliknya
            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }

            $merchants = $query->latest()->get();

            return $this->success(
                $merchants,
                'Data merchant berhasil ditampilkan'
            );

        } catch (Throwable $e) {
            report($e);

            return $this->error(
                'Gagal mengambil data merchant',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | POST - Menambahkan Merchant
    |--------------------------------------------------------------------------
    */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user('api');

            $rules = [
                'merchant_name' => 'required|string|max:255',
                'nmid' => 'required|string|max:50|unique:merchants,nmid',
                'business_category' => 'required|string|max:100',
                'city' => 'required|string|max:100',
                'status' => 'sometimes|in:active,inactive',
            ];

            // Hanya admin boleh menentukan user_id
            if ($user->role === 'admin') {
                $rules['user_id'] = 'required|integer|exists:users,id';
            } else {
                $rules['user_id'] = 'prohibited';
            }

            $validator = Validator::make(
                $request->all(),
                $rules
            );

            if ($validator->fails()) {
                return $this->error(
                    'Validasi gagal',
                    422,
                    $validator->errors()
                );
            }

            $data = $validator->validated();

            // Pemilik otomatis dari token JWT
            if ($user->role !== 'admin') {
                $data['user_id'] = $user->id;
            }

            $merchant = Merchant::create($data);

            return $this->success(
                $merchant->load('user:id,name,email'),
                'Merchant berhasil ditambahkan',
                201
            );

        } catch (Throwable $e) {
            report($e);

            return $this->error(
                'Gagal menambahkan merchant',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET - Menampilkan Detail Merchant
    |--------------------------------------------------------------------------
    */
    public function show(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $user = $request->user('api');

            $merchant = Merchant::with('user:id,name,email')
                ->find($id);

            if (!$merchant) {
                return $this->error(
                    'Merchant tidak ditemukan',
                    404
                );
            }

            // Cek kepemilikan merchant
            if (
                $user->role !== 'admin' &&
                (int) $merchant->user_id !== (int) $user->id
            ) {
                return $this->error(
                    'Anda tidak memiliki akses ke merchant ini',
                    403
                );
            }

            return $this->success(
                $merchant,
                'Detail merchant berhasil ditampilkan'
            );

        } catch (Throwable $e) {
            report($e);

            return $this->error(
                'Gagal mengambil detail merchant',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PUT/PATCH - Memperbarui Merchant
    |--------------------------------------------------------------------------
    */
    public function update(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $user = $request->user('api');

            $merchant = Merchant::find($id);

            if (!$merchant) {
                return $this->error(
                    'Merchant tidak ditemukan',
                    404
                );
            }

            // Hanya admin atau pemilik merchant
            if (
                $user->role !== 'admin' &&
                (int) $merchant->user_id !== (int) $user->id
            ) {
                return $this->error(
                    'Anda tidak memiliki izin mengubah merchant ini',
                    403
                );
            }

            $validator = Validator::make(
                $request->all(),
                [
                    'user_id' => 'prohibited',

                    'merchant_name' =>
                        'sometimes|required|string|max:255',

                    'nmid' => [
                        'sometimes',
                        'required',
                        'string',
                        'max:50',
                        Rule::unique('merchants', 'nmid')
                            ->ignore($merchant->id),
                    ],

                    'business_category' =>
                        'sometimes|required|string|max:100',

                    'city' =>
                        'sometimes|required|string|max:100',

                    'status' =>
                        'sometimes|required|in:active,inactive',
                ]
            );

            if ($validator->fails()) {
                return $this->error(
                    'Validasi gagal',
                    422,
                    $validator->errors()
                );
            }

            $merchant->update($validator->validated());

            return $this->success(
                $merchant->load('user:id,name,email'),
                'Merchant berhasil diperbarui'
            );

        } catch (Throwable $e) {
            report($e);

            return $this->error(
                'Gagal memperbarui merchant',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE - Menghapus Merchant
    |--------------------------------------------------------------------------
    */
    public function destroy(
        Request $request,
        string $id
    ): JsonResponse {
        try {
            $user = $request->user('api');

            $merchant = Merchant::find($id);

            if (!$merchant) {
                return $this->error(
                    'Merchant tidak ditemukan',
                    404
                );
            }

            // Hanya admin atau pemilik merchant
            if (
                $user->role !== 'admin' &&
                (int) $merchant->user_id !== (int) $user->id
            ) {
                return $this->error(
                    'Anda tidak memiliki izin menghapus merchant ini',
                    403
                );
            }

            $merchant->delete();

            return $this->success(
                null,
                'Merchant berhasil dihapus'
            );

        } catch (Throwable $e) {
            report($e);

            return $this->error(
                'Gagal menghapus merchant. Periksa data terkait.',
                500
            );
        }
    }
}
