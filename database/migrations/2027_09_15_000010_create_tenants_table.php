<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary(); // id [cite: 208]
            $table->string('name'); // name [cite: 209]
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete(); // plan_id [cite: 213]
            
            $table->json('data')->nullable(); // Qui finiranno db_name, user, password ecc.

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
