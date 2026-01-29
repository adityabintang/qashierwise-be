<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMedia extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_media';

    protected $fillable = [
        'whatsapp_account_id',
        'whatsapp_message_id',
        'media_id',
        'mime_type',
        'filename',
        'file_size',
        'url',
        'local_path',
        'type',
        'sha256',
        'downloaded_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'downloaded_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function message()
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }
}
