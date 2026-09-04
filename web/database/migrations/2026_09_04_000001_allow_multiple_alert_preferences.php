<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alert_preferences', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
            $table->string('name')->default('')->after('user_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('alert_preferences', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('name');
            $table->unique('user_id');
        });
    }
};
