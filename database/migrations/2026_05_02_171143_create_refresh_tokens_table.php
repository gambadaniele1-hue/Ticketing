<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // NOTA: Questa tabella va nel database centrale
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();

            // Chiave esterna verso l'utente globale (Se cancelli l'utente, cancelli i suoi token)
            $table->foreignId('global_identity_id')
                ->constrained('global_identities')
                ->cascadeOnDelete();

            // Il token vero e proprio (64 caratteri sono standard per i refresh token)
            $table->string('token', 64)->unique();

            // Quando scade questo token?
            $table->timestamp('expires_at');

            // Interruttore per bannare/disconnettere forzatamente un utente
            $table->boolean('revoked')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};