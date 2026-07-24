<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminFeaturePermission extends Model
{
    protected $table = 'admin_feature_permissions';

    protected $fillable = [
        'resource',
        'can_create',
        'can_read',
        'can_update',
        'can_delete',
    ];
}

