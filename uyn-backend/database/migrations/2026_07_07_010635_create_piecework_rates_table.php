<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piecework_rates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('operation_process_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * Importe pagado por cada pieza completada.
             */
            $table->decimal('amount_per_piece', 12, 4);

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
                'operation_process_id',
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
        Schema::dropIfExists('piecework_rates');
    }
};