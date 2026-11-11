<?php

namespace Tests\Unit\Facebook;

use App\Exceptions\FacebookGraphException;
use App\Models\FacebookPage;
use App\Models\User;
use App\Services\Facebook\RateLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GraphApiAndRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_access_token_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $rawToken = 'EAABsbCS1iHgBO_plain_text_long_lived_page_token_12345';

        $page = FacebookPage::create([
            'user_id' => $user->id,
            'page_id' => '555666777',
            'name' => 'Encrypted Token Page',
            'access_token' => $rawToken,
            'status' => 'active',
        ]);

        // Decrypted via Eloquent cast
        $this->assertSame($rawToken, $page->access_token);

        // Encrypted cipher stored in raw database column
        $storedCipher = DB::table('facebook_pages')->where('id', $page->id)->value('access_token');
        $this->assertNotSame($rawToken, $storedCipher);
    }

    public function test_rate_limit_service_throttles_when_usage_headers_exceed_threshold(): void
    {
        config(['facebook.rate_limit.usage_header_threshold_percent' => 80]);

        $rateLimiter = app(RateLimitService::class);

        $rateLimiter->recordCallAndHeaders(42, 10, [
            'X-Page-Usage' => [json_encode(['call_count' => 85, 'total_cputime' => 20, 'total_time' => 35])],
        ]);

        $status = $rateLimiter->getStatus(42, 10);
        $this->assertTrue($status['is_throttled']);
        $this->assertSame(85, $status['header_peak_percent']);

        $this->expectException(FacebookGraphException::class);
        $rateLimiter->ensureWithinLimits(42, 10);
    }

    public function test_facebook_graph_exception_classifies_error_codes_accurately(): void
    {
        $expired = FacebookGraphException::fromResponse([
            'error' => ['message' => 'Token expired', 'code' => 190, 'error_subcode' => 463],
        ], 401);
        $this->assertTrue($expired->isTokenExpiredOrInvalid());
        $this->assertFalse($expired->isRetryable());

        $missingPermission = FacebookGraphException::fromResponse([
            'error' => ['message' => 'Requires pages_manage_posts permission', 'code' => 200],
        ], 403);
        $this->assertTrue($missingPermission->isPermissionMissing());
        $this->assertFalse($missingPermission->isRetryable());

        $outsideWindow = FacebookGraphException::fromResponse([
            'error' => ['message' => 'Outside allowed 24h window', 'code' => 10, 'error_subcode' => 2018278],
        ], 400);
        $this->assertTrue($outsideWindow->isOutsideMessagingWindow());
        $this->assertFalse($outsideWindow->isRetryable());
    }
}
