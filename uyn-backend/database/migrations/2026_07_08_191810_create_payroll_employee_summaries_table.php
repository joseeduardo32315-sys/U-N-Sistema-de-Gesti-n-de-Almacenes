<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'payroll_employee_summaries',
            function (Blueprint $table) {
                $table->id();

                $table->foreignId('payroll_period_id')
                    ->constrained()
                    ->restrictOnDelete();

                $table->foreignId('employee_id')
                    ->constrained()
                    ->restrictOnDelete();

                /*
                 * piecework | fixed | mixed
                 */
                $table->string('payment_type', 20);

                $table->decimal('piecework_amount', 14, 2)
                    ->default(0);

                $table->decimal('fixed_amount', 14, 2)
                    ->default(0);

                $table->decimal('total_amount', 14, 2)
                    ->default(0);

                $table->unsignedInteger('details_count')
                    ->default(0);

                /*
                 * generated | reviewed | paid
                 */
                $table->string('status', 20)
                    ->default('generated');

                /*
                 * Resumen de las fuentes y reglas aplicadas.
                 */
                $table->json('calculation_snapshot')
                    ->nullable();

                $table->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'payroll_period_id',
                    'employee_id',
                ]);

                $table->index('employee_id');

                $table->index([
                    'payment_type',
                    'status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_employee_summaries');
    }
};