<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InvalidFinancialOperationException extends Exception
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'error_code' => 'INVALID_FINANCIAL_OPERATION',
                'errors' => [
                    'financial_operation' => [$this->getMessage()],
                ],
            ], 422);
        }

        return back()->withErrors(['financial_operation' => $this->getMessage()])->with('error', $this->getMessage());
    }
}
