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
        Schema::create('problem_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Problem::class, 'problem_id');
            $table->string('language');
            $table->string('name');
            $table->longText('description')->nullable();
            $table->longText('input')->nullable();
            $table->longText('output')->nullable();
            $table->longText('note')->nullable();
            $table->timestamps();
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
