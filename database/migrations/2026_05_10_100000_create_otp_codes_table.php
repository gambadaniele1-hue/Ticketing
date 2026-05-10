<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('global_identity_id')->constrained('global_identities')->cascadeOnDelete();
            $table->string('code');
            $table->timestamp('expires_at');
            $table->boolean('used')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
