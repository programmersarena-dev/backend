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
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('problem_id')->constrained()->onDelete('cascade');

            $table->string('language', 20);
            $table->string('status', 30)->default('Queued');

            $table->mediumText('code');
            $table->mediumText('outputs')->nullable();
            $table->mediumText('output')->nullable();
            $table->text('error_message')->nullable();

            $table->unsignedInteger('time')->nullable()->comment('Execution time in ms');
            $table->unsignedInteger('memory')->nullable()->comment('Memory usage in KB');

            $table->timestamp('judged_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['user_id', 'problem_id', 'status']);
            $table->index(['problem_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
