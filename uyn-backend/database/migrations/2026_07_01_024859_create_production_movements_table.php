<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('garment_cut_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * Valores permitidos desde aplicación:
             * cut, complement, special_piece
             */
            $table->string('target_type', 30);

            /*
             * Solo uno de estos dos campos puede tener valor,
             * según el tipo de destino del movimiento.
             */
            $table->foreignId('special_process_piece_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('complement_id')
                ->nullable()
                ->constrained('garment_cut_complements')
                ->restrictOnDelete();

            /*
             * Proceso y operación que se ejecutarán después
             * de que el movimiento sea recibido.
             */
            $table->foreignId('process_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('operation_process_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('from_area_id')
                ->constrained('areas')
                ->restrictOnDelete();

            $table->foreignId('to_area_id')
                ->constrained('areas')
                ->restrictOnDelete();

            $table->unsignedInteger('quantity');

            /*
             * pending
             * received
             * in_progress
             * partially_completed
             * completed
             * cancelled
             * with_incident
             * delayed
             */
            $table->string('status', 30)
                ->default('pending');

            $table->timestamp('start_time')
                ->nullable();

            $table->timestamp('end_time')
                ->nullable();

            $table->text('notes')
                ->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'garment_cut_id',
                'status',
            ]);

            $table->index([
                'target_type',
                'status',
            ]);

            $table->index([
                'from_area_id',
                'status',
            ]);

            $table->index([
                'to_area_id',
                'status',
            ]);

            $table->index([
                'process_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_movements');
    }
};