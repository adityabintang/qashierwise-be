<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    /**
     * The "booted" method of the model.
     * Apply Row Level Security - only show messages for authenticated user with ACTIVE WhatsApp account
     */
    protected static function booted(): void
    {
        static::addGlobalScope('userMessages', function (Builder $builder) {
            if (auth()->check()) {
                $userId = auth()->id();

                // Get user's active WhatsApp account
                $activeAccount = WhatsAppAccount::where('user_id', $userId)
                    ->where('is_active', true)
                    ->first();

                if ($activeAccount) {
                    // Only show messages for this user AND this specific phone number
                    $builder->where('user_id', $userId)
                        ->where('phone_number_id', $activeAccount->phone_number_id);
                } else {
                    // No active account = no data shown
                    $builder->whereRaw('1 = 0');
                }
            } else {
                // If not authenticated, return no results
                $builder->whereRaw('1 = 0');
            }
        });
    }

    protected $fillable = [
        'user_id',
        'phone_number_id',
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
        'read_at',
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

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'phone_number_id', 'phone_number_id');
    }

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'contact_id');
    }

    // Helper to get parsed content
    private function getParsedContent()
    {
        if (empty($this->content)) {
            return [];
        }

        return is_string($this->content) ? json_decode($this->content, true) : $this->content;
    }

    // Accessors for frontend
    public function getBodyAttribute()
    {
        $content = $this->getParsedContent();

        // For text messages, return content directly
        if ($this->type === 'text') {
            return $this->content ?? '';
        }

        // For template messages, extract template info
        if ($this->type === 'template') {
            $templateName = $content['template_name'] ?? $this->metadata['template_name'] ?? 'Unknown template';

            return "Template: {$templateName}";
        }

        // For interactive messages (buttons, lists)
        if ($this->type === 'interactive') {
            return $content['body'] ?? 'Interactive message';
        }

        // For location messages
        if ($this->type === 'location') {
            $name = $content['name'] ?? '';
            $address = $content['address'] ?? '';

            return $name ?: $address ?: 'Location shared';
        }

        // For document messages
        if ($this->type === 'document') {
            $filename = $content['filename'] ?? null;
            $caption = $content['caption'] ?? null;

            return $filename ?: $caption ?: 'Document';
        }

        // For image messages
        if ($this->type === 'image') {
            $caption = $content['caption'] ?? null;

            return $caption ?: 'Image';
        }

        // For video messages
        if ($this->type === 'video') {
            $caption = $content['caption'] ?? null;

            return $caption ?: 'Video';
        }

        // For audio messages
        if ($this->type === 'audio') {
            return 'Audio message';
        }

        // For sticker messages
        if ($this->type === 'sticker') {
            return 'Sticker';
        }

        // For contact messages
        if ($this->type === 'contact' || $this->type === 'contacts') {
            return 'Contact card';
        }

        // For other message types, just return the type
        return ucfirst($this->type ?? 'Message');
    }

    public function getMediaUrlAttribute()
    {
        if (in_array($this->type, ['image', 'video', 'audio', 'document'])) {
            $content = $this->getParsedContent();

            // Check for 'url' first (our format), then 'media_url' for backward compatibility
            return $content['url'] ?? $content['media_url'] ?? null;
        }

        return null;
    }

    public function getCaptionAttribute()
    {
        if (in_array($this->type, ['image', 'video', 'document'])) {
            $content = $this->getParsedContent();

            return $content['caption'] ?? null;
        }

        return null;
    }

    public function getFilenameAttribute()
    {
        if ($this->type === 'document') {
            $content = $this->getParsedContent();

            return $content['filename'] ?? 'document';
        }

        return null;
    }

    public function getTemplateNameAttribute()
    {
        if ($this->type === 'template') {
            $content = $this->getParsedContent();

            return $content['template_name'] ?? $this->metadata['template_name'] ?? null;
        }

        return null;
    }

    public function getButtonsAttribute()
    {
        if ($this->type === 'interactive') {
            $content = $this->getParsedContent();

            return $content['buttons'] ?? [];
        }

        return [];
    }

    public function getListSectionsAttribute()
    {
        if ($this->type === 'interactive') {
            $content = $this->getParsedContent();

            return $content['sections'] ?? [];
        }

        return [];
    }

    public function getButtonTextAttribute()
    {
        if ($this->type === 'interactive') {
            $content = $this->getParsedContent();

            return $content['button_text'] ?? null;
        }

        return null;
    }

    public function getLatitudeAttribute()
    {
        if ($this->type === 'location') {
            $content = $this->getParsedContent();

            return $content['latitude'] ?? null;
        }

        return null;
    }

    public function getLongitudeAttribute()
    {
        if ($this->type === 'location') {
            $content = $this->getParsedContent();

            return $content['longitude'] ?? null;
        }

        return null;
    }

    public function getLocationNameAttribute()
    {
        if ($this->type === 'location') {
            $content = $this->getParsedContent();

            return $content['name'] ?? null;
        }

        return null;
    }

    public function getLocationAddressAttribute()
    {
        if ($this->type === 'location') {
            $content = $this->getParsedContent();

            return $content['address'] ?? null;
        }

        return null;
    }

    protected $appends = [
        'body',
        'media_url',
        'caption',
        'filename',
        'template_name',
        'buttons',
        'list_sections',
        'button_text',
        'latitude',
        'longitude',
        'location_name',
        'location_address',
    ];
}
