<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tournament;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('create', Tournament::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'game' => ['required', 'string', Rule::in(Tournament::ALLOWED_GAMES)],
            'format' => ['required', 'string', Rule::in(Tournament::ALLOWED_FORMATS)],
            'entry_fee_cents' => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_float($value) || is_bool($value) || ! preg_match('/^-?\d+$/', (string) $value)) {
                        $fail('Les frais d\'entrée doivent être exprimés en centimes entiers.');
                    }
                },
                'integer',
                'min:100',
                'max:10000000',
            ],
            'max_participants' => ['required', 'integer', 'min:2', 'max:128'],
            'starts_at' => ['nullable', 'date'],
        ];
    }
}
