<?php

namespace App\Http\Requests;

use App\Models\Institution;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLogbookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validasi saat mengedit entri logbook (hanya untuk status draft/revisi).
     * Ukuran & jenis file upload diatur admin (institution settings).
     */
    public function rules(): array
    {
        $isRevisi = $this->route('logbook')?->jenis === 'revisi';

        $inst = Institution::active();
        $maxKb = $inst->maxUploadSizeMb() * 1024;
        $mimes = implode(',', $inst->allowedFileTypes());

        $rules = ['progres_kendala' => ['required', 'string']];

        if ($isRevisi) {
            $rules['tanggal_pengiriman'] = ['required', 'date', 'before_or_equal:today'];
        } else {
            $rules['tanggal_bimbingan'] = ['required', 'date', 'before_or_equal:today'];
            $rules['topik'] = ['required', 'string', 'max:255'];
        }

        $rules['lampiran'] = ['nullable', 'file', 'mimes:'.$mimes, 'max:'.$maxKb];
        $rules['catatan_perbaikan'] = ['nullable', 'file', 'mimes:'.$mimes, 'max:'.$maxKb];

        return $rules;
    }

    public function messages(): array
    {
        $inst = Institution::active();
        $maxMb = $inst->maxUploadSizeMb();
        $types = strtoupper(implode(', ', $inst->allowedFileTypes()));

        return [
            'tanggal_bimbingan.required' => 'Tanggal bimbingan wajib diisi.',
            'tanggal_bimbingan.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'tanggal_pengiriman.required' => 'Tanggal pengiriman revisi wajib diisi.',
            'tanggal_pengiriman.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'topik.required' => 'Topik bimbingan wajib diisi.',
            'progres_kendala.required' => 'Ringkasan perbaikan wajib diisi.',
            'lampiran.mimes' => 'Lampiran harus berupa file '.$types.'.',
            'lampiran.max' => 'Ukuran lampiran maksimal '.$maxMb.' MB.',
            'catatan_perbaikan.mimes' => 'Catatan perbaikan harus berupa file '.$types.'.',
            'catatan_perbaikan.max' => 'Catatan perbaikan maksimal '.$maxMb.' MB.',
        ];
    }
}