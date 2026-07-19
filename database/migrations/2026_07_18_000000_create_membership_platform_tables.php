<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('monthly_credit_allowance')->default(0);
            $table->unsignedSmallInteger('history_retention_days')->default(30);
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('category', 60)->default('general');
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('credit_cost')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['category', 'sort_order']);
        });

        Schema::create('feature_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('credit_cost_override')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->timestamps();
            $table->unique(['feature_id', 'plan_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('id')->constrained('plans')->restrictOnDelete();
            $table->string('role', 20)->default('user')->after('password')->index();
            $table->string('status', 20)->default('active')->after('role')->index();
            $table->string('membership_status', 20)->default('active')->after('status');
            $table->timestamp('membership_started_at')->nullable()->after('membership_status');
            $table->timestamp('membership_expires_at')->nullable()->after('membership_started_at')->index();
        });

        Schema::create('credit_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamp('last_monthly_reset_at')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('amount');
            $table->string('type', 30);
            $table->string('feature_code', 80)->nullable();
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::table('scans', function (Blueprint $table) {
            $table->foreignId('credit_transaction_id')->nullable()->after('user_id')
                ->constrained('credit_transactions')->nullOnDelete();
            $table->unique('credit_transaction_id');
        });

        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->json('title');
            $table->json('content');
            $table->boolean('is_published')->default(false)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('faq_items', function (Blueprint $table) {
            $table->id();
            $table->json('question');
            $table->json('answer');
            $table->string('category', 80)->default('general')->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string');
            $table->boolean('is_public')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100)->index();
            $table->string('target_type', 120)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->index(['target_type', 'target_id']);
            $table->index(['created_at', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('faq_items');
        Schema::dropIfExists('content_pages');
        Schema::table('scans', function (Blueprint $table) {
            $table->dropForeign(['credit_transaction_id']);
            $table->dropUnique(['credit_transaction_id']);
            $table->dropColumn('credit_transaction_id');
        });
        Schema::dropIfExists('credit_transactions');
        Schema::dropIfExists('credit_wallets');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'role', 'status', 'membership_status', 'membership_started_at', 'membership_expires_at']);
        });
        Schema::dropIfExists('feature_plan');
        Schema::dropIfExists('features');
        Schema::dropIfExists('plans');
    }
};
