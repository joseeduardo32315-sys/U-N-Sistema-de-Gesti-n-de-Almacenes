<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_processes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('process_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('name', 100);

            /*
             * Orden interno de la operación dentro de su proceso.
             */
            $table->unsignedSmallInteger('flow_order');

            $table->unique(['process_id', 'name']);

            $table->unique([
                'process_id',
                'flow_order',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_processes');
    }
};