<?php

namespace App\Http\Controllers;

use App\Http\Resources\WalletResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $wallets = $request->user()->wallets()->get();

        return response()->v1(200, 'Wallets retrieved.', WalletResource::collection($wallets));
    }
}
