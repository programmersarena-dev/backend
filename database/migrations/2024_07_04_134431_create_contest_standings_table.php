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
        Schema::create('contest_standings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contest_id')
                ->unique()
                ->constrained('contests')
                ->cascadeOnDelete();

            $table->jsonb('result')->nullable();

            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_standings');
    }
};
