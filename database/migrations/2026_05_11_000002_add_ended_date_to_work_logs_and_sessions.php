<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->date('ended_date')->nullable()->after('ended_at');
        });

        Schema::table('work_log_sessions', function (Blueprint $table) {
            $table->date('ended_date')->nullable()->after('ended_at');
        });

        DB::table('work_logs')
            ->whereNotNull('ended_at')
            ->whereNull('ended_date')
            ->update(['ended_date' => DB::raw('date(work_date)')]);

        DB::table('work_log_sessions')
            ->whereNotNull('ended_at')
            ->whereNull('ended_date')
            ->update(['ended_date' => DB::raw('date(work_date)')]);
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn('ended_date');
        });

        Schema::table('work_log_sessions', function (Blueprint $table) {
            $table->dropColumn('ended_date');
        });
    }
};
