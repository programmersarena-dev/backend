<?php

use App\Models\Problem;
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
        Schema::create('problem_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Problem::class, 'problem_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('language', 10);
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('input')->nullable();
            $table->text('output')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['problem_id', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('problem_translations');
    }
};
