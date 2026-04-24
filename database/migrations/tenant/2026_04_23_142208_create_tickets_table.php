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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('status'); // es: open, in_progress, resolved, closed
            $table->string('priority'); // es: low, medium, high, urgent
            $table->foreignId('user_id_author')->constrained('users');
            $table->foreignId('user_id_resolver')->nullable()->constrained('users');
            $table->foreignId('team_id')->nullable()->constrained('teams');
            $table->foreignId('category_id')->nullable()->constrained('categories');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            if (DB::connection()->getDatabaseName() == env('SHARED_DB_NAME', 'ticketing_shared')) {
                $table->string('tenant_id')->nullable(); // Per identificare a quale tenant appartiene il ticket
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
