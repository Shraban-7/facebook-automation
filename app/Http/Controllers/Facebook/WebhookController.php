<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Services\Facebook\ActivityLogService;
use App\Services\Facebook\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    /**
     * Handle Meta Webhook verification GET handshake (`hub.mode`, `hub.verify_token`, `hub.challenge`).
     */
    public function verify(Request $request, WebhookService $webhookService): Response
    {
        $mode = $request->query('hub_mode') ?? $request->query('hub.mode');
        $verifyToken = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $verifiedChallenge = $webhookService->verifyHandshake(
            mode: is_string($mode) ? $mode : null,
            verifyToken: is_string($verifyToken) ? $verifyToken : null,
            challenge: is_string($challenge) ? $challenge : null,
        );

        if ($verifiedChallenge === null) {
            return response('Forbidden: Invalid verify token', 403)
                ->header('Content-Type', 'text/plain');
        }

        return response($verifiedChallenge, 200)
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Handle incoming Meta Webhook POST events with `X-Hub-Signature-256` verification and idempotent deduplication.
     */
    public function handle(
        Request $request,
        WebhookService $webhookService,
        ActivityLogService $activityLogger,
    ): JsonResponse {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (! $webhookService->verifySignature($rawBody, is_string($signature) ? $signature : null)) {
            $activityLogger->record([
                'category' => 'webhook',
                'action' => 'webhook.signature.invalid',
                'level' => 'error',
                'method' => 'POST',
                'endpoint' => '/api/webhooks/facebook',
                'http_status' => 401,
                'message' => 'Rejected webhook POST due to invalid or missing X-Hub-Signature-256 header.',
                'context' => ['signature' => $signature],
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid X-Hub-Signature-256 signature.',
            ], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        $result = $webhookService->ingestPayload(
            payload: $payload,
            signatureHeader: is_string($signature) ? $signature : null,
            signatureVerified: true,
        );

        return response()->json([
            'status' => 'EVENT_RECEIVED',
            'queued' => $result['queued_count'],
            'duplicates_ignored' => $result['duplicate_count'],
            'event_ids' => $result['event_ids'],
        ], 200);
    }
}
