<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletResource;
use App\Http\Responses\ApiResponse;
use App\Models\DepartmentUserRole;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function __construct(
        private ApiResponse $apiResponse,
    ) {}

    public function show(): JsonResponse
    {
        $labId = $this->resolveLabId();

        if (! $labId) {
            return $this->apiResponse->error('Could not resolve lab for the authenticated user.', 403);
        }

        $wallet = Wallet::query()->where('lab_id', $labId)->firstOrFail();

        return $this->apiResponse->success(
            new WalletResource($wallet),
            'Wallet retrieved successfully'
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $labId = $this->resolveLabId();

        if (! $labId) {
            return $this->apiResponse->error('Could not resolve lab for the authenticated user.', 403);
        }

        $wallet = Wallet::query()->where('lab_id', $labId)->firstOrFail();

        $transactions = $wallet->transactions()
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return $this->apiResponse->success(
            TransactionResource::collection($transactions),
            'Transactions retrieved successfully'
        );
    }

    public function showTransaction(Transaction $transaction): JsonResponse
    {
        $labId = $this->resolveLabId();

        if (! $labId || $transaction->wallet->lab_id !== $labId) {
            return $this->apiResponse->error('Transaction not found', 404);
        }

        return $this->apiResponse->success(
            new TransactionResource($transaction),
            'Transaction retrieved successfully'
        );
    }

    private function resolveLabId(): ?int
    {
        $user = request()->user();

        if ($user->hasRole('system_admin')) {
            return (int) request()->query('lab_id');
        }

        return DepartmentUserRole::query()
            ->join('roles', 'roles.id', '=', 'department_user_roles.role_id')
            ->join('departments', 'departments.id', '=', 'department_user_roles.department_id')
            ->where('department_user_roles.user_id', $user->id)
            ->where('roles.name', 'lab_manager')
            ->where('roles.guard_name', 'sanctum')
            ->value('departments.lab_id');
    }
}
