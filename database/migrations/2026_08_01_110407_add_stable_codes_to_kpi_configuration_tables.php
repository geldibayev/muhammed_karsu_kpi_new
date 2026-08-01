<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('formulas', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->unique('code', 'formulas_code_uq');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->date('starts_on')->nullable()->after('desc');
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->unique('code', 'reports_code_uq');
        });

        Schema::table('criteria', function (Blueprint $table) {
            $table->string('code')->nullable()->after('id');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('parent_id');
            $table->unique(['report_id', 'code'], 'criteria_report_code_uq');
            $table->index(['parent_id', 'sort_order'], 'criteria_parent_sort_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('criteria', function (Blueprint $table) {
            $table->dropUnique('criteria_report_code_uq');
            $table->dropIndex('criteria_parent_sort_idx');
            $table->dropColumn(['code', 'sort_order']);
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->dropUnique('reports_code_uq');
            $table->dropColumn(['code', 'starts_on', 'ends_on']);
        });

        Schema::table('formulas', function (Blueprint $table) {
            $table->dropUnique('formulas_code_uq');
            $table->dropColumn('code');
        });
    }
};
