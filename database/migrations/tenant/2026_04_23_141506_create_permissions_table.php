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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug'); // es. 'tickets.create'
            $table->text('description')->nullable();
            $table->timestamps();

            if (DB::connection()->getDatabaseName() == env('SHARED_DB_NAME', 'ticketing_shared')) {
                $table->string('tenant_id')->nullable(); // Per identificare a quale tenant appartiene il permesso
                $table->unique(['tenant_id', 'slug']);
            } else {
                $table->unique('slug');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
