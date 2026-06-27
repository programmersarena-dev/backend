<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contest_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contest_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->boolean('is_official')->default(false);
            $table->foreignId('opponent_id')->nullable()->constrained('users')->onDelete('set null');

            $table->integer('old_rating')->nullable();
            $table->integer('rating_change')->nullable();

            $table->timestamps();

            $table->unique(['contest_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contest_user');
    }
};
