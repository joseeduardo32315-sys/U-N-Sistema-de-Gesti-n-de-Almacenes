<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_processes', function (Blueprint $table) {
            $table->string('payroll_calculation_type', 30)
                ->default('per_piece');

            $table->index('payroll_calculation_type');
        });
    }

    public function down(): void
    {
        Schema::table('operation_processes', function (Blueprint $table) {
            $table->dropIndex(['payroll_calculation_type']);
            $table->dropColumn('payroll_calculation_type');
        });
    }
};