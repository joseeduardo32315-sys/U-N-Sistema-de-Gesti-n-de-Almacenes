<?php

namespace App\Http\Requests\Process;

use Illuminate\Foundation\Http\FormRequest;

class IndexProcessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}