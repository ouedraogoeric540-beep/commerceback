<?php

namespace App\Modules\Currency\Console;

use Illuminate\Console\Command;
use App\Modules\Currency\Jobs\UpdateExchangeRatesJob;

class CurrencySyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync exchange rates from external providers (dispatches Job)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Dispatching UpdateExchangeRatesJob to queue...');
        
        UpdateExchangeRatesJob::dispatch();
        
        $this->info('Job dispatched successfully.');
        return Command::SUCCESS;
    }
}
