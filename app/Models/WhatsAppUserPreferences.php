<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppUserPreferences extends Model
{
    protected $table = 'whatsapp_user_preferences';

    protected $fillable = [
        'user_id',
        'consent_given_at',
        'consent_version',
        'consent_parameters',
        'prefer_human_agent',
        'disable_long_term_memory',
        'preferred_language',
    ];

    protected function casts(): array
    {
        return [
            'consent_given_at' => 'datetime',
            'consent_parameters' => 'array',
            'prefer_human_agent' => 'boolean',
            'disable_long_term_memory' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasConsented(): bool
    {
        return $this->consent_given_at !== null;
    }
}
