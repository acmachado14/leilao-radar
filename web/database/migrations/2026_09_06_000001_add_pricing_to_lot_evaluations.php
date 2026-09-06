<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_evaluations', function (Blueprint $table) {
            $table->decimal('max_bid_amount', 12, 2)->nullable()->after('patio_checks');
            $table->decimal('estimated_resale', 12, 2)->nullable()->after('max_bid_amount');
            $table->decimal('estimated_costs', 12, 2)->nullable()->after('estimated_resale');
            $table->decimal('target_profit', 12, 2)->nullable()->after('estimated_costs');
            $table->text('pricing_rationale')->nullable()->after('target_profit');
        });
    }

    public function down(): void
    {
        Schema::table('lot_evaluations', function (Blueprint $table) {
            $table->dropColumn([
                'max_bid_amount',
                'estimated_resale',
                'estimated_costs',
                'target_profit',
                'pricing_rationale',
            ]);
        });
    }
};
