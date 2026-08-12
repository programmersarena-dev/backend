<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contest_ratings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('contest_id')->constrained('contests')->cascadeOnDelete();

            $table->unsignedInteger('rank');
            $table->unsignedInteger('solved')->default(0);

            $table->integer('old_rating');
            $table->integer('new_rating');

            $table->timestamps();

            $table->unique(['user_id', 'contest_id']);

            $table->index(['contest_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contest_ratings');
    }
};
