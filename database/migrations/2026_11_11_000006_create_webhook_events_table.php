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
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_page_id')->nullable()->constrained('facebook_pages')->nullOnDelete();
            $table->string('event_id', 128)->unique();
            $table->string('object', 32)->default('page')->index();
            $table->string('entry_id', 64)->index();
            $table->string('event_type', 48)->index();
            $table->string('signature', 128)->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->json('payload');
            $table->string('status', 32)->default('received')->index(); // received, processing, processed, failed, ignored
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['entry_id', 'event_type']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
