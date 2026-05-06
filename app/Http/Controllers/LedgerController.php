<?php

namespace App\Http\Controllers;

use App\Http\Resources\TransactionResource;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function index(Request $request, Wallet $wallet): JsonResponse
    {
        if ($wallet->walletable_id != $request->user()->id || $wallet->walletable_type !== 'user') {
            return response()->error('Wallet not found.', 404);
        }

        $transactions = $wallet->transactions()
            ->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return response()->v1(200, 'Ledger retrieved.', [
            'data' => TransactionResource::collection($transactions->getCollection()),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ],
        ]);
    }
}
