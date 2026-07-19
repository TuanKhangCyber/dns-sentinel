<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target', 253);
            $table->string('target_type', 20);
            $table->string('profile', 50);
            $table->string('status', 40)->default('queued');
            $table->string('stage', 60)->default('queued');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('scanner_type', 30)->default('nmap');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('authorization_confirmed_at');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error_message')->nullable();
            $table->json('options')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['target', 'profile']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
