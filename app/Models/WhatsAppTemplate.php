<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'whatsapp_account_id',
        'template_id',
        'name',
        'language',
        'category',
        'status',
        'components',
        'body',
        'header',
        'header_type',
        'footer',
        'buttons',
        'quality_score',
        'usage_count'
    ];

    protected $casts = [
        'components' => 'array',
        'buttons' => 'array',
        'usage_count' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }
}
