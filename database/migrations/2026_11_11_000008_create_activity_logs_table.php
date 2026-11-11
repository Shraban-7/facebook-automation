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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('facebook_page_id')->nullable()->constrained('facebook_pages')->nullOnDelete();
            $table->string('category', 32)->index(); // api_call, webhook, oauth, scheduler, auto_reply, moderation, rate_limit, error
            $table->string('action', 96)->index();
            $table->string('level', 16)->default('info')->index(); // info, warning, error, critical
            $table->string('method', 12)->nullable();
            $table->string('endpoint', 512)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable()->index();
            $table->integer('graph_error_code')->nullable()->index();
            $table->integer('graph_error_subcode')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('rate_limit_usage')->nullable();
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category', 'created_at']);
            $table->index(['facebook_page_id', 'level', 'created_at']);
            $table->index(['category', 'level']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
