<?php

namespace App\Http\Requests\Organisations;

use App\Models\Organisation;
use App\Models\Party;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePartyRelationshipRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $organisation = $this->route('current_organisation');
        $partyIdentifier = $this->route('party');
        $party = is_string($partyIdentifier)
            ? Party::query()->where('uuid', $partyIdentifier)->first()
            : null;

        return [
            'related_party_id' => [
                'required',
                'integer',
                Rule::exists('parties', 'id')->where(
                    'organisation_id',
                    $organisation instanceof Organisation ? $organisation->id : 0,
                ),
                Rule::notIn([$party instanceof Party ? $party->id : 0]),
            ],
            'type' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'started_at' => ['nullable', 'date'],
        ];
    }
}
