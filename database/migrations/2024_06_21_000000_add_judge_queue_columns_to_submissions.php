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
        Schema::table('submissions', function (Blueprint $table) {
            // Add columns for new judge-box integration
            if (!Schema::hasColumn('submissions', 'status')) {
                $table->string('status')->default('Queued')->comment('Queued, Judging, Accepted, Wrong Answer, etc.');
            }
            if (!Schema::hasColumn('submissions', 'output')) {
                $table->longText('output')->nullable()->comment('Raw output from submission');
            }
            if (!Schema::hasColumn('submissions', 'time')) {
                $table->integer('time')->nullable()->comment('Execution time in milliseconds');
            }
            if (!Schema::hasColumn('submissions', 'memory')) {
                $table->integer('memory')->nullable()->comment('Memory usage in KB');
            }
            if (!Schema::hasColumn('submissions', 'error_message')) {
                $table->longText('error_message')->nullable()->comment('Error or exception message');
            }
            if (!Schema::hasColumn('submissions', 'judged_at')) {
                $table->timestamp('judged_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumnIfExists('status');
            $table->dropColumnIfExists('output');
            $table->dropColumnIfExists('time');
            $table->dropColumnIfExists('memory');
            $table->dropColumnIfExists('error_message');
            $table->dropColumnIfExists('judged_at');
        });
    }
};
