<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'whatsapp_account_id',
        'whatsapp_contact_id',
        'message_id',
        'wam_id',
        'direction',
        'status',
        'type',
        'content',
        'media',
        'metadata',
        'context_message_id',
        'template_name',
        'template_language',
        'error_code',
        'error_message',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at'
    ];

    protected $casts = [
        'media' => 'array',
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }

    public function mediaFiles()
    {
        return $this->hasMany(WhatsAppMedia::class, 'whatsapp_message_id');
    }
}
