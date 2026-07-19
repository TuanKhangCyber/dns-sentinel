<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_host_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('port');
            $table->string('protocol', 10);
            $table->string('state', 20);
            $table->string('service')->nullable();
            $table->string('product')->nullable();
            $table->string('version')->nullable();
            $table->string('extra_info')->nullable();
            $table->string('cpe')->nullable();
            $table->text('banner')->nullable();
            $table->timestamps();

            $table->unique(['scan_host_id', 'protocol', 'port']);
            $table->index(['state', 'protocol']);
            $table->index('service');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_ports');
    }
};
