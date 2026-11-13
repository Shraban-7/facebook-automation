<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\FacebookPage;
use App\Models\SocialAccount;
use App\Services\Social\TokenVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MetaComplianceController extends Controller
{
    public function dataDeletion(Request $request, TokenVault $tokenVault): JsonResponse
    {
        $data = $this->parseSignedRequest((string) $request->input('signed_request', ''));
        if ($data === null) {
            return response()->json(['error' => 'Invalid signed_request.'], 400);
        }

        $userId = (string) ($data['user_id'] ?? '');
        $confirmationCode = (string) Str::ulid();

        if ($userId !== '') {
            $accounts = SocialAccount::query()
                ->whereIn('provider', ['facebook', 'instagram', 'threads'])
                ->where(function ($q) use ($userId): void {
                    $q->where('external_id', $userId)
                        ->orWhereJsonContains('settings->meta_user_id', $userId);
                })
                ->get();

            foreach ($accounts as $account) {
                $tokenVault->purge($account);
                $account->update(['status' => 'revoked']);
            }

            FacebookPage::query()->where('page_id', $userId)->delete();
        }

        return response()->json([
            'url' => url("/compliance/deletion-status/{$confirmationCode}"),
            'confirmation_code' => $confirmationCode,
        ]);
    }

    public function deauthorize(Request $request, TokenVault $tokenVault): JsonResponse
    {
        $data = $this->parseSignedRequest((string) $request->input('signed_request', ''));
        if ($data === null) {
            return response()->json(['error' => 'Invalid signed_request.'], 400);
        }

        $userId = (string) ($data['user_id'] ?? '');
        if ($userId !== '') {
            $accounts = SocialAccount::query()
                ->whereIn('provider', ['facebook', 'instagram', 'threads'])
                ->where(function ($q) use ($userId): void {
                    $q->where('external_id', $userId)
                        ->orWhereJsonContains('settings->meta_user_id', $userId);
                })
                ->get();

            foreach ($accounts as $account) {
                $tokenVault->purge($account);
                $account->update(['status' => 'revoked']);
            }
        }

        return response()->json(['status' => 'deauthorized']);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseSignedRequest(string $signedRequest): ?array
    {
        if (! str_contains($signedRequest, '.')) {
            return null;
        }

        [$encodedSig, $encodedPayload] = explode('.', $signedRequest, 2);
        $sig = $this->base64UrlDecode($encodedSig);
        $payloadJson = $this->base64UrlDecode($encodedPayload);

        if ($sig === false || $payloadJson === false) {
            return null;
        }

        $data = json_decode($payloadJson, true);
        if (! is_array($data)) {
            return null;
        }

        $secret = (string) (config('social.providers.facebook.client_secret') ?: config('services.facebook.client_secret', ''));
        if ($secret === '') {
            return null;
        }

        $expectedSig = hash_hmac('sha256', $encodedPayload, $secret, true);

        if (! hash_equals($expectedSig, $sig)) {
            return null;
        }

        return $data;
    }

    private function base64UrlDecode(string $input): string|false
    {
        return base64_decode(strtr($input, '-_', '+/'), true);
    }
}
