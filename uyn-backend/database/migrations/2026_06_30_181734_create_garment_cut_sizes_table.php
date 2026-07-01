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
        Schema::create('garment_cut_sizes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('garment_cut_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('size_id')
                ->constrained()
                ->restrictOnDelete();

            $table->unsignedInteger('total_pieces');

            $table->timestamps();

            $table->unique([
                'garment_cut_id',
                'size_id',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('garment_cut_sizes');
    }
};
