<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 32)->index(); // facebook, instagram, threads, linkedin, youtube, x, tiktok, pinterest
            $table->string('kind', 24)->default('page'); // page, profile, channel, organization, business
            $table->string('external_id', 191);
            $table->foreignId('parent_account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('avatar_url', 1024)->nullable();
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->string('status', 32)->default('active')->index(); // active, reauth_required, paused, revoked, error
            $table->json('capabilities')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('token_expires_at')->nullable()->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->unsignedBigInteger('legacy_facebook_page_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'provider', 'external_id'], 'uniq_workspace_provider_external');
            $table->index(['workspace_id', 'status']);
        });

        Schema::create('oauth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->unique()->constrained('social_accounts')->cascadeOnDelete();
            $table->longText('access_token');
            $table->longText('refresh_token')->nullable();
            $table->string('token_type', 32)->default('Bearer');
            $table->json('scopes')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->unsignedSmallInteger('key_version')->default(1);
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oauth_tokens');
        Schema::dropIfExists('social_accounts');
    }
};
