<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;

final class CancelTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tournament|null $tournament */
        $tournament = $this->route('tournament');

        return $this->user() !== null
            && $tournament instanceof Tournament
            && $this->user()->can('cancel', $tournament);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'string', 'min:4', 'max:120'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
