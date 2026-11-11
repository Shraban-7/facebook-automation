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
        Schema::create('auto_reply_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('facebook_page_id')->constrained('facebook_pages')->cascadeOnDelete();
            $table->string('name');
            $table->string('channel', 24)->default('both')->index(); // messenger, comment, both
            $table->string('trigger_type', 24)->default('keyword')->index(); // keyword, exact, contains, regex, fallback
            $table->json('keywords')->nullable();
            $table->json('banned_words')->nullable();
            $table->text('reply_template');
            $table->text('private_reply_template')->nullable();
            $table->string('moderation_action', 24)->default('none'); // none, hide
            $table->boolean('respect_24h_window')->default(true);
            $table->unsignedInteger('cooldown_seconds')->default(300);
            $table->unsignedInteger('priority')->default(100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('match_count')->default(0);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['facebook_page_id', 'is_active', 'channel', 'priority'], 'idx_auto_reply_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_reply_rules');
    }
};
