<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\WhatsApp\MessageHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class WebhookController extends Controller
{
    protected MessageHandler $messageHandler;

    public function __construct(MessageHandler $messageHandler)
    {
        $this->messageHandler = $messageHandler;
    }

    /**
     * Verify webhook for WhatsApp Business API
     *
     * @return \Illuminate\Http\Response
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = Setting::get('whatsapp_webhook_secret', config('whatsapp.webhook_secret'));

        Log::channel('whatsapp')->info('=== WEBHOOK VERIFY REQUEST ===', [
            'ip' => $request->ip(),
            'hub_mode' => $mode,
            'hub_challenge' => $challenge,
            'token_received' => $token ? substr($token, 0, 4).'****' : '(empty)',
            'token_expected' => $verifyToken ? substr($verifyToken, 0, 4).'****' : '(empty)',
            'token_match' => $token === $verifyToken,
            'all_params' => $request->query(),
            'headers' => $request->headers->all(),
        ]);

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::channel('whatsapp')->info('VERIFY SUCCESS — returning challenge', ['challenge' => $challenge]);

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::channel('whatsapp')->warning('VERIFY FAILED', [
            'reason' => $mode !== 'subscribe' ? 'mode is not subscribe' : 'token mismatch',
        ]);

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming WhatsApp messages
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        try {
            // Verify webhook signature (Meta HMAC-SHA256) unless disabled for local dev
            if (Config::get('whatsapp.verify_signature', true)) {
                try {
                    $this->verifySignature($request);
                } catch (\Exception $e) {
                    Log::channel('whatsapp')->warning('Webhook signature verification failed', [
                        'error' => $e->getMessage(),
                        'ip' => $request->ip(),
                    ]);
                    return response()->json(['error' => 'Invalid signature'], 403);
                }
            }

            $data = $request->all();
            $rawBody = $request->getContent();
            $verbose = Config::get('whatsapp.log_verbose', false);

            if ($verbose) {
                Log::channel('whatsapp')->info('=== WEBHOOK POST RECEIVED ===', [
                    'ip' => $request->ip(),
                    'content_type' => $request->header('Content-Type'),
                    'raw_body_length' => strlen($rawBody),
                    'raw_body' => $rawBody,
                ]);
                Log::channel('whatsapp')->info('Parsed payload', ['data' => $data]);
            } else {
                Log::channel('whatsapp')->info('=== WEBHOOK POST RECEIVED ===', [
                    'ip' => $request->ip(),
                    'raw_body_length' => strlen($rawBody),
                ]);
            }

            // Check if this is a message event
            if (isset($data['entry']) && is_array($data['entry'])) {
                $entryCount = count($data['entry']);
                Log::channel('whatsapp')->info("Processing {$entryCount} entries");

                foreach ($data['entry'] as $entryIndex => $entry) {
                    $entryId = $entry['id'] ?? 'unknown';
                    Log::channel('whatsapp')->info("Entry [{$entryIndex}]", ['entry_id' => $entryId]);

                    if (isset($entry['changes']) && is_array($entry['changes'])) {
                        foreach ($entry['changes'] as $changeIndex => $change) {
                            $field = $change['field'] ?? 'unknown';
                            Log::channel('whatsapp')->info("Change [{$entryIndex}][{$changeIndex}] field={$field}");

                            $value = $change['value'] ?? [];

                            // Log metadata/contacts only when verbose (avoid PII in production logs)
                            if ($verbose) {
                                if (isset($value['metadata'])) {
                                    Log::channel('whatsapp')->info('Metadata', [
                                        'display_phone_number' => $value['metadata']['display_phone_number'] ?? null,
                                        'phone_number_id' => $value['metadata']['phone_number_id'] ?? null,
                                    ]);
                                }
                                if (isset($value['contacts'])) {
                                    foreach ($value['contacts'] as $contact) {
                                        Log::channel('whatsapp')->info('Contact', [
                                            'wa_id' => $contact['wa_id'] ?? null,
                                            'name' => $contact['profile']['name'] ?? null,
                                        ]);
                                    }
                                }
                            }

                            // Process messages
                            if (isset($value['messages']) && is_array($value['messages'])) {
                                $msgCount = count($value['messages']);
                                Log::channel('whatsapp')->info("Found {$msgCount} message(s)");

                                foreach ($value['messages'] as $msgIndex => $message) {
                                    if ($verbose) {
                                        Log::channel('whatsapp')->info(">>> MESSAGE [{$msgIndex}]", [
                                            'from' => $message['from'] ?? null,
                                            'type' => $message['type'] ?? null,
                                            'id' => $message['id'] ?? null,
                                            'timestamp' => $message['timestamp'] ?? null,
                                            'text' => $message['text']['body'] ?? null,
                                            'interactive' => $message['interactive'] ?? null,
                                            'button' => $message['button'] ?? null,
                                        ]);
                                    } else {
                                        $bodyLen = isset($message['text']['body']) ? strlen($message['text']['body']) : 0;
                                        Log::channel('whatsapp')->info(">>> MESSAGE [{$msgIndex}]", [
                                            'from' => $message['from'] ?? null,
                                            'type' => $message['type'] ?? null,
                                            'id' => $message['id'] ?? null,
                                            'body_length' => $bodyLen,
                                        ]);
                                    }

                                    $this->messageHandler->handle($message, $value);

                                    Log::channel('whatsapp')->info("<<< MESSAGE [{$msgIndex}] processed OK");
                                }
                            } else {
                                Log::channel('whatsapp')->info('No messages in this change');
                            }

                            // Handle status updates
                            if (isset($value['statuses'])) {
                                foreach ($value['statuses'] as $status) {
                                    Log::channel('whatsapp')->info('STATUS UPDATE', [
                                        'message_id' => $status['id'] ?? null,
                                        'status' => $status['status'] ?? null,
                                        'timestamp' => $status['timestamp'] ?? null,
                                        'recipient_id' => $status['recipient_id'] ?? null,
                                        'errors' => $status['errors'] ?? null,
                                    ]);
                                }
                            }

                            // Handle errors from WhatsApp
                            if (isset($value['errors'])) {
                                Log::channel('whatsapp')->error('WHATSAPP ERRORS', ['errors' => $value['errors']]);
                            }
                        }
                    }
                }
            } else {
                Log::channel('whatsapp')->warning('No entry[] in payload — possibly a test ping or unknown format');
            }

            Log::channel('whatsapp')->info('=== WEBHOOK POST COMPLETE — returning 200 ===');

            return response()->json(['status' => 'ok']);
        } catch (\Exception $e) {
            Log::channel('whatsapp')->error('=== WEBHOOK ERROR ===', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Always return 200 to prevent WhatsApp from retrying the webhook
            return response()->json(['status' => 'ok']);
        }
    }

    /**
     * Verify webhook signature (optional security layer)
     */
    protected function verifySignature(Request $request): void
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            throw new \Exception('Missing X-Hub-Signature-256 header');
        }

        $secret = Config::get('whatsapp.webhook_app_secret') ?? Setting::get('whatsapp_webhook_secret', config('whatsapp.webhook_secret'));
        if (empty($secret)) {
            throw new \Exception('Webhook app secret not configured');
        }

        $expectedSignature = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        if (! hash_equals($expectedSignature, $signature)) {
            throw new \Exception('Invalid signature');
        }
    }
}
