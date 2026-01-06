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
            $table->boolean('daily_reminder_enabled')->default(true)->after('last_period_start_date');
            $table->unsignedTinyInteger('daily_reminder_hour')->default(9)->after('daily_reminder_enabled');
            $table->unsignedTinyInteger('post_attack_follow_up_hours')->default(12)->after('daily_reminder_hour');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'daily_reminder_enabled',
                'daily_reminder_hour',
                'post_attack_follow_up_hours',
            ]);
        });
    }
};

