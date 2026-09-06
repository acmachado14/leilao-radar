<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan', 32)->default('trial')->after('subscription_status');
        });

        DB::table('users')
            ->where('subscription_status', 'active')
            ->update(['plan' => 'radar']);

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('lote_id');
            $table->string('source', 16);
            $table->boolean('billed')->default(true);
            $table->decimal('estimated_cost_brl', 10, 4)->default(0);
            $table->unsignedSmallInteger('image_count')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'lote_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }
};
