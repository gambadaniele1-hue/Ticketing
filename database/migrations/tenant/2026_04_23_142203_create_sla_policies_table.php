<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // create_sla_policies_table
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('priority'); // Valore numerico per calcoli
            $table->integer('response_time_hours');
            $table->integer('resolution_time_hours');
            $table->timestamps();

            if (DB::connection()->getDatabaseName() == env('SHARED_DB_NAME', 'ticketing_shared')) {
                $table->string('tenant_id')->nullable(); // Per identificare a quale tenant appartiene la SLA policy
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_policies');
    }
};
