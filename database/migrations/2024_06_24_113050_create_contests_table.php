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
        Schema::create('contests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('type_id')
                ->nullable()
                ->constrained('contest_types')
                ->onDelete('restrict');

            $table->string('name');
            $table->timestamp('start_date');

            $table->unsignedInteger('duration_minutes')->comment('Duration of the contest in minutes');

            $table->boolean('official')->default(false);
            $table->boolean('active')->default(false);

            $table->timestamps();

            $table->index(['active', 'start_date']);
            $table->index(['official', 'start_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contests');
    }
};
