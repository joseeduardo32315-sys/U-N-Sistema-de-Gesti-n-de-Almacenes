<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_incidents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('garment_cut_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('production_movement_id')
                ->constrained()
                ->restrictOnDelete();

            /*
             * damage, loss, quality, delay, other
             */
            $table->string('incident_type', 30);

            /*
             * Para retrasos puede ser cero.
             */
            $table->unsignedInteger('quantity_affected')
                ->default(0);

            $table->text('description');

            $table->foreignId('responsible_employee_id')
                ->nullable()
                ->constrained('employees')
                ->restrictOnDelete();

            /*
             * open, resolved, cancelled
             */
            $table->string('status', 30)
                ->default('open');

            $table->timestamp('resolved_at')
                ->nullable();

            $table->foreignId('resolved_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('notes')
                ->nullable();

            $table->timestamps();

            $table->index([
                'garment_cut_id',
                'status',
            ]);

            $table->index([
                'production_movement_id',
                'status',
            ]);

            $table->index([
                'incident_type',
                'status',
            ]);

            $table->index([
                'responsible_employee_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_incidents');
    }
};