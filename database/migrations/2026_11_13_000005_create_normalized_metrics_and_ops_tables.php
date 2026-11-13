<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metric_definitions', function (Blueprint $table) {
            $table->string('key', 64)->primary(); // impressions, reach, followers, engagements, video_views, watch_time
            $table->string('label');
            $table->string('unit', 24)->default('count'); // count, seconds, percent
            $table->string('aggregation', 16)->default('sum'); // sum, last, avg
            $table->timestamps();
        });

        Schema::create('metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
            $table->string('metric_key', 64);
            $table->date('date')->index();
            $table->decimal('value', 16, 2)->default(0);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'metric_key', 'date'], 'uniq_account_metric_date');
            $table->index(['social_account_id', 'date']);
        });

        Schema::create('post_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_target_id')->constrained('post_targets')->cascadeOnDelete();
            $table->string('metric_key', 64);
            $table->timestamp('captured_at');
            $table->decimal('value', 16, 2)->default(0);
            $table->timestamps();

            $table->index(['post_target_id', 'metric_key', 'captured_at'], 'idx_post_metric_captured');
        });

        Schema::create('quota_usage', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32);
            $table->string('scope_key', 128);
            $table->date('date');
            $table->unsignedBigInteger('units_used')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'scope_key', 'date']);
        });

        Schema::create('api_call_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->index();
            $table->foreignId('social_account_id')->nullable()->constrained('social_accounts')->nullOnDelete();
            $table->string('endpoint', 512);
            $table->string('method', 12);
            $table->unsignedSmallInteger('status')->nullable()->index();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('request_id', 128)->nullable();
            $table->json('rate_usage')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_call_logs');
        Schema::dropIfExists('quota_usage');
        Schema::dropIfExists('post_metric_snapshots');
        Schema::dropIfExists('metric_snapshots');
        Schema::dropIfExists('metric_definitions');
    }
};
