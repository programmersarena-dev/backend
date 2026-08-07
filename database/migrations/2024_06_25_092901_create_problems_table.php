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

            $table->foreignId('contest_id')
                  ->nullable()
                  ->constrained('contests')
                  ->nullOnDelete();

            $table->string('code', 32)->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('name');

            $table->unsignedSmallInteger('time_limit')->default(1000)->comment('In milliseconds');
            $table->unsignedSmallInteger('memory_limit')->default(256)->comment('In megabytes');

            $table->unsignedSmallInteger('difficulty')->nullable()->index();
            $table->unsignedSmallInteger('score')->default(100);

            $table->json('tags')->nullable();

            $table->text('description')->nullable();
            $table->text('input')->nullable();
            $table->text('output')->nullable();
            $table->text('note')->nullable();

            $table->string('test_cases_path')->nullable();

            $table->boolean('is_public')->default(false)->index();

            $table->timestamps();
            $table->softDeletes();
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
