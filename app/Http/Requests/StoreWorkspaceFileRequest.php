<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkspaceFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi dilakukan di controller via MahasiswaTaPolicy::viewWorkspace
        // (upload dilakukan oleh pemilik TA atau dosen pembimbing).
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => ['required', 'array'],
            'files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:51200'], // 50 MB
            'bab' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'Pilih minimal satu file.',
            'files.*.mimes' => 'Hanya file PDF, DOC, DOCX, XLS, atau XLSX yang diperbolehkan.',
            'files.*.max' => 'Ukuran file maksimal 50 MB.',
            'bab.max' => 'Label bab maksimal 50 karakter.',
        ];
    }
}
