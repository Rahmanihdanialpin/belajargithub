<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class CustomerDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $orders = Order::where('user_id', $user->id)
            ->with('items.product')
            ->latest()
            ->get();

        $totalOrders = $orders->count();
        $pendingOrders = $orders->where('status', 'pending')->count();
        $completedOrders = $orders->where('status', 'completed')->count();
        $totalSpent = $orders->where('status', 'completed')->sum('total_amount');

        return view('customer.dashboard', compact(
            'orders', 'totalOrders', 'pendingOrders', 'completedOrders', 'totalSpent'
        ));
    }
}


