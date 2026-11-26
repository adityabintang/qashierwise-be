<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtwaClick extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['click_id', 'click_hash'];

    protected $casts = ['clicked_at' => 'immutable_datetime', 'click_id' => 'encrypted'];
}
