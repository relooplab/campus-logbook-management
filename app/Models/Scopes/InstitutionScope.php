<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tenant scope — aktif jika user login punya institution_id (anggota institusi).
 * User personal (institution_id null) tidak ter-filter (data pribadi pemilik).
 */
class InstitutionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenant = auth()->user()?->institution_id;
        if ($tenant) {
            $builder->where($model->qualifyColumn('institution_id'), $tenant);
        }
    }
}