<?php

namespace App\Listeners;

use App\Events\ResultWeightUpdated;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResultTotalCalc
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  ResultWeightUpdated  $event
     * @return void
     */
    public function handle(ResultWeightUpdated $event)
    {
        //
    }
}
