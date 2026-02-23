<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;

/**
 * Counters for monitoring: message volume, AI requests/failures, handoffs, circuit opens.
 * Stored in cache with daily keys; optional alert when circuit opens.
 */
class WhatsAppMetricsService
{
    protected string $prefix = 'wa:metrics:';
    protected int $ttl = 86400 * 2; // 2 days

    protected function key(string $name): string
    {
        return $this->prefix . $name . ':' . now()->format('Y-m-d');
    }

    public function incrementMessagesReceived(): void
    {
        $k = $this->key('messages');
        Cache::put($k, (int) Cache::get($k, 0) + 1, $this->ttl);
    }

    public function incrementAiRequests(): void
    {
        Cache::put($this->key('ai_requests'), (int) Cache::get($this->key('ai_requests'), 0) + 1, $this->ttl);
    }

    public function incrementAiFailures(): void
    {
        Cache::put($this->key('ai_failures'), (int) Cache::get($this->key('ai_failures'), 0) + 1, $this->ttl);
    }

    public function incrementHandoffs(): void
    {
        Cache::put($this->key('handoffs'), (int) Cache::get($this->key('handoffs'), 0) + 1, $this->ttl);
    }

    public function incrementCircuitOpens(): void
    {
        Cache::put($this->key('circuit_opens'), (int) Cache::get($this->key('circuit_opens'), 0) + 1, $this->ttl);
    }

    public function getTodayCounts(): array
    {
        return [
            'messages' => (int) Cache::get($this->key('messages'), 0),
            'ai_requests' => (int) Cache::get($this->key('ai_requests'), 0),
            'ai_failures' => (int) Cache::get($this->key('ai_failures'), 0),
            'handoffs' => (int) Cache::get($this->key('handoffs'), 0),
            'circuit_opens' => (int) Cache::get($this->key('circuit_opens'), 0),
        ];
    }
}
