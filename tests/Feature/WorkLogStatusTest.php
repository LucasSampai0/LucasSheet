<?php

namespace Tests\Feature;

use App\Models\WorkLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkLogStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_pausing_finished_task_preserves_existing_end_time(): void
    {
        $workLog = WorkLog::factory()->create([
            'work_date' => '2026-05-15',
            'started_at' => '10:00',
            'ended_at' => '10:11',
            'ended_date' => '2026-05-15',
            'status' => WorkLog::STATUS_FINISHED,
        ]);

        $workLog->sessions()->create([
            'work_date' => '2026-05-15',
            'started_at' => '10:00',
        ]);

        $workLog->pause('09:30', '2026-05-15');

        $workLog->refresh();

        $this->assertSame(WorkLog::STATUS_PAUSED, $workLog->status);
        $this->assertNull($workLog->ended_at);
        $this->assertSame(11, $workLog->duration_minutes);
        $this->assertSame('10:11', substr((string) $workLog->sessions()->first()->ended_at, 0, 5));
    }
}
