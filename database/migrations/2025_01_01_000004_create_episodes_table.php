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
        Schema::create('episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audio_clip_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->unsignedTinyInteger('intensity')->nullable();
            $table->enum('pain_location', ['left', 'right', 'bilateral', 'frontal', 'occipital', 'other'])->nullable();
            $table->boolean('aura')->nullable();
            $table->json('symptoms')->nullable();
            $table->json('triggers')->nullable();
            $table->text('what_you_tried')->nullable();
            $table->text('notes')->nullable();
            $table->json('extraction_confidences')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_time']);
        });

        if ($this->supportsFullText()) {
            DB::statement('ALTER TABLE episodes ADD FULLTEXT INDEX ft_episode_notes (what_you_tried, notes)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->supportsFullText()) {
            DB::statement('ALTER TABLE episodes DROP INDEX ft_episode_notes');
        }

        Schema::dropIfExists('episodes');
    }

    private function supportsFullText(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
