<?php

namespace App\Http\Requests;

use App\Models\Institution;
use App\Support\ProgramContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $inst = Institution::current();
        $maxKb = $inst->maxUploadSizeMb() * 1024;
        $mimes = implode(',', $inst->allowedFileTypes());
        $ta = ProgramContext::resolve($this->user(), $this);

        return [
            'addressed_dosen_id' => ['nullable', Rule::in($ta?->allDosenIds() ?? [])],
            'tanggal_bimbingan' => ['required', 'date', 'before_or_equal:today'],
            'topik' => ['required', 'string', 'max:255'],
            'progres_kendala' => ['required', 'string'],
            'lampiran' => ['nullable', 'file', 'mimes:'.$mimes, 'max:'.$maxKb],
        ];
    }

    public function messages(): array
    {
        $inst = Institution::current();
        $maxMb = $inst->maxUploadSizeMb();
        $types = strtoupper(implode(', ', $inst->allowedFileTypes()));

        return [
            'addressed_dosen_id.in' => 'Penerima logbook harus pembimbing atau dosen penguji program Anda.',
            'tanggal_bimbingan.required' => 'Tanggal bimbingan wajib diisi.',
            'tanggal_bimbingan.before_or_equal' => 'Tanggal tidak boleh di masa depan.',
            'topik.required' => 'Topik bimbingan wajib diisi.',
            'progres_kendala.required' => 'Ringkasan perbaikan wajib diisi.',
            'lampiran.mimes' => 'Lampiran harus berupa file '.$types.'.',
            'lampiran.max' => 'Ukuran lampiran maksimal '.$maxMb.' MB.',
        ];
    }
}
