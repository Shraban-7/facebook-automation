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
        Schema::create('facebook_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('page_id', 64)->index();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('avatar_url', 1024)->nullable();
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->text('access_token');
            $table->text('user_access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable()->index();
            $table->timestamp('token_last_refreshed_at')->nullable();
            $table->json('permissions')->nullable();
            $table->json('tasks')->nullable();
            $table->boolean('webhook_subscribed')->default(false);
            $table->string('status', 32)->default('active')->index();
            $table->integer('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'page_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('facebook_user_id', 64)->nullable()->index()->after('email');
            $table->text('facebook_user_token')->nullable()->after('password');
            $table->timestamp('facebook_token_expires_at')->nullable()->after('facebook_user_token');
            $table->foreignId('active_facebook_page_id')
                ->nullable()
                ->after('facebook_token_expires_at')
                ->constrained('facebook_pages')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('active_facebook_page_id');
            $table->dropColumn([
                'facebook_user_id',
                'facebook_user_token',
                'facebook_token_expires_at',
            ]);
        });

        Schema::dropIfExists('facebook_pages');
    }
};
