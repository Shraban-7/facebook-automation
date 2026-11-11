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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('facebook_page_id')->constrained('facebook_pages')->cascadeOnDelete();
            $table->string('facebook_post_id', 128)->nullable()->index();
            $table->string('type', 24)->default('text')->index(); // text, image, link
            $table->text('content');
            $table->json('media_urls')->nullable();
            $table->string('link_url', 1024)->nullable();
            $table->string('link_title')->nullable();
            $table->string('status', 32)->default('draft')->index(); // draft, scheduled, publishing, published, failed
            $table->timestamp('published_at')->nullable()->index();
            $table->string('permalink_url', 1024)->nullable();
            $table->json('metrics')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['facebook_page_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['facebook_page_id', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
