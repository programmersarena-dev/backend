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
            $table->foreignIdFor(\App\Models\ContestType::class, 'type_id')->default(1);
            $table->string('name');
            $table->text('authorIds')->nullable();
            $table->timestamp('start_date');
            $table->time('duration');
            $table->longText('participantIds')->nullable();
            $table->boolean('official')->default(0);
            $table->boolean('active')->default(0);
            $table->timestamps();
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
