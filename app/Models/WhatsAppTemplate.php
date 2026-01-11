<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    /**
     * The "booted" method of the model.
     * Apply Row Level Security - only show templates for user's ACTIVE WhatsApp account
     * Uses phone_number_id for filtering (consistent with contacts and messages)
     */
    protected static function booted(): void
    {
        static::addGlobalScope('userTemplates', function (Builder $builder) {
            // Only apply scope if user is authenticated
            if (auth()->check()) {
                $userId = auth()->id();

                // Get user's active WhatsApp account
                $activeAccount = WhatsAppAccount::withoutGlobalScopes()
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->first();

                if ($activeAccount) {
                    // Only show templates for this user's ACTIVE account using phone_number_id
                    $builder->where('phone_number_id', $activeAccount->phone_number_id);
                } else {
                    // No active account = no templates shown
                    $builder->whereRaw('1 = 0');
                }
            } else {
                // If not authenticated, return no results (security default)
                $builder->whereRaw('1 = 0');
            }
        });
    }

    protected $fillable = [
        'whatsapp_account_id',
        'phone_number_id',
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
        'usage_count',
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
