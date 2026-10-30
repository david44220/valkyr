<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TournamentStateConflictException extends Exception
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => $this->getMessage(),
                'error_code' => 'TOURNAMENT_STATE_CONFLICT',
                'errors' => [
                    'tournament' => [$this->getMessage()],
                ],
            ], 409);
        }

        return back()->withErrors(['tournament' => $this->getMessage()])->with('error', $this->getMessage());
    }
}
