<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']); // Chiave primaria composta

            if (DB::connection()->getDatabaseName() == env('SHARED_DB_NAME', 'ticketing_shared')) {
                $table->string('tenant_id')->nullable(); // Per identificare a quale tenant appartiene l'associazione
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};