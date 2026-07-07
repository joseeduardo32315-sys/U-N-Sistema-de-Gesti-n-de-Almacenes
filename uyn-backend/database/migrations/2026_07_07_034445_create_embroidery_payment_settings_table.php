<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embroidery_payment_settings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('operation_process_id')
                ->constrained()
                ->restrictOnDelete();

            $table->decimal('stitch_price', 14, 8);

            $table->decimal('application_price', 12, 4);

            /*
             * Ejemplo: 0.300000 representa 30%.
             */
            $table->decimal('payment_percentage', 8, 6);

            $table->decimal('minimum_payment_per_piece', 12, 4);

            $table->decimal('default_payment_per_piece', 12, 4);

            $table->date('effective_from');

            $table->date('effective_to')
                ->nullable();

            $table->string('status', 20)
                ->default('active');

            $table->text('notes')
                ->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'operation_process_id',
                'status',
            ]);

            $table->index([
                'effective_from',
                'effective_to',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embroidery_payment_settings');
    }
};