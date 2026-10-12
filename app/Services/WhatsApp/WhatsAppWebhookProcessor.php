<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappMessage;
use App\Models\WhatsappWebhookEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies one verified Meta WhatsApp Cloud API webhook payload.
 *
 * Every event (a status update, an incoming message, or an error) is first recorded in the
 * school's `whatsapp_webhook_events` under a deterministic event_key with a unique index, in
 * the same transaction that applies it — so a retried or duplicated delivery inserts nothing
 * and changes nothing. Routing is per change: each change's metadata.phone_number_id is
 * resolved to one school's database connection, and its events are written there only.
 *
 * Logging never includes payload content, phone numbers, message text or tokens.
 */
class WhatsAppWebhookProcessor
{
    /** Delivery progress; a status only moves a message forward. */
    private const STATUS_RANK = [
        'accepted' => 1,
        'sent' => 2,
        'delivered' => 3,
        'read' => 4,
        'failed' => 5,
    ];

    public function __construct(private WhatsAppAccountResolver $resolver) {}

    /**
     * @return array{events: int, duplicates: int, unrouted: int}
     */
    public function process(array $payload): array
    {
        $summary = ['events' => 0, 'duplicates' => 0, 'unrouted' => 0];

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return $summary;
        }

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            $wabaId = isset($entry['id']) ? (string) $entry['id'] : null;
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== 'messages' || ! is_array($change['value'] ?? null)) {
                    continue;
                }
                $value = $change['value'];
                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');
                $connection = $phoneNumberId !== '' ? $this->resolver->connectionFor($phoneNumberId, $wabaId) : null;
                if ($connection === null) {
                    $summary['unrouted']++;
                    Log::warning('WhatsApp webhook: no school linked to this phone number id', [
                        'phone_number_id' => self::mask($phoneNumberId),
                    ]);

                    continue;
                }

                foreach ($this->eventsFrom($value, $phoneNumberId) as $event) {
                    $this->record($connection, $event) ? $summary['events']++ : $summary['duplicates']++;
                }
            }
        }

        return $summary;
    }

    /**
     * Flatten one change value into individual events.
     *
     * @return list<array{kind: string, key: string, wamid: ?string, status: ?string, at: ?Carbon, data: array, phone_number_id: string}>
     */
    private function eventsFrom(array $value, string $phoneNumberId): array
    {
        $events = [];

        foreach ((array) ($value['statuses'] ?? []) as $status) {
            $wamid = (string) ($status['id'] ?? '');
            $state = strtolower((string) ($status['status'] ?? ''));
            if ($wamid === '' || $state === '') {
                continue;
            }
            $events[] = [
                'kind' => 'status',
                'key' => hash('sha256', implode('|', ['status', $phoneNumberId, $wamid, $state, $status['timestamp'] ?? '', $status['recipient_id'] ?? ''])),
                'wamid' => $wamid,
                'status' => $state,
                'at' => self::time($status['timestamp'] ?? null),
                'data' => $status,
                'phone_number_id' => $phoneNumberId,
            ];
        }

        $contacts = collect((array) ($value['contacts'] ?? []))->keyBy(fn ($c) => (string) ($c['wa_id'] ?? ''));
        foreach ((array) ($value['messages'] ?? []) as $message) {
            $wamid = (string) ($message['id'] ?? '');
            if ($wamid === '') {
                continue;
            }
            $events[] = [
                'kind' => 'message',
                'key' => hash('sha256', 'message|'.$phoneNumberId.'|'.$wamid),
                'wamid' => $wamid,
                'status' => 'received',
                'at' => self::time($message['timestamp'] ?? null),
                'data' => ['message' => $message, 'contact' => $contacts->get((string) ($message['from'] ?? ''))],
                'phone_number_id' => $phoneNumberId,
            ];
        }

        foreach ((array) ($value['errors'] ?? []) as $error) {
            $events[] = [
                'kind' => 'error',
                'key' => hash('sha256', 'error|'.$phoneNumberId.'|'.json_encode($error)),
                'wamid' => null,
                'status' => null,
                'at' => null,
                'data' => $error,
                'phone_number_id' => $phoneNumberId,
            ];
        }

        return $events;
    }

    /** @return bool  false when this event was already recorded (duplicate delivery) */
    private function record(string $connection, array $event): bool
    {
        return DB::connection($connection)->transaction(function () use ($connection, $event) {
            $row = (new WhatsappWebhookEvent)->fill([
                'event_key' => $event['key'],
                'kind' => $event['kind'],
                'phone_number_id' => $event['phone_number_id'],
                'wamid' => $event['wamid'],
                'status' => $event['status'],
                'occurred_at' => $event['at'],
                'payload' => $event['data'],
                'processed_at' => now(),
            ]);
            $now = now();
            $attributes = $row->getAttributes() + ['created_at' => $now, 'updated_at' => $now];
            $inserted = WhatsappWebhookEvent::on($connection)->insertOrIgnore($attributes);
            if ($inserted === 0) {
                return false;
            }

            match ($event['kind']) {
                'status' => $this->applyStatus($connection, $event),
                'message' => $this->applyIncoming($connection, $event),
                default => Log::warning('WhatsApp webhook: error notification received', [
                    'code' => $event['data']['code'] ?? null,
                ]),
            };

            return true;
        });
    }

    private function applyStatus(string $connection, array $event): void
    {
        $data = $event['data'];
        $state = $event['status'];
        $at = $event['at'] ?? now();

        $message = WhatsappMessage::on($connection)->where('wamid', $event['wamid'])->lockForUpdate()->first()
            ?? (new WhatsappMessage)->setConnection($connection)->fill([
                'wamid' => $event['wamid'],
                'direction' => 'outbound',
                'phone_number_id' => $event['phone_number_id'],
                'contact_wa_id' => isset($data['recipient_id']) ? (string) $data['recipient_id'] : null,
            ]);

        $column = match ($state) {
            'sent' => 'sent_at',
            'delivered' => 'delivered_at',
            'read' => 'read_at',
            'failed' => 'failed_at',
            default => null,
        };
        if ($column && $message->{$column} === null) {
            $message->{$column} = $at;
        }

        // Webhooks can arrive out of order (read before delivered) — never move backwards.
        $rank = self::STATUS_RANK[$state] ?? 0;
        if ($rank > (int) $message->status_rank) {
            $message->status = $state;
            $message->status_rank = $rank;
        }

        if ($state === 'failed') {
            $error = (array) (($data['errors'] ?? [])[0] ?? []);
            $message->error_code = isset($error['code']) ? (string) $error['code'] : null;
            $message->error_title = isset($error['title']) ? mb_substr((string) $error['title'], 0, 255) : null;
        }
        if (! empty($data['conversation']['id'])) {
            $message->conversation_id = (string) $data['conversation']['id'];
        }
        if (! empty($data['pricing']['category'])) {
            $message->pricing_category = (string) $data['pricing']['category'];
        }

        $message->save();
    }

    private function applyIncoming(string $connection, array $event): void
    {
        $message = $event['data']['message'];
        $type = (string) ($message['type'] ?? 'unknown');

        $body = match ($type) {
            'text' => $message['text']['body'] ?? null,
            'button' => $message['button']['text'] ?? null,
            'interactive' => $message['interactive']['button_reply']['title']
                ?? $message['interactive']['list_reply']['title'] ?? null,
            'image', 'video', 'document' => $message[$type]['caption'] ?? null,
            'reaction' => $message['reaction']['emoji'] ?? null,
            default => null,
        };

        WhatsappMessage::on($connection)->firstOrCreate(
            ['wamid' => $event['wamid']],
            [
                'direction' => 'inbound',
                'phone_number_id' => $event['phone_number_id'],
                'contact_wa_id' => isset($message['from']) ? (string) $message['from'] : null,
                'type' => mb_substr($type, 0, 32),
                'body' => $body !== null ? (string) $body : null,
                'context_wamid' => $message['context']['id'] ?? null,
                'status' => 'received',
                'received_at' => $event['at'] ?? now(),
            ]
        );
    }

    private static function time(mixed $timestamp): ?Carbon
    {
        return is_numeric($timestamp)
            ? Carbon::createFromTimestamp((int) $timestamp)->setTimezone(config('app.timezone'))
            : null;
    }

    /** "1234567890123" → "…0123" — enough to recognise in logs, not enough to reuse. */
    public static function mask(?string $value): string
    {
        $value = (string) $value;

        return $value === '' ? '(none)' : '…'.substr($value, -4);
    }
}
