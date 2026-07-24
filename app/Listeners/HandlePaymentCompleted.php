<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Jobs\UpdateInventoryOnPayment;

class HandlePaymentCompleted
{
    public function handle(PaymentCompleted $event): void
    {
        UpdateInventoryOnPayment::dispatch($event->order);
    }
}
