<?php

namespace App\Http\Controllers\Webhooks;

use App\Contracts\Social\SupportsWebhooks;
use App\Http\Controllers\Controller;
use App\Jobs\Social\ProcessSocialWebhookJob;
use App\Models\WebhookEvent;
use App\Services\Social\ProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SocialWebhookController extends Controller
{
    public function verify(Request $request, string $provider): Response
    {
        $mode = (string) $request->query('hub_mode', $request->query('hub.mode', ''));
        $token = (string) $request->query('hub_verify_token', $request->query('hub.verify_token', ''));
        $challenge = (string) $request->query('hub_challenge', $request->query('hub.challenge', ''));

        $expected = (string) (config("social.providers.{$provider}.verify_token")
            ?? config('services.facebook.verify_token', ''));

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return response($challenge, 200, ['Content-Type' => 'text/plain']);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request, string $provider, ProviderManager $providers): JsonResponse
    {
        $providerInstance = $providers->provider($provider);
        $rawBody = $request->getContent();

        if ($providerInstance instanceof SupportsWebhooks) {
            if (! $providerInstance->verify($request)) {
                return response()->json(['error' => 'Invalid webhook signature.'], 403);
            }
        }

        $payloadHash = hash('sha256', $provider.'|'.$rawBody);
        $existing = WebhookEvent::query()->where('payload_hash', $payloadHash)->first();
        if ($existing) {
            return response()->json(['status' => 'duplicate']);
        }

        $event = WebhookEvent::query()->create([
            'provider' => $provider,
            'event_type' => (string) ($request->input('object') ?? $provider.'.event'),
            'payload_hash' => $payloadHash,
            'payload' => $request->json()->all(),
            'status' => 'pending',
        ]);

        ProcessSocialWebhookJob::dispatch($event->id);

        return response()->json(['status' => 'accepted'], 200);
    }
}
