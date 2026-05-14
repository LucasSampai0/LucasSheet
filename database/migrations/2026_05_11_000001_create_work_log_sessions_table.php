<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_log_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_log_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->time('started_at');
            $table->time('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->timestamps();

            $table->index(['work_log_id', 'started_at']);
            $table->index(['work_date', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_log_sessions');
    }
};
