<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppCoexistenceImport extends Model
{
    protected $table = 'whatsapp_coexistence_imports';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'onboarded_at' => 'immutable_datetime',
            'subscribed_at' => 'immutable_datetime',
            'history_progress' => 'integer',
            'contacts_without_phone' => 'integer',
        ];
    }

    public function status(): array
    {
        $status = fn (string $value) => $value === 'waiting' && $this->onboarded_at->addDay()->lte(now())
            ? 'unconfirmed' : $value;

        return [
            'contacts_status' => $status($this->contacts_status),
            'history_status' => $status($this->history_status),
            'history_progress' => $this->history_progress,
            'contacts_without_phone' => $this->contacts_without_phone,
        ];
    }
}
