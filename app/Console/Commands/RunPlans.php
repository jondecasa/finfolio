<?php

namespace App\Console\Commands;

use App\Services\PlanRunner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RunPlans extends Command
{
    protected $signature = 'plans:run {--date= : Treat this YYYY-MM-DD as "today"}';

    protected $description = 'Apply every scheduled contribution / movement plan that is due';

    public function handle(PlanRunner $runner): int
    {
        $on = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->startOfDay()
            : CarbonImmutable::today();

        $result = $runner->runDue($on);

        $this->info("Plans: {$result['applied']} applied, {$result['skipped']} skipped.");

        return self::SUCCESS;
    }
}
