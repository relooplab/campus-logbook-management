<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class InstitutionWorkspaceAllowedUser extends Pivot
{
    protected $table = 'institution_workspace_allowed_users';

    protected $fillable = [
        'institution_workspace_id',
        'user_id',
    ];
}