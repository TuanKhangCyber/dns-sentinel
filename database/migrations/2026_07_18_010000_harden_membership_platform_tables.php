<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('faq_items') && ! Schema::hasColumn('faq_items', 'code')) {
            Schema::table('faq_items', function (Blueprint $table) {
                $table->string('code', 100)->nullable()->unique()->after('id');
            });

            foreach ([1 => ['default_faq_1', 'membership'], 2 => ['default_faq_2', 'privacy'], 3 => ['default_faq_3', 'privacy'], 4 => ['default_faq_4', 'privacy']] as $id => [$code, $category]) {
                DB::table('faq_items')->where('id', $id)->where('category', $category)->whereNull('code')->update(['code' => $code]);
            }
        }

        if (Schema::hasTable('credit_transactions')) {
            Schema::table('credit_transactions', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('credit_transactions')) {
            Schema::table('credit_transactions', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('faq_items') && Schema::hasColumn('faq_items', 'code')) {
            Schema::table('faq_items', function (Blueprint $table) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }
};
