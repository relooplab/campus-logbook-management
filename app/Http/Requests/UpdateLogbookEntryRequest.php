<?php

namespace App\Http\Requests;

use App\Models\Institution;
use App\Models\LogbookEntry;
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

        $rules = ['progres_kendala' => ['nullable', 'string', 'max:500']];

        if ($isRevisi) {
            $rules['tanggal_pengiriman'] = ['required', 'date', 'before_or_equal:today'];
            $rules['riwayat_perbaikan'] = ['required', 'array', 'min:1'];
            $rules['riwayat_perbaikan.*.halaman'] = ['required', 'string', 'max:255'];
            $rules['riwayat_perbaikan.*.komentar_dosen'] = ['required', 'string', 'max:1000'];
            $rules['riwayat_perbaikan.*.perbaikan'] = ['required', 'string', 'max:2000'];
            $rules['riwayat_perbaikan.*.status'] = ['required', 'in:'.implode(',', LogbookEntry::PERBAIKAN_STATUSES)];
        } else {
            $rules['tanggal_bimbingan'] = ['required', 'date', 'before_or_equal:today'];
            $rules['topik'] = ['required', 'string', 'max:255'];
        }

        $rules['lampiran'] = ['nullable', 'file', 'mimes:'.$mimes, 'max:'.$maxKb];

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
            'progres_kendala.max' => 'Pesan untuk dosen maksimal 500 karakter.',
            'riwayat_perbaikan.required' => 'Tabel catatan perbaikan wajib diisi minimal 1 baris.',
            'riwayat_perbaikan.min' => 'Tabel catatan perbaikan wajib diisi minimal 1 baris.',
            'riwayat_perbaikan.*.halaman.required' => 'Kolom Halaman/Bagian wajib diisi.',
            'riwayat_perbaikan.*.komentar_dosen.required' => 'Kolom Komentar Dosen wajib diisi.',
            'riwayat_perbaikan.*.perbaikan.required' => 'Kolom Perbaikan yang Dilakukan wajib diisi.',
            'riwayat_perbaikan.*.status.required' => 'Kolom Status wajib dipilih.',
            'lampiran.mimes' => 'Lampiran harus berupa file '.$types.'.',
            'lampiran.max' => 'Ukuran lampiran maksimal '.$maxMb.' MB.',
        ];
    }
}