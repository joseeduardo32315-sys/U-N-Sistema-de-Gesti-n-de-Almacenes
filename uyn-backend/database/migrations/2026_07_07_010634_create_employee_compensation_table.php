<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_compensations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * piecework | fixed
             */
            $table->string('payment_type', 20);

            /*
             * weekly | biweekly | monthly
             * Solo aplica para payment_type = fixed.
             */
            $table->string('payment_frequency', 20)
                ->nullable();

            /*
             * Monto fijo por periodo.
             * Solo aplica para payment_type = fixed.
             */
            $table->decimal('fixed_amount', 12, 2)
                ->nullable();

            $table->date('effective_from');

            $table->date('effective_to')
                ->nullable();

            $table->string('status', 20)
                ->default('active');

            $table->text('notes')
                ->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'employee_id',
                'status',
            ]);

            $table->index([
                'payment_type',
                'status',
            ]);

            $table->index([
                'effective_from',
                'effective_to',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_compensations');
    }
};