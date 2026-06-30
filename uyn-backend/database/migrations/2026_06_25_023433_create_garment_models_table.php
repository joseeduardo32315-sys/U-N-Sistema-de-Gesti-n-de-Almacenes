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
        Schema::create('garment_models', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();
            $table->string('name', 150);

            $table->text('description')->nullable();

            /*
            * Ejemplos
            * "2 a 8"
            * "CH, M, G"
            * "1, 2, 3, 4"
            */
            $table->string('size_range', 100)->nullable();
            
            /*
            * Solo guardamos la ruta relativa del archivo
            * Ejemplo: garment-models/abc11123.jpg
            */
            $table->string('image_path')->nullable();

            $table->string('status', 20)
                ->default('active');

            $table->timestamps();

            $table->index(['status', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garment_models');
    }
};
