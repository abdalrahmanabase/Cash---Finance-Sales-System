<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class VerifySalesInventory extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:verify-sales-inventory';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
{
    $sales = Sale::where('created_at', '>', now()->subDays(3))->get();
    
    foreach ($sales as $sale) {
        $results = $sale->verifyInventoryUpdate();
        
        foreach ($results as $result) {
            if ($result['expected_stock'] != $result['actual_stock'] || !$result['history_exists']) {
                $this->error("Issue with sale #{$sale->id}, product #{$result['product_id']}");
            }
        }
    }
    
    $this->info('Verification complete');
}
}
