<?php

namespace App\Http\Requests\Organisations;

use App\Models\OrganisationOwnershipTransfer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AcceptOrganisationOwnershipTransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $transfer = $this->route('transfer');

        return $transfer instanceof OrganisationOwnershipTransfer
            && $transfer->nominee_user_id === $this->user()?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            //
        ];
    }
}
