<?php

namespace App\Console\Commands;

use App\Exports\WorkReportXlsx;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateDailyReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'report:today {--date= : Data no formato YYYY-MM-DD}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Gera um relatorio XLSX do dia atual em storage/app/private/reports.';

    /**
     * Execute the console command.
     */
    public function handle(ReportService $reports): int
    {
        $date = $this->option('date') ?: now()->toDateString();
        $report = $reports->totalsFor([
            'start_date' => $date,
            'end_date' => $date,
        ]);

        try {
            $export = new WorkReportXlsx($report);
            $path = 'reports/relatorio-'.$date.'.xlsx';

            Storage::put($path, $export->contents());
            $this->info('Relatorio gerado em storage/app/private/'.$path);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
