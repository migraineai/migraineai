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
        Schema::table('users', function (Blueprint $table) {
            $table->string('age_range', 32)->nullable()->after('age');
            $table->string('time_zone', 64)->nullable()->after('age_range');
            $table->boolean('cycle_tracking_enabled')->default(false)->after('time_zone');
            $table->unsignedTinyInteger('cycle_length_days')->nullable()->after('cycle_tracking_enabled');
            $table->unsignedTinyInteger('period_length_days')->nullable()->after('cycle_length_days');
            $table->date('last_period_start_date')->nullable()->after('period_length_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'age_range',
                'time_zone',
                'cycle_tracking_enabled',
                'cycle_length_days',
                'period_length_days',
                'last_period_start_date',
            ]);
        });
    }
};
