<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Guest extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'phone_number',
        'name',
        'loyalty_points',
        'preferences',
        'first_visit_at',
        'last_visit_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'preferences' => 'array',
        'loyalty_points' => 'integer',
        'first_visit_at' => 'datetime',
        'last_visit_at' => 'datetime',
    ];

    /**
     * Get the WhatsApp session for the guest.
     */
    public function whatsappSession(): HasOne
    {
        return $this->hasOne(WhatsAppSession::class);
    }

    /**
     * Get conversation history for the guest.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(GuestConversation::class);
    }

    /**
     * Update last visit timestamp.
     */
    public function updateLastVisit(): void
    {
        $this->update(['last_visit_at' => now()]);
    }
}
