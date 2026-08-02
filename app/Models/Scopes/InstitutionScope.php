<?php

namespace App\Models\Scopes;

use App\Support\Feature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Tenant scope — hanya aktif di mode institusi.
 * Di mode individual (default) tidak ada filter (data pribadi pemilik).
 */
class InstitutionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (Feature::isInstitution()) {
            $tenant = auth()->user()?->institution_id;
            if ($tenant) {
                $builder->where($model->qualifyColumn('institution_id'), $tenant);
            }
        }
    }
}
