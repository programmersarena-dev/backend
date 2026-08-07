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
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            $table->string('handle', 32)->unique()->comment('Unique username for profile URLs & logins');
            $table->string('name', 64)->comment('Display or Full Name shown on leaderboards');
            $table->string('email', 254)->unique();

            $table->string('user_type', 16)->default('user')->comment('user, admin, judge-daemon');

            $table->string('locale', 5)->default('tk')->comment('Preferred translation UI language code');

            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->dateTime('last_activity')->nullable();
            $table->timestamps();

            $table->index('user_type');
            $table->index('last_activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
