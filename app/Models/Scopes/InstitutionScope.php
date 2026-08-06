<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tenant scope — aktif jika user login punya institution_id (anggota institusi).
 * User personal (institution_id null) tidak ter-filter (data pribadi pemilik).
 *
 * Pengecualian: dosen yang ditugaskan sebagai pembimbing/penguji tetap bisa
 * melihat mahasiswa bimbingannya walau mahasiswa itu dari institusi lain —
 * bimbingan/penguji lintas institusi memang didesain untuk didukung.
 */
class InstitutionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();
        $tenant = $user?->institution_id;
        if (!$tenant) {
            return;
        }

        $builder->where(function ($query) use ($model, $tenant, $user) {
            $query->where($model->qualifyColumn('institution_id'), $tenant)
                ->orWhere($model->qualifyColumn('pembimbing_1_id'), $user->id)
                ->orWhere($model->qualifyColumn('pembimbing_2_id'), $user->id)
                ->orWhere($model->qualifyColumn('penguji_1_id'), $user->id)
                ->orWhere($model->qualifyColumn('penguji_2_id'), $user->id);
        });
    }
}