<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audio_clips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('storage_path');
            $table->unsignedSmallInteger('duration_sec')->nullable();
            $table->string('codec', 32)->nullable();
            $table->unsignedInteger('sample_rate')->nullable();
            $table->enum('status', ['uploaded', 'transcribed', 'failed'])->default('uploaded');
            $table->longText('transcript_text')->nullable();
            $table->string('asr_provider', 32)->nullable();
            $table->decimal('asr_confidence', 5, 4)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        if ($this->supportsFullText()) {
            DB::statement('ALTER TABLE audio_clips ADD FULLTEXT INDEX ft_transcript_text (transcript_text)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->supportsFullText()) {
            DB::statement('ALTER TABLE audio_clips DROP INDEX ft_transcript_text');
        }

        Schema::dropIfExists('audio_clips');
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
