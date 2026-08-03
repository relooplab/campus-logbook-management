<?php

namespace App\Http\Requests;

use App\Models\Institution;
use Illuminate\Foundation\Http\FormRequest;

class StoreLogbookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validasi entri logbook (sesi bimbingan biasa).
     * Ukuran & jenis file upload diatur admin (institution settings).
     */
    public function rules(): array
    {
        $inst = Institution::active();
        $maxKb = $inst->maxUploadSizeMb() * 1024;
        $mimes = implode(',', $inst->allowedFileTypes());

        return [
            'tanggal_bimbingan' => ['required', 'date', 'before_or_equal:today'],
            'topik' => ['required', 'string', 'max:255'],
            'progres_kendala' => ['required', 'string'],
            'lampiran' => ['nullable', 'file', 'mimes:'.$mimes, 'max:'.$maxKb],
        ];
    }

    public function messages(): array
    {
        $inst = Institution::active();
        $maxMb = $inst->maxUploadSizeMb();
        $types = strtoupper(implode(', ', $inst->allowedFileTypes()));

        return [
            'tanggal_bimbingan.required' => 'Tanggal bimbingan wajib diisi.',
            'tanggal_bimbingan.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'topik.required' => 'Topik bimbingan wajib diisi.',
            'progres_kendala.required' => 'Ringkasan perbaikan wajib diisi.',
            'lampiran.mimes' => 'Lampiran harus berupa file '.$types.'.',
            'lampiran.max' => 'Ukuran lampiran maksimal '.$maxMb.' MB.',
        ];
    }
}
