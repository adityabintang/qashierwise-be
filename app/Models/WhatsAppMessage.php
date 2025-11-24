<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'user_id',
        'contact_id',
        'message_id',
        'direction',
        'type',
        'content',
        'metadata',
        'status',
        'is_read',
        'sent_at',
        'delivered_at',
        'read_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    // Accessors for frontend
    public function getBodyAttribute()
    {
        // For text messages, return content directly
        if ($this->type === 'text') {
            return $this->content ?? '';
        }
        // For template messages, extract template info
        if ($this->type === 'template') {
            $templateName = $this->metadata['template_name'] ?? 'Unknown template';
            return "Template: {$templateName}";
        }
        // For other message types, return content or empty string
        return $this->content ?? '';
    }

    public function getMediaUrlAttribute()
    {
        if (in_array($this->type, ['image', 'video', 'audio', 'document'])) {
            $content = is_string($this->content) ? json_decode($this->content, true) : $this->content;
            return $content['media_url'] ?? null;
        }
        return null;
    }

    public function getCaptionAttribute()
    {
        if ($this->type === 'image') {
            $content = is_string($this->content) ? json_decode($this->content, true) : $this->content;
            return $content['caption'] ?? null;
        }
        return null;
    }

    public function getFilenameAttribute()
    {
        if ($this->type === 'document') {
            $content = is_string($this->content) ? json_decode($this->content, true) : $this->content;
            return $content['filename'] ?? 'document';
        }
        return null;
    }

    protected $appends = ['body', 'media_url', 'caption', 'filename'];
}
