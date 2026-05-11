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
        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->time('started_at');
            $table->time('ended_at')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('in_progress');
            $table->timestamps();

            $table->index(['work_date', 'client_id']);
            $table->index(['status', 'work_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
