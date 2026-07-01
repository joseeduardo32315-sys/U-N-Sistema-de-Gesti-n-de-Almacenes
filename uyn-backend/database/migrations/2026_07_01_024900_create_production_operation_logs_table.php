<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_operation_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('production_movement_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('operation_process_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('employee_id')
                ->constrained()
                ->restrictOnDelete();

            $table->timestamp('start_time')
                ->nullable();

            $table->timestamp('end_time')
                ->nullable();

            /*
             * Estos datos se utilizarán cuando correspondan,
             * principalmente en bordado y operaciones específicas.
             */
            $table->unsignedInteger('stitches_count')
                ->nullable();

            $table->unsignedInteger('applications_count')
                ->nullable();

            $table->unsignedInteger('quantity_processed')
                ->default(0);

            /*
             * pending
             * in_progress
             * completed
             * cancelled
             * with_incident
             */
            $table->string('status', 30)
                ->default('pending');

            $table->text('notes')
                ->nullable();

            /*
             * Importe calculado posteriormente a partir de
             * la tarifa de destajo correspondiente.
             */
            $table->decimal('payout_amount', 12, 2)
                ->nullable();

            $table->timestamps();

            $table->index([
                'production_movement_id',
                'status',
            ]);

            $table->index([
                'operation_process_id',
                'status',
            ]);

            $table->index([
                'employee_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_operation_logs');
    }
};