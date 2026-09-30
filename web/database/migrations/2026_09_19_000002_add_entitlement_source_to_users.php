<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('entitlement_source', 16)->nullable()->after('plan');
            $table->string('revenuecat_app_user_id')->nullable()->after('entitlement_source');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['entitlement_source', 'revenuecat_app_user_id']);
        });
    }
};
