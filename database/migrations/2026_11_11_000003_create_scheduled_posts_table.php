<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('scheduled_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('facebook_page_id')->constrained('facebook_pages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('scheduled_for')->index();
            $table->string('timezone', 64)->default('UTC');
            $table->string('status', 32)->default('pending')->index(); // pending, processing, published, failed, cancelled
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(4);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->string('facebook_post_id', 128)->nullable();
            $table->integer('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->json('failure_log')->nullable();
            $table->string('idempotency_key', 128)->unique();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index(['facebook_page_id', 'scheduled_for']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_posts');
    }
};
