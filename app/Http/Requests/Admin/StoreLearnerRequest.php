<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLearnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canAdminister() ?? false;
    }

    /**
     * Superadmins pick the company; an admin's learners always go to the
     * admin's own company, so the field is ignored for them.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'company_id' => [
                $this->user()->isSuperAdmin() ? 'required' : 'nullable',
                'integer',
                'exists:companies,id',
            ],
        ];
    }
}
