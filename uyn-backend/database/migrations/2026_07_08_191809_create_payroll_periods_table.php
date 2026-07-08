<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)
                ->unique();

            /*
             * weekly | biweekly | monthly
             */
            $table->string('frequency', 20);

            $table->date('start_date');
            $table->date('end_date');

            $table->date('payment_date')
                ->nullable();

            /*
             * draft | generated | closed | cancelled
             */
            $table->string('status', 20)
                ->default('draft');

            $table->text('notes')
                ->nullable();

            $table->timestamp('generated_at')
                ->nullable();

            $table->timestamp('closed_at')
                ->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('generated_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index([
                'frequency',
                'status',
            ]);

            $table->index([
                'start_date',
                'end_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};