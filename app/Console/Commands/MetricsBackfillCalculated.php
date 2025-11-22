<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Athlete;
use Illuminate\Console\Command;
use App\Services\MetricCalculationService;

class MetricsBackfillCalculated extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'metrics:backfill-calculated {--athletes= : Comma-separated IDs of athletes to backfill} {--days=90 : Number of past days to backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill the calculated metrics for all or specific athletes for a given number of past days.';

    /**
     * Execute the console command.
     */
    public function handle(MetricCalculationService $metricCalculationService)
    {
        $this->info('Starting to backfill calculated metrics...');

        $athleteIds = $this->option('athletes');
        $daysToBackfill = (int) $this->option('days');

        if ($athleteIds) {
            $athletes = Athlete::whereIn('id', explode(',', $athleteIds))->get();
        } else {
            $athletes = Athlete::all();
        }

        if ($athletes->isEmpty()) {
            $this->warn('No athletes found to process.');

            return;
        }

        $progressBar = $this->output->createProgressBar($athletes->count() * $daysToBackfill);
        $progressBar->start();

        foreach ($athletes as $athlete) {
            for ($i = 0; $i < $daysToBackfill; $i++) {
                $date = Carbon::today()->subDays($i);
                $metricCalculationService->processAndStoreDailyCalculatedMetrics($athlete, $date);
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();
        $this->info('Calculated metrics backfilled successfully for '.$athletes->count().' athlete(s) over the last '.$daysToBackfill.' days.');

        return self::SUCCESS;
    }
}
