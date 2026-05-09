<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapiEvent extends Model
{
    protected $table = 'capi_events';

    protected $fillable = [
        'user_id',
        'event_name',
        'event_id',
        'event_source',
        'ctwa_clid',
        'contact_id',
        'payload',
        'status',
        'meta_response',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'meta_response' => 'array',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }
}
