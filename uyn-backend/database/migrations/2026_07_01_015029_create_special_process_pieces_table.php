<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_process_pieces', function (Blueprint $table) {
            $table->id();

            $table->foreignId('garment_cut_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('piece_type_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * Proceso especial requerido por esta pieza.
             * Ejemplo: Bordado.
             */
            $table->foreignId('process_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * Área donde se encuentra actualmente la pieza.
             * Al clasificarla, inicia en Diseño.
             */
            $table->foreignId('current_area_id')
                ->constrained('areas')
                ->restrictOnDelete();

            $table->string('status', 30)
                ->default('pending');

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            /*
             * Una pieza física del corte solo puede tener una
             * ruta especial activa por tipo de pieza.
             */
            $table->unique([
                'garment_cut_id',
                'piece_type_id',
            ]);

            $table->index([
                'process_id',
                'status',
            ]);

            $table->index([
                'current_area_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_process_pieces');
    }
};