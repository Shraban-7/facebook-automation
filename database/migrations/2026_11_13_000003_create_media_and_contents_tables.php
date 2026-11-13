<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 32)->default('public');
            $table->string('path', 1024);
            $table->string('original_name')->nullable();
            $table->string('mime', 128);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->char('checksum_sha256', 64)->nullable()->index();
            $table->json('meta')->nullable();
            $table->string('status', 24)->default('ready')->index(); // uploading, ready, failed
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
        });

        Schema::create('contents', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('link_url', 1024)->nullable();
            $table->string('status', 32)->default('draft')->index(); // draft, pending_approval, approved, scheduled, partial, published, failed
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->string('timezone', 64)->default('UTC');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->json('tags')->nullable();
            $table->boolean('ai_generated')->default(false);
            $table->unsignedBigInteger('legacy_post_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['workspace_id', 'status']);
        });

        Schema::create('content_media', function (Blueprint $table) {
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('media_asset_id')->constrained('media_assets')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('alt_text', 500)->nullable();

            $table->primary(['content_id', 'media_asset_id']);
        });

        Schema::create('post_targets', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('content_id')->constrained('contents')->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
            $table->string('status', 32)->default('scheduled')->index(); // scheduled, queued, publishing, published, failed, cancelled
            $table->text('body_override')->nullable();
            $table->json('options')->nullable(); // title, privacy, hashtags, first_comment, media_urls, category
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('external_id', 191)->nullable()->index();
            $table->string('external_url', 1024)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('error_class', 32)->nullable()->index();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('legacy_scheduled_post_id')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['social_account_id', 'published_at']);
            $table->index(['external_id', 'social_account_id']);
        });

        Schema::create('publish_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_target_id')->constrained('post_targets')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->string('outcome', 24)->index(); // success, retryable_error, fatal_error
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_class', 32)->nullable();
            $table->string('provider_request_id', 128)->nullable();
            $table->text('response_excerpt')->nullable();
            $table->timestamps();

            $table->index(['post_target_id', 'attempt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_attempts');
        Schema::dropIfExists('post_targets');
        Schema::dropIfExists('content_media');
        Schema::dropIfExists('contents');
        Schema::dropIfExists('media_assets');
    }
};
