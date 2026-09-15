<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitExcuseLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'letter_body' => ['nullable', 'string', 'min:10', 'max:2000'],
            'letter_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'letter_pdf.mimes' => 'The letter must be a PDF file.',
            'letter_pdf.max' => 'The PDF must be 5 MB or smaller.',
            'photo.mimes' => 'The photo must be a JPEG or PNG image.',
            'photo.max' => 'The photo must be 5 MB or smaller.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! filled($this->input('letter_body')) && ! $this->hasFile('letter_pdf')) {
                $validator->errors()->add(
                    'letter_body',
                    'Type an explanation or upload a PDF letter.',
                );
            }
        });
    }
}
