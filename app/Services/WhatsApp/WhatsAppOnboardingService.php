<?php

namespace App\Services\WhatsApp;

use App\Models\WhatsappConnection;
use Illuminate\Support\Facades\DB;

/**
 * Completes Meta Embedded Signup for the current school and manages the connection.
 *
 * The browser only reports which WABA / phone number the admin picked; the server verifies
 * that the exchanged token really has access to both (the phone must be listed under the
 * WABA for that token) before anything is saved.
 */
class WhatsAppOnboardingService
{
    public function __construct(
        private WhatsAppGraphClient $graph,
        private WhatsAppTenantBridge $bridge,
    ) {}

    public function complete(?int $schoolId, int $userId, string $code, ?string $wabaId, ?string $phoneNumberId): WhatsappConnection
    {
        $tokenData = $this->graph->exchangeCode($code);
        $token = (string) $tokenData['access_token'];
        $expiresAt = ! empty($tokenData['expires_in']) ? now()->addSeconds((int) $tokenData['expires_in']) : null;

        // Session info from the popup can be missing (closed early, blocked message) — fall
        // back to what Meta says this token was granted.
        if (! $wabaId) {
            $wabaId = $this->wabaFromToken($token);
        }
        if (! $wabaId) {
            throw new WhatsAppGraphException('Meta did not share a WhatsApp Business Account. Please run Connect again and finish every step.');
        }

        $phoneIds = $this->graph->wabaPhoneNumberIds($token, $wabaId);
        if (! $phoneNumberId && count($phoneIds) === 1) {
            $phoneNumberId = $phoneIds[0];
        }
        if (! $phoneNumberId || ! in_array($phoneNumberId, $phoneIds, true)) {
            throw new WhatsAppGraphException('The selected phone number is not part of the authorised WhatsApp Business Account.');
        }

        $this->bridge->assertPhoneAvailable($phoneNumberId, $schoolId);

        $phone = $this->graph->phoneNumber($token, $phoneNumberId);
        try {
            $waba = $this->graph->waba($token, $wabaId);
        } catch (WhatsAppGraphException) {
            $waba = [];
        }

        $this->graph->subscribeApp($token, $wabaId);

        $connection = DB::transaction(function () use ($wabaId, $waba, $phoneNumberId, $phone, $token, $expiresAt, $userId) {
            WhatsappConnection::query()
                ->where('phone_number_id', '!=', $phoneNumberId)
                ->where('status', 'connected')
                ->update(['status' => 'disconnected', 'access_token' => null, 'disconnected_at' => now()]);

            return WhatsappConnection::query()->updateOrCreate(
                ['phone_number_id' => $phoneNumberId],
                [
                    'waba_id' => $wabaId,
                    'waba_name' => $waba['name'] ?? null,
                    'business_id' => $waba['owner_business_info']['id'] ?? null,
                    'display_phone_number' => $phone['display_phone_number'] ?? null,
                    'verified_name' => $phone['verified_name'] ?? null,
                    'quality_rating' => $phone['quality_rating'] ?? null,
                    'platform_type' => $phone['platform_type'] ?? null,
                    'code_verification_status' => $phone['code_verification_status'] ?? null,
                    'access_token' => $token,
                    'token_expires_at' => $expiresAt,
                    'status' => 'connected',
                    'connected_by_id' => $userId,
                    'connected_at' => now(),
                    'disconnected_at' => null,
                    'last_error' => null,
                ]
            );
        });

        $this->bridge->link($schoolId, $phoneNumberId, $wabaId, $connection->display_phone_number);

        return $connection;
    }

    public function refresh(WhatsappConnection $connection): WhatsappConnection
    {
        $phone = $this->graph->phoneNumber((string) $connection->access_token, $connection->phone_number_id);
        $connection->update([
            'display_phone_number' => $phone['display_phone_number'] ?? $connection->display_phone_number,
            'verified_name' => $phone['verified_name'] ?? $connection->verified_name,
            'quality_rating' => $phone['quality_rating'] ?? null,
            'platform_type' => $phone['platform_type'] ?? null,
            'code_verification_status' => $phone['code_verification_status'] ?? null,
            'last_error' => null,
        ]);

        return $connection;
    }

    public function register(WhatsappConnection $connection, string $pin): WhatsappConnection
    {
        $this->graph->registerPhone((string) $connection->access_token, $connection->phone_number_id, $pin);

        return $this->refresh($connection);
    }

    public function disconnect(?int $schoolId, WhatsappConnection $connection): void
    {
        try {
            if ($connection->access_token) {
                $this->graph->unsubscribeApp((string) $connection->access_token, $connection->waba_id);
            }
        } catch (WhatsAppGraphException) {
            // Still disconnect locally — the token may already be revoked on Meta's side.
        }

        $connection->update(['status' => 'disconnected', 'access_token' => null, 'disconnected_at' => now()]);
        $this->bridge->unlink($schoolId, $connection->phone_number_id);
    }

    private function wabaFromToken(string $token): ?string
    {
        foreach ((array) ($this->graph->debugToken($token)['granular_scopes'] ?? []) as $scope) {
            if (($scope['scope'] ?? null) === 'whatsapp_business_management') {
                $ids = array_values((array) ($scope['target_ids'] ?? []));

                return count($ids) === 1 ? (string) $ids[0] : null;
            }
        }

        return null;
    }
}
