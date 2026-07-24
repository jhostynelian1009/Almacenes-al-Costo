<?php

namespace App\Listeners;

use App\Events\OrderApproved;
use App\Jobs\UpdateInventoryOnPayment;

class HandleOrderApproved
{
    public function handle(OrderApproved $event): void
    {
        UpdateInventoryOnPayment::dispatch($event->order);
    }
}
