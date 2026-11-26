<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtwaRecap extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'total' => 'decimal:2',
        'sent_at' => 'immutable_datetime',
        'reviewed_at' => 'immutable_datetime',
    ];
}
