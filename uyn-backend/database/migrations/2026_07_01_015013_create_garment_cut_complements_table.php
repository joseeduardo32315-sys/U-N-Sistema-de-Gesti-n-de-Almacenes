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
        Schema::create('garment_cut_complements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('garment_cut_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('current_area_id')
                ->constrained('areas')
                ->restrictOnDelete();

            $table->string('status', 20)
                ->default('pending');

            $table->text('notes')
                ->nullable();
            
            $table->timestamps();

            $table->unique('garment_cut_id');

            $table->index([
                'current_area_id',
                'status',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garment_cut_complements');
    }
};
