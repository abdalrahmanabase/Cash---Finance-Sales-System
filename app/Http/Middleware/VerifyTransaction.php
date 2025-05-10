<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class VerifyTransaction
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);
        
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
            abort(500, 'Uncommitted transaction detected');
        }
        
        return $response;
    }
}
