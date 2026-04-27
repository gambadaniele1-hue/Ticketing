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
            $table->text('description')->nullable(); // description [cite: 210]
            $table->string('db_name')->nullable(); // db_name [cite: 211]
            $table->string('db_password')->nullable(); // db_password [cite: 212]
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete(); // plan_id [cite: 213]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
