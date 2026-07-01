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
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_code', 50)->unique();
            
            $table->string('location', 150)
                ->nullable();

            $table->string('status', 30)
                ->default('registered');

            $table->date('start_date');

            /**
             * Fecha estimada de terminación o entrega.
            */
            $table->date('end_date')
                ->nullable();

            $table->string('priority', 20)
                ->default('normal');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            $table->index(['status', 'priority']);
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
