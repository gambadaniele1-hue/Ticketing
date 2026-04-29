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
        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->foreignId('global_user_id')->constrained('global_identities')->cascadeOnDelete(); // global_user_id [cite: 222]
            $table->string('tenant_id'); // tenant_id [cite: 223]
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->enum('state', ['pending', 'accepted', 'banned'])->default('pending'); // state [cite: 224]
            $table->timestamps();

            $table->primary(['global_user_id', 'tenant_id']); // Chiave primaria composta
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
    }
};
