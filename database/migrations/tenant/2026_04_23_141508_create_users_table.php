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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('global_user_id')->index(); // Rif. al DB Globale
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            if (DB::connection()->getDatabaseName() == env('SHARED_DB_NAME', 'ticketing_shared')) {
                $table->string('tenant_id')->nullable(); // Per identificare a quale tenant appartiene l'utente
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
