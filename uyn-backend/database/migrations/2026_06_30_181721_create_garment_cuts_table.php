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
        Schema::create('garment_cuts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('production_order_id')
                ->constrained()
                ->restrictOnDelete();

            /**
             * Relación agregada para conectar el catálogo
             * garment_models con los cortes reales
             */
            $table->foreignId('garment_model_id')
                ->constrained()
                ->restrictOnDelete();

            /**
             * Folio único del corte.
             * Ejemplo: CUT-PM23-001
             */
            $table->string('code', 50)
                ->unique();

            $table->text('description')
                ->nullable();

            /**
             * Campos calculados desde garment_cuts_sizes.
             */
            $table->unsignedSmallInteger('total_sizes');

            /**
             * Se guarda únicamente cuando todas las tallas
             * tienen la misma cantidad de piezas.
             */
            $table->unsignedInteger('base_pieces_per_size')
                ->nullable();
            
            $table->unsignedInteger('total_pieces');

            $table->string('status', 30)
                ->default('registered');

            /**
             * Al crear un corte, su área actual sera Corte.
             */
            $table->foreignId('current_area_id')
                ->constrained('areas')
                ->restrictOnDelete();

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            $table->index(['production_order_id', 'status']);
            $table->index(['current_area_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garment_cuts');
    }
};
