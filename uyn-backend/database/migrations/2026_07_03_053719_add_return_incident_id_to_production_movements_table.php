<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_movements', function (Blueprint $table) {
            $table->foreignId('return_incident_id')
                ->nullable()
                ->after('garment_cut_id')
                ->constrained('production_incidents')
                ->restrictOnDelete()
                ->unique();
        });
    }

    public function down(): void
    {
        Schema::table('production_movements', function (Blueprint $table) {
            $table->dropForeign(['return_incident_id']);
            $table->dropUnique(['return_incident_id']);
            $table->dropColumn('return_incident_id');
        });
    }
};