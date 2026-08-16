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
        Schema::table('problems', function (Blueprint $table) {
            // checker_code: a C++ program that validates submission output.
            // Receives (input.txt, output.txt, expected.txt) and exits 0 for AC.
            // Null means use default exact-token comparison.
            $table->longText('checker_code')->nullable()->after('test_cases_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('problems', function (Blueprint $table) {
            $table->dropColumn('checker_code');
        });
    }
};
