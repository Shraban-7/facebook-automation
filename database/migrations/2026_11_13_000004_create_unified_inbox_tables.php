<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
            $table->string('external_id', 191);
            $table->string('participant_external_id', 191);
            $table->string('participant_name')->nullable();
            $table->string('status', 24)->default('open')->index(); // open, closed, snoozed
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('window_expires_at')->nullable()->index();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
            $table->index(['workspace_id', 'status', 'last_message_at']);
        });

        Schema::create('inbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->string('external_id', 191);
            $table->string('direction', 8)->default('in'); // in, out
            $table->text('body')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_auto_reply')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->string('status', 32)->default('received')->index();
            $table->timestamps();

            $table->unique(['conversation_id', 'external_id']);
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_target_id')->nullable()->constrained('post_targets')->nullOnDelete();
            $table->foreignId('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
            $table->string('external_id', 191);
            $table->string('parent_external_id', 191)->nullable();
            $table->string('author_external_id', 191);
            $table->string('author_name')->nullable();
            $table->text('body');
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('replied_at')->nullable();
            $table->string('sentiment', 24)->nullable();
            $table->timestamp('created_at_platform')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'external_id']);
            $table->index(['social_account_id', 'is_hidden']);
        });

        Schema::create('saved_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('title');
            $table->text('body');
            $table->string('shortcut', 64)->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'shortcut']);
        });

        Schema::create('rule_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('auto_reply_rules')->cascadeOnDelete();
            $table->string('event_ref', 191);
            $table->string('result', 32); // replied, moderated, skipped_window, skipped_cooldown, failed
            $table->timestamp('created_at')->useCurrent();

            $table->index(['rule_id', 'event_ref', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rule_executions');
        Schema::dropIfExists('saved_replies');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('inbox_messages');
        Schema::dropIfExists('conversations');
    }
};
