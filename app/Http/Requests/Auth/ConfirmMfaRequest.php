<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmMfaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasEnabledTwoFactorAuthentication() === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['code' => ['required', 'string']];
    }
}
