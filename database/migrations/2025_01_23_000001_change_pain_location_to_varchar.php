<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            // Change pain_location from enum to varchar
            $table->string('pain_location', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            // Revert back to enum
            $table->enum('pain_location', ['left', 'right', 'bilateral', 'frontal', 'occipital', 'other'])->nullable()->change();
        });
    }
};
