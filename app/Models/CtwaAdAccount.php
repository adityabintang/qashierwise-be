<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CtwaAdAccount extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['access_token'];

    protected $casts = ['access_token' => 'encrypted'];
}
