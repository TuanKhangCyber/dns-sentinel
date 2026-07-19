<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scan_host_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('scan_port_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plugin_id')->nullable();
            $table->string('title');
            $table->string('severity', 20)->default('informational');
            $table->decimal('cvss_score', 3, 1)->nullable();
            $table->string('cvss_vector')->nullable();
            $table->json('cve')->nullable();
            $table->text('description')->nullable();
            $table->text('evidence')->nullable();
            $table->text('solution')->nullable();
            $table->json('references')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();

            $table->index(['scan_id', 'severity']);
            $table->index(['scan_id', 'status']);
            $table->index(['plugin_id', 'severity']);
            $table->index('cvss_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_findings');
    }
};
