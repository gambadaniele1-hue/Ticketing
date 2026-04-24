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
        Schema::create('global_identities', function (Blueprint $table) {
            $table->id(); // id [cite: 198]
            $table->string('name'); // name [cite: 199]
            $table->string('email')->unique(); // email [cite: 200]
            $table->string('password'); // password [cite: 201]
            $table->timestamp('email_verified_at')->nullable(); // email_verifid_at [cite: 202]
            $table->timestamps(); // created_at, updated_at [cite: 203, 204]
            $table->softDeletes(); // deleted_at [cite: 205]
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_identities');
    }
};
