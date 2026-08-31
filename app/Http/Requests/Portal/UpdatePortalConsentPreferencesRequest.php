<?php

namespace App\Http\Requests\Portal;

use App\Enums\ConsentChannel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePortalConsentPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->attributes->has('portal_access_grant');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'channels' => ['present', 'array', 'max:3'],
            'channels.*' => [
                'string',
                'distinct',
                Rule::enum(ConsentChannel::class)->only([
                    ConsentChannel::Email,
                    ConsentChannel::Sms,
                    ConsentChannel::Telephone,
                ]),
            ],
        ];
    }
}
