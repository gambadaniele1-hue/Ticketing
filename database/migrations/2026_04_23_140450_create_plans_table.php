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
        Schema::create('plans', function (Blueprint $table) {
            $table->id(); // id [cite: 215]
            $table->string('name'); // name [cite: 216]
            $table->text('description')->nullable(); // description [cite: 217]
            $table->decimal('price_month', 8, 2); // price_month [cite: 218]
            $table->enum('database_type', ['shared', 'dedicated']); // database_type [cite: 219]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
