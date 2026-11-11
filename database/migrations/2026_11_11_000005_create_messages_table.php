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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_page_id')->constrained('facebook_pages')->cascadeOnDelete();
            $table->foreignId('auto_reply_rule_id')->nullable()->constrained('auto_reply_rules')->nullOnDelete();
            $table->string('channel', 24)->default('messenger')->index(); // messenger, comment
            $table->string('direction', 16)->default('inbound')->index(); // inbound, outbound
            $table->string('external_id', 128)->nullable()->index();
            $table->string('parent_external_id', 128)->nullable()->index();
            $table->string('sender_id', 64)->index();
            $table->string('sender_name')->nullable();
            $table->string('recipient_id', 64)->nullable();
            $table->text('message_text')->nullable();
            $table->text('reply_text')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->string('status', 32)->default('received')->index(); // received, queued, replied, moderated, skipped_outside_window, skipped_policy, failed
            $table->timestamp('last_user_interaction_at')->nullable()->index();
            $table->timestamp('replied_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['facebook_page_id', 'sender_id', 'last_user_interaction_at'], 'idx_messages_window');
            $table->index(['facebook_page_id', 'channel', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
