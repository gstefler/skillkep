<?php

namespace App\Http\Requests\Manage;

use App\Models\CourseUpload;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StartCourseUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'filename' => ['required', 'string', 'max:255', 'regex:/\.zip$/i'],
            'size' => ['required', 'integer', 'min:1', 'max:'.CourseUpload::MAX_SIZE],
            'last_modified' => ['required', 'integer', 'min:0'],
            'pass_score' => ['required', 'integer', 'min:0', 'max:100'],
            'lock_modules' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'filename.regex' => 'A fájlnak ZIP-archívumnak kell lennie.',
            'size.max' => 'A fájl legfeljebb 8 GB lehet.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['lock_modules' => $this->boolean('lock_modules')]);
    }
}
