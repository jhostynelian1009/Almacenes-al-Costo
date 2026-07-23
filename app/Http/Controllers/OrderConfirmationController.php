<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\View\View;

class OrderConfirmationController extends Controller
{
    public function show(string $orderReference): View
    {
        $order = Order::query()
            ->with(['items' => fn ($query) => $query->orderBy('id')])
            ->where('reference', $orderReference)
            ->firstOrFail();

        return view('public.orders.confirmation', [
            'order' => $order,
        ]);
    }
}
