<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Jobs\ReleaseReservedStock;

class HandlePaymentFailed
{
    public function handle(PaymentFailed $event): void
    {
        ReleaseReservedStock::dispatch($event->order);
    }
}
