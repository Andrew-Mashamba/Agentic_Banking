<?php

namespace App\Actions\Jetstream;

use App\Models\GuestConversation;
use App\Models\User;
use App\Models\WhatsAppPendingTask;
use App\Models\WhatsAppUserMemory;
use App\Models\WhatsAppUserPreferences;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     * Cascades to WhatsApp-related data (right to be forgotten). banking_audit_logs
     * are also removed via FK cascade; retain only if regulation requires anonymization.
     */
    public function delete(User $user): void
    {
        $userId = $user->id;

        GuestConversation::where('guest_id', $userId)->delete();
        WhatsAppUserMemory::where('user_id', $userId)->delete();
        WhatsAppUserPreferences::where('user_id', $userId)->delete();
        WhatsAppPendingTask::where('user_id', $userId)->delete();

        $user->deleteProfilePhoto();
        $user->tokens->each->delete();
        $user->delete();
    }
}
