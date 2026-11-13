<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('provider', 32)->default('facebook')->after('id')->index();
            $table->foreignId('social_account_id')
                ->nullable()
                ->after('facebook_page_id')
                ->constrained('social_accounts')
                ->nullOnDelete();
            $table->char('payload_hash', 64)->nullable()->after('event_id')->index();
        });

        Schema::table('auto_reply_rules', function (Blueprint $table) {
            $table->foreignId('workspace_id')
                ->nullable()
                ->after('user_id')
                ->constrained('workspaces')
                ->nullOnDelete();
            $table->foreignId('social_account_id')
                ->nullable()
                ->after('facebook_page_id')
                ->constrained('social_accounts')
                ->nullOnDelete();
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('workspace_id')
                ->nullable()
                ->after('user_id')
                ->constrained('workspaces')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::table('auto_reply_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_account_id');
            $table->dropConstrainedForeignId('workspace_id');
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('social_account_id');
            $table->dropColumn(['provider', 'payload_hash']);
        });
    }
};
