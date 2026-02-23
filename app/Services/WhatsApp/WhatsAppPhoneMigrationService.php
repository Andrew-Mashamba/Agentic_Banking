<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WhatsAppSession;
use App\Models\WhatsAppPendingTask;
use Illuminate\Support\Facades\Log;

/**
 * When a user changes their phone number, migrate WhatsApp session and related data
 * from the old number to the new number so they can continue using WhatsApp banking.
 */
class WhatsAppPhoneMigrationService
{
    public function __construct(
        protected StateManager $stateManager
    ) {}

    /**
     * Migrate WhatsApp data from old phone to new phone (e.g. after profile update).
     * Call this when user updates phone_number.
     */
    public function migrate(User $user, string $oldPhone, string $newPhone): bool
    {
        if ($oldPhone === $newPhone) {
            return true;
        }
        if (preg_replace('/\D/', '', $oldPhone) === preg_replace('/\D/', '', $newPhone)) {
            return true;
        }
        $userId = $user->id;

        $session = WhatsAppSession::where('phone_number', $oldPhone)->first();
        if ($session) {
            $session->update([
                'phone_number' => $newPhone,
                'guest_id' => $userId,
            ]);
            $this->stateManager->clearStateByPhone($oldPhone);
            Log::channel('whatsapp')->info('WhatsApp session migrated to new phone', [
                'user_id' => $userId,
                'from' => $oldPhone,
                'to' => $newPhone,
            ]);
        }

        WhatsAppPendingTask::where('user_id', $userId)
            ->where('phone_number', $oldPhone)
            ->update(['phone_number' => $newPhone]);

        return true;
    }
}
