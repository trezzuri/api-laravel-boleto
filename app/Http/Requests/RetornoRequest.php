<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RetornoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['arquivo' => ['required', 'file', 'max:20480']];
    }

    public function messages(): array
    {
        return [
            'arquivo.required' => 'Envie o arquivo de retorno.',
            'arquivo.file' => 'O arquivo enviado é inválido.',
            'arquivo.max' => 'O arquivo não pode ultrapassar 20 MB.',
        ];
    }
}