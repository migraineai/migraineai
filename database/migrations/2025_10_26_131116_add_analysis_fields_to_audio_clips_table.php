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
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->json('structured_payload')->nullable()->after('transcript_text');
            $table->text('analysis_error')->nullable()->after('structured_payload');
            $table->timestamp('processed_at')->nullable()->after('analysis_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audio_clips', function (Blueprint $table) {
            $table->dropColumn(['structured_payload', 'analysis_error', 'processed_at']);
        });
    }
};
