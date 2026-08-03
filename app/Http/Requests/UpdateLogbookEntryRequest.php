<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLogbookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validasi saat mengedit entri logbook (hanya untuk status draft/revisi).
     */
    public function rules(): array
    {
        $isRevisi = $this->route('logbook')?->jenis === 'revisi';

        $rules = ['progres_kendala' => ['required', 'string']];

        if ($isRevisi) {
            $rules['tanggal_pengiriman'] = ['required', 'date', 'before_or_equal:today'];
        } else {
            $rules['tanggal_bimbingan'] = ['required', 'date', 'before_or_equal:today'];
            $rules['topik'] = ['required', 'string', 'max:255'];
        }

        $rules['lampiran'] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];
        $rules['catatan_perbaikan'] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'tanggal_bimbingan.required' => 'Tanggal bimbingan wajib diisi.',
            'tanggal_bimbingan.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'tanggal_pengiriman.required' => 'Tanggal pengiriman revisi wajib diisi.',
            'tanggal_pengiriman.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'topik.required' => 'Topik bimbingan wajib diisi.',
            'progres_kendala.required' => 'Ringkasan perbaikan wajib diisi.',
            'lampiran.mimes' => 'Lampiran harus berupa file PDF.',
            'lampiran.max' => 'Ukuran lampiran maksimal 10 MB.',
            'catatan_perbaikan.mimes' => 'Catatan perbaikan harus berupa PDF.',
            'catatan_perbaikan.max' => 'Catatan perbaikan maksimal 10 MB.',
        ];
    }
}
