<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores a buyer's Google Calendar OAuth token (keyed by email).
 *
 * When a buyer completes the calendar-connect flow for the first time, their
 * refresh token is persisted here. Subsequent reservations check this table
 * by the buyer's email — if a token exists, the calendar event is created
 * automatically without any action from the buyer.
 */
class BuyerCalendarToken extends Model
{
    protected $fillable = [
        'email',
        'access_token',
        'calendar_id',
    ];

    /**
     * Find a stored token by buyer email (case-insensitive).
     */
    public static function findByEmail(string $email): ?self
    {
        return static::whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
    }
}
