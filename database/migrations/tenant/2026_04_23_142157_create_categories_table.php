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
        // create_categories_table
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();

            if (DB::connection()->getDatabaseName() == env('SHARED_DB_NAME', 'ticketing_shared')) {
                $table->string('tenant_id')->nullable(); // Per identificare a quale tenant appartiene la categoria
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
