<?php

namespace App\Services\WhatsApp;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl;

    protected string $accessToken;

    protected string $phoneNumberId;

    public function __construct()
    {
        $this->apiUrl = Setting::get('whatsapp_api_url', config('whatsapp.api_url'));
        $this->accessToken = Setting::get('whatsapp_access_token', config('whatsapp.access_token')) ?? '';
        $this->phoneNumberId = Setting::get('whatsapp_phone_number_id', config('whatsapp.phone_number_id')) ?? '';
    }

    /**
     * Send a text message
     */
    public function sendTextMessage(string $to, string $message): array
    {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message,
                ],
            ]);

        Log::info('WhatsApp text message sent', [
            'to' => $to,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        return $response->json();
    }

    /**
     * Send an interactive message with buttons
     */
    public function sendButtonMessage(string $to, string $bodyText, array $buttons): array
    {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        $formattedButtons = array_map(function ($button) {
            return [
                'type' => 'reply',
                'reply' => [
                    'id' => $button['id'],
                    'title' => $button['title'],
                ],
            ];
        }, $buttons);

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $bodyText,
                    ],
                    'action' => [
                        'buttons' => $formattedButtons,
                    ],
                ],
            ]);

        Log::info('WhatsApp button message sent', [
            'to' => $to,
            'status' => $response->status(),
        ]);

        return $response->json();
    }

    /**
     * Send an interactive list message
     */
    public function sendListMessage(string $to, string $bodyText, string $buttonText, array $sections): array
    {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'list',
                    'body' => [
                        'text' => $bodyText,
                    ],
                    'action' => [
                        'button' => $buttonText,
                        'sections' => $sections,
                    ],
                ],
            ]);

        Log::info('WhatsApp list message sent', [
            'to' => $to,
            'status' => $response->status(),
        ]);

        return $response->json();
    }

    /**
     * Send a template message
     */
    public function sendTemplateMessage(string $to, string $templateName, array $parameters = []): array
    {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        $components = [];
        if (! empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(function ($param) {
                    return ['type' => 'text', 'text' => $param];
                }, $parameters),
            ];
        }

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'template',
                'template' => [
                    'name' => $templateName,
                    'language' => [
                        'code' => 'en',
                    ],
                    'components' => $components,
                ],
            ]);

        Log::info('WhatsApp template message sent', [
            'to' => $to,
            'template' => $templateName,
            'status' => $response->status(),
        ]);

        return $response->json();
    }

    /**
     * Send an image message
     */
    public function sendImageMessage(string $to, string $imageUrl, string $caption = ''): array
    {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        $imageData = [
            'link' => $imageUrl,
        ];

        if ($caption) {
            $imageData['caption'] = $caption;
        }

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'image',
                'image' => $imageData,
            ]);

        Log::info('WhatsApp image message sent', [
            'to' => $to,
            'status' => $response->status(),
        ]);

        return $response->json();
    }

    /**
     * Send a document message (PDF, etc.)
     */
    public function sendDocumentMessage(string $to, string $documentUrl, string $caption = '', string $filename = ''): array
    {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        $docData = ['link' => $documentUrl];
        if ($caption) {
            $docData['caption'] = $caption;
        }
        if ($filename) {
            $docData['filename'] = $filename;
        }

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'document',
                'document' => $docData,
            ]);

        Log::info('WhatsApp document message sent', [
            'to' => $to,
            'status' => $response->status(),
        ]);

        return $response->json();
    }

    /**
     * Mark a message as read
     */
    public function markAsRead(string $messageId): array
    {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $messageId,
            ]);

        return $response->json();
    }

    /**
     * Send an interactive flow message (WhatsApp Flows).
     * Flow must be published; use flow_id from Meta Business Suite / Graph API.
     * Works within 24-hour session (user must have messaged first).
     */
    public function sendFlowMessage(string $to, string $flowId, string $bodyText, string $buttonText = 'Open'): array
    {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        $response = Http::withToken($this->accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'flow',
                    'header' => [
                        'type' => 'text',
                        'text' => 'Banking',
                    ],
                    'body' => [
                        'text' => $bodyText,
                    ],
                    'action' => [
                        'name' => 'flow',
                        'parameters' => [
                            [
                                'type' => 'flow_id',
                                'flow_id' => $flowId,
                            ],
                            [
                                'type' => 'flow_cta',
                                'flow_cta' => $buttonText,
                            ],
                        ],
                    ],
                ],
            ]);

        Log::info('WhatsApp flow message sent', [
            'to' => $to,
            'flow_id' => $flowId,
            'status' => $response->status(),
        ]);

        return $response->json();
    }

    /**
     * Send typing indicator to show "typing..." in the guest's chat.
     * Lasts up to 25 seconds. Call before starting AI processing
     * so the guest sees immediate feedback while waiting.
     */
    public function sendTypingIndicator(string $to): void
    {
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";

        try {
            Http::withToken($this->accessToken)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'reaction',
                    'status' => 'typing',
                ]);
        } catch (\Exception $e) {
            // Non-fatal — don't break the flow if typing indicator fails
            Log::debug('Typing indicator failed', ['to' => $to, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Download media (image, document, etc.) from WhatsApp Cloud API and save to configured storage path.
     * Returns the relative storage path (e.g. whatsapp_attachments/2026/02/user_3_abc123.pdf) or null on failure.
     */
    public function downloadAndSaveMedia(string $mediaId, string $mimeType, int $userId, string $extension = ''): ?string
    {
        $getUrl = $this->apiUrl . '/' . $mediaId;
        $response = Http::withToken($this->accessToken)->get($getUrl);

        if (! $response->successful()) {
            Log::channel('whatsapp')->warning('WhatsApp media URL fetch failed', [
                'media_id' => $mediaId,
                'status' => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();
        $url = $data['url'] ?? null;
        if (! $url) {
            Log::channel('whatsapp')->warning('WhatsApp media response missing url', ['media_id' => $mediaId]);
            return null;
        }

        $fileContent = Http::withToken($this->accessToken)->get($url)->body();
        if (empty($fileContent)) {
            Log::channel('whatsapp')->warning('WhatsApp media download empty', ['media_id' => $mediaId]);
            return null;
        }

        $maxBytes = (int) config('whatsapp.attachments.max_bytes', 10 * 1024 * 1024);
        $allowedMimes = config('whatsapp.attachments.allowed_mime_types', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf']);
        if (strlen($fileContent) > $maxBytes) {
            Log::channel('whatsapp')->warning('WhatsApp media rejected: size exceeds limit', [
                'media_id' => $mediaId,
                'size' => strlen($fileContent),
                'max_bytes' => $maxBytes,
            ]);
            return null;
        }
        $allowed = is_array($allowedMimes) ? $allowedMimes : [];
        if (! empty($allowed) && ! in_array(strtolower($mimeType), array_map('strtolower', $allowed), true)) {
            Log::channel('whatsapp')->warning('WhatsApp media rejected: mime type not allowed', [
                'media_id' => $mediaId,
                'mime_type' => $mimeType,
            ]);
            return null;
        }

        $basePath = config('whatsapp.attachments.storage_path', 'whatsapp_attachments');
        $subDir = now()->format('Y/m/d');
        $filename = 'user_' . $userId . '_' . substr(md5($mediaId . microtime()), 0, 8);
        if ($extension) {
            $filename .= '.' . ltrim($extension, '.');
        } else {
            $filename .= $this->extensionFromMime($mimeType);
        }
        $relativePath = $basePath . '/' . $subDir . '/' . $filename;

        $fullPath = str_starts_with($basePath, '/') || preg_match('#^[A-Za-z]:#', $basePath)
            ? rtrim($basePath, '/') . '/' . $subDir . '/' . $filename
            : storage_path('app/' . $relativePath);
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (file_put_contents($fullPath, $fileContent) === false) {
            Log::channel('whatsapp')->warning('WhatsApp media save failed', ['path' => $relativePath]);
            return null;
        }

        if (config('whatsapp.attachments.scan_enabled', false)) {
            $scanner = app(\App\Contracts\AttachmentScannerInterface::class);
            if (! $scanner->scan($fullPath)) {
                @unlink($fullPath);
                Log::channel('whatsapp')->warning('WhatsApp media rejected: scan failed or infected', ['path' => $relativePath]);
                return null;
            }
        }

        Log::channel('whatsapp')->info('WhatsApp media saved', [
            'media_id' => $mediaId,
            'path' => $relativePath,
            'user_id' => $userId,
        ]);

        return $relativePath;
    }

    protected function extensionFromMime(string $mimeType): string
    {
        $map = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'application/pdf' => '.pdf',
        ];
        return $map[$mimeType] ?? '.bin';
    }
}
