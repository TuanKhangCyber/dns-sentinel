<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recon_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('target', 253);
            $table->string('status', 20)->default('running');
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->json('results')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recon_histories');
    }
};
