<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CtwaOrder extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'first_recap_at' => 'immutable_datetime',
        'paid_at' => 'immutable_datetime',
        'recap_total' => 'decimal:2',
        'paid_total' => 'decimal:2',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(CtwaSetting::class, 'ctwa_setting_id');
    }

    public function click(): BelongsTo
    {
        return $this->belongsTo(CtwaClick::class, 'ctwa_click_id');
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(CtwaDelivery::class);
    }
}
