<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;

class AdminDashboardController extends Controller
{
    /**
     * Display admin dashboard statistics.
     */
    public function index()
    {
        $totalUsers = User::count();

        $totalProducts = Product::count();

        $totalOrders = Order::count();

        $totalPayments = Payment::count();

        $pendingPayments = Payment::where(
            'payment_status',
            'pending'
        )->count();

        $paidPayments = Payment::where(
            'payment_status',
            'paid'
        )->count();

        $failedPayments = Payment::where(
            'payment_status',
            'failed'
        )->count();

        $totalRevenue = Payment::where(
            'payment_status',
            'paid'
        )->sum('amount');

        return response()->json([
            'total_users' => $totalUsers,
            'total_products' => $totalProducts,
            'total_orders' => $totalOrders,
            'total_payments' => $totalPayments,

            'pending_payments' => $pendingPayments,
            'paid_payments' => $paidPayments,
            'failed_payments' => $failedPayments,

            'total_revenue' => $totalRevenue,
        ]);
    }
}
