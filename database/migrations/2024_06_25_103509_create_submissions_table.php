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

            $table->foreignId('user_id')->constrained()->onDelete('restrict');
            $table->foreignId('problem_id')->constrained()->onDelete('restrict');
            $table->foreignId('contest_id')->nullable()->constrained()->nullOnDelete();

            $table->string('language', 20);
            $table->string('status', 30)->default('Queued');

            $table->mediumText('code');
            $table->json('outputs')->nullable()->comment('Per-test-case outputs');
            $table->mediumText('output')->nullable()->comment('Final combined/summary output');
            $table->text('error_message')->nullable();

            $table->unsignedInteger('time')->nullable()->comment('Execution time in ms');
            $table->unsignedInteger('memory')->nullable()->comment('Memory usage in KB');

            $table->timestamp('judged_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['status', 'created_at']);
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
