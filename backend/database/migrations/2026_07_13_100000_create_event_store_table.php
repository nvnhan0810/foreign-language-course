<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_store', function (Blueprint $table) {
            $table->id();
            $table->string('aggregate_type', 120);
            $table->string('aggregate_id', 120);
            $table->unsignedInteger('playhead');
            $table->string('event_type', 180);
            $table->jsonb('payload');
            $table->jsonb('metadata')->nullable();
            $table->timestamp('recorded_at')->useCurrent();

            $table->unique(['aggregate_type', 'aggregate_id', 'playhead']);
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_store');
    }
};
