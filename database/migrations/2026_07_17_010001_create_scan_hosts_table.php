<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45);
            $table->string('hostname', 253)->nullable();
            $table->string('status', 20)->default('unknown');
            $table->string('mac_address', 17)->nullable();
            $table->string('vendor')->nullable();
            $table->string('operating_system')->nullable();
            $table->unsignedTinyInteger('os_accuracy')->nullable();
            $table->unsignedInteger('response_time')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->unique(['scan_id', 'ip_address']);
            $table->index(['scan_id', 'status']);
            $table->index('hostname');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_hosts');
    }
};
