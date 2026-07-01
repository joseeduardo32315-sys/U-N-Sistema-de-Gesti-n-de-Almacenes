<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piece_types', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100)->unique();

            $table->string('description', 255)
                ->nullable();

            $table->string('status', 20)
                ->default('active');

            $table->timestamps();

            $table->index(['status', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_types');
    }
};