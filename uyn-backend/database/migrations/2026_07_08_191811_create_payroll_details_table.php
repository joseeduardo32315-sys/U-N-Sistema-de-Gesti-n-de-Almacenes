<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('payroll_employee_summary_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * operation_log | fixed_compensation
             */
            $table->string('source_type', 30);

            /*
             * Solo se usa para pagos por destajo provenientes
             * de production_operation_logs.
             */
            $table->foreignId('production_operation_log_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            /*
             * Se utilizará para pagos fijos en la siguiente fase.
             */
            $table->foreignId('employee_compensation_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            $table->string('description', 300);

            $table->unsignedInteger('quantity')
                ->nullable();

            $table->decimal('unit_amount', 14, 4)
                ->nullable();

            $table->decimal('amount', 14, 2);

            $table->timestamp('occurred_at')
                ->nullable();

            /*
             * Copia del payout_snapshot o del cálculo de salario fijo.
             */
            $table->json('calculation_snapshot')
                ->nullable();

            $table->timestamps();

            /*
             * Impide que una misma operación completada
             * se integre en más de una nómina.
             */
            $table->unique('production_operation_log_id');

            $table->index([
                'source_type',
                'occurred_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_details');
    }
};