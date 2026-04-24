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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->text('body');
            $table->boolean('is_internal')->default(false); // Messaggi solo per operatori
            $table->unsignedBigInteger('macro_id')->nullable(); // Per risposte predefinite
            $table->timestamps();

            if (DB::connection()->getDatabaseName() == env('SHARED_DB_NAME', 'ticketing_shared')) {
                $table->string('tenant_id')->nullable(); // Per identificare a quale tenant appartiene il messaggio
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
