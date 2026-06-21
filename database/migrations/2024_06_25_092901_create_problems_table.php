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
        Schema::create('problems', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\App\Models\Contest::class, 'contest_id');
            $table->string('name');
            $table->text('tags')->nullable();
            $table->integer('time_limit');
            $table->integer('memory_limit');
            $table->integer('score')->nullable();
            $table->longText('description')->nullable();
            $table->text('input')->nullable();
            $table->text('output')->nullable();
            $table->string('test_cases');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('problems');
    }
};
