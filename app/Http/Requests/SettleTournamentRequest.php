<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Tournament;
use Illuminate\Foundation\Http\FormRequest;

final class SettleTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Tournament|null $tournament */
        $tournament = $this->route('tournament');

        return $this->user() !== null
            && $tournament instanceof Tournament
            && $this->user()->can('settle', $tournament);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_place_user_id' => ['required', 'integer', 'min:1'],
            'second_place_user_id' => ['required', 'integer', 'min:1', 'different:first_place_user_id'],
            'third_place_user_id' => ['nullable', 'integer', 'min:1', 'different:first_place_user_id', 'different:second_place_user_id'],
            'idempotency_key' => ['required', 'string', 'min:4', 'max:120'],
        ];
    }
}
