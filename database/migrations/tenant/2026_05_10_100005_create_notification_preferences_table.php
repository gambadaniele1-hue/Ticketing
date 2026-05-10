<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('channel', ['app', 'email']);
            $table->string('event_type');
            $table->boolean('enabled')->default(true);

            $table->primary(['user_id', 'channel', 'event_type']);

            if (DB::connection()->getDatabaseName() == env('SHARED_DB_NAME', 'ticketing_shared')) {
                $table->string('tenant_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
