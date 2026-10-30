<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InsufficientFundsException extends Exception
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'error_code' => 'INSUFFICIENT_FUNDS',
                'errors' => [
                    'balance_cents' => [$this->getMessage()],
                ],
            ], 422);
        }

        return back()->withErrors(['balance_cents' => $this->getMessage()])->with('error', $this->getMessage());
    }
}
