<?php

namespace Logcutter\LogPulse\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'level' => ['nullable', Rule::in([
                'all',
                'emergency',
                'alert',
                'critical',
                'error',
                'warning',
                'notice',
                'info',
                'debug',
            ])],
            'search' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'string', 'max:255'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:250'],
        ];
    }
}
