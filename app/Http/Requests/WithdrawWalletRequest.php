<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class WithdrawWalletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount_cents' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_float($value) || is_bool($value) || ! preg_match('/^-?\d+$/', (string) $value)) {
                        $fail('Le montant du retrait doit être exprimé en centimes entiers (aucun nombre flottant autorisé).');
                    }
                },
                'integer',
                'min:100',
                'max:50000000',
            ],
            'iban' => ['nullable', 'string', 'min:10', 'max:64'],
            'idempotency_key' => ['required', 'string', 'min:4', 'max:140'],
        ];
    }
}
