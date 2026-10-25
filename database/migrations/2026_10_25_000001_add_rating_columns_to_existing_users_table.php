<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $needsCurrentRating = ! Schema::hasColumn('users', 'current_rating');
        $needsMaxRating = ! Schema::hasColumn('users', 'max_rating');

        if (! $needsCurrentRating && ! $needsMaxRating) {
            return;
        }

        Schema::table('users', function (Blueprint $table) use ($needsCurrentRating, $needsMaxRating) {
            if ($needsCurrentRating) {
                $table->integer('current_rating')->default(0)->index();
            }

            if ($needsMaxRating) {
                $table->integer('max_rating')->default(0);
            }
        });
    }

    public function down(): void
    {
        // This migration repairs databases created before these columns were
        // added to the original users migration. They may be owned by that
        // migration on newer databases, so removing them here is unsafe.
    }
};
