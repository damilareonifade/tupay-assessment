<?php

namespace App\Http\Controllers;

use App\Services\SwapService;
use App\WalletModule\Exceptions\InsufficientBalanceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SwapController extends Controller
{
    public function __construct(private readonly SwapService $swapService) {}

    public function swap(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $result = $this->swapService->swapNgnToCny(
                $request->user(),
                (int) $request->amount
            );
        } catch (InsufficientBalanceException $e) {
            return response()->error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return response()->error($e->getMessage(), 409);
        } catch (\InvalidArgumentException $e) {
            return response()->error($e->getMessage(), 422);
        }

        return response()->v1(200, 'Swap completed successfully.', [
            'from_currency' => 'NGN',
            'to_currency' => 'CNY',
            'debited_kobo' => $result['from_amount'],
            'credited_fen' => $result['to_amount'],
            'rate' => $result['rate'],
            'ngn_balance' => $result['ngn_wallet']->balance->whole(),
            'cny_balance' => $result['cny_wallet']->balance->whole(),
        ]);
    }
}
