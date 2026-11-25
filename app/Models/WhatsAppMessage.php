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

    public function media()
    {
        return $this->hasMany(WhatsAppMedia::class, 'message_id');
    }

    public function scopeUnreadIncoming(Builder $query): Builder
    {
        return $query->where('direction', 'incoming')->where('is_read', false);
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
            $details = $this->interactive_details;

            return $details['body'] ?? $details['selection'] ?? 'Interactive message';
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

    public function getInteractiveDetailsAttribute(): ?array
    {
        $metadata = $this->metadata ?? [];
        $type = $this->type === 'unsupported' ? ($metadata['original_type'] ?? $this->type) : $this->type;
        if (! in_array($type, ['interactive', 'button', 'order', 'unsupported'], true) && ! isset($metadata['interactive'])) {
            return null;
        }

        $payload = $this->getParsedContent();

        if (in_array($type, ['order', 'unsupported'], true)) {
            $items = $payload['product_items'] ?? $metadata['order']['product_items'] ?? $metadata['product_items'] ?? [];
            if ($type === 'order' || $items) {
                return ['selection' => 'Pesanan katalog', 'sections' => [['rows' => $items]]];
            }
        }

        $interactive = $payload['interactive'] ?? $metadata['interactive'] ?? $payload;
        if (! in_array($type, ['interactive', 'button'], true) && ! isset($metadata['interactive']) && ! isset($payload['interactive'])) {
            return null;
        }

        if ($this->direction === 'incoming') {
            if (isset($metadata['response_json']) || isset($interactive['nfm_reply'])) {
                return ['body' => 'Formulir WhatsApp dikirim oleh pelanggan'];
            }

            $reply = $interactive['button_reply'] ?? $interactive['list_reply'] ?? $metadata['button'] ?? $metadata;
            $selection = $reply['title'] ?? $reply['text'] ?? null;
            if ($selection === null && $type !== 'unsupported' && ! is_array($payload) && ! str_starts_with($this->content ?? '', 'Unsupported message type:')) {
                $selection = $this->content;
            }

            return [
                'selection' => $selection,
                'description' => $reply['description'] ?? null,
            ];
        }

        $interactive ??= $metadata;
        $action = $interactive['action'] ?? [];
        $sections = array_map(fn (array $section) => [
            'title' => $section['title'] ?? null,
            'rows' => $section['rows'] ?? $section['product_items'] ?? [],
        ], $action['sections'] ?? $interactive['sections'] ?? []);

        $productId = $action['product_retailer_id'] ?? null;
        if ($productId) {
            $sections = [['rows' => [['product_retailer_id' => $productId]]]];
        }

        return [
            'header' => $interactive['header']['text'] ?? (is_string($interactive['header'] ?? null) ? $interactive['header'] : null),
            'body' => $interactive['body']['text'] ?? (is_string($interactive['body'] ?? null) ? $interactive['body'] : null),
            'footer' => $interactive['footer']['text'] ?? (is_string($interactive['footer'] ?? null) ? $interactive['footer'] : null),
            'buttons' => $action['buttons'] ?? $interactive['buttons'] ?? [],
            'button_text' => $action['button'] ?? $action['parameters']['display_text'] ?? $action['parameters']['flow_cta'] ?? $interactive['button_text'] ?? null,
            'sections' => $sections,
        ];
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
        'interactive_details',
        'latitude',
        'longitude',
        'location_name',
        'location_address',
    ];
}
