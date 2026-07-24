<?php

namespace App\Http\Controllers;

use App\Models\Ingredient;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\FinancialReportExport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function dashboard()
    {
        // 1. Penjualan Gabungan Terintegrasi
        $todaySales = Order::where('status', 'completed')->today()->sum('total_amount');
        $weekSales = Order::where('status', 'completed')->thisWeek()->sum('total_amount');
        $monthSales = Order::where('status', 'completed')->thisMonth()->sum('total_amount');

        // 2. Statistik POS vs Online
        $todayOnlineSales = Order::where('status', 'completed')->where('source', 'online')->today()->sum('total_amount');
        $todayPosSales = Order::where('status', 'completed')->where('source', 'pos')->today()->sum('total_amount');

        $todayOrders = Order::today()->count();
        $pendingOrders = Order::where('status', 'pending')->count();
        $totalProducts = Product::count();

        // 3. Stok Bahan Baku (Ingredients)
        $lowStockIngredients = Ingredient::lowStock()->get();
        $totalIngredients = Ingredient::count();
        $totalIngredientValue = Ingredient::all()->sum('stock_value');

        // 4. Data Chart Penjualan 7 Hari Terakhir
        $dailySales = Order::where('status', 'completed')
            ->whereBetween('created_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->selectRaw("DATE(created_at) as date, 
                         SUM(CASE WHEN source = 'online' THEN total_amount ELSE 0 END) as online_total,
                         SUM(CASE WHEN source = 'pos' THEN total_amount ELSE 0 END) as pos_total,
                         SUM(total_amount) as total")
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $dates = collect(range(6, 0))->map(function ($days) {
            return now()->subDays($days)->format('Y-m-d');
        });

        $chartData = $dates->map(function ($date) use ($dailySales) {
            $data = $dailySales->get($date);
            return [
                'date' => Carbon::parse($date)->format('d M'),
                'online' => (float) ($data ? $data->online_total : 0),
                'pos' => (float) ($data ? $data->pos_total : 0),
                'total' => (float) ($data ? $data->total : 0),
            ];
        });

        // =========================================================================
        // 🛠️ DI SINI TEMPAT PERBAIKANNYA (Bagian Paling Bawah Sebelum Return View)
        // =========================================================================
        
        // Perbaikan: Tambahkan with('ingredients') agar saat looping produk terlaris di blade,
        // aplikasi Anda bisa menghitung HPP/Modal produk tersebut secara on-the-fly tanpa error N+1.
        $categorySales = Product::with('ingredients') 
            ->withSum('orderItems as total_sales', 'subtotal')
            ->orderByDesc('total_sales')
            ->take(5)
            ->get();

        // =========================================================================

        return view('admin.dashboard', compact(
            'todaySales', 'weekSales', 'monthSales', 'todayOnlineSales', 'todayPosSales',
            'todayOrders', 'pendingOrders', 'totalProducts', 'chartData', 'categorySales',
            'lowStockIngredients', 'totalIngredients', 'totalIngredientValue'
        ));
    }

    public function index(Request $request)
    {
        $data = $this->getReportData($request);

        return view('admin.reports.index', $data);
    }

    public function exportPdf(Request $request)
    {
        $data = $this->getReportData($request);

        $pdf = Pdf::loadView('admin.reports.pdf', $data);

        return $pdf->download('laporan-keuangan-' . $data['startDate']->format('Y-m-d') . '.pdf');
    }

    public function exportExcel(Request $request)
    {
        $data = $this->getReportData($request);
        
        $fileName = 'Laporan_Keuangan_Sazaliha_' . $data['startDate']->format('Y-m-d') . '.xlsx';

        return Excel::download(new FinancialReportExport($data), $fileName);
    }

    /**
     * Helper Method: Pusat pengolahan data laporan finansial terintegrasi (Online + POS)
     */
    private function getReportData(Request $request): array
    {
        $startDate = $request->filled('start_date') 
            ? Carbon::parse($request->start_date)->startOfDay() 
            : now()->startOfMonth();
        
        $endDate = $request->filled('end_date') 
            ? Carbon::parse($request->end_date)->endOfDay() 
            : now()->endOfDay();

        // 🛠️ PERBAIKAN 1: Tambahkan 'items.product.ingredients' ke dalam with() agar query efisien (Anti N+1)
        $orders = Order::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['items.product.ingredients', 'user', 'cashier']) 
            ->latest()
            ->get();

        // Perhitungan Total Finansial Tersegmentasi
        $totalRevenue = $orders->sum('total_amount');
        $onlineRevenue = $orders->where('source', 'online')->sum('total_amount');
        $posRevenue = $orders->where('source', 'pos')->sum('total_amount');
        
        // 🛠️ PERBAIKAN 2: Hitung Total Modal Secara On-the-Fly dari Bahan Baku (Resep)
        $totalCost = $orders->flatMap->items->sum(function ($item) {
            if (!$item->product) return 0;
            
            $costPerProduct = 0;
            foreach ($item->product->ingredients as $ingredient) {
                $takaran = $ingredient->pivot->quantity_needed ?? 0;
                $hargaBahan = $ingredient->cost_per_unit ?? 0;
                $costPerProduct += ($takaran * $hargaBahan);
            }
            
            return $costPerProduct * $item->quantity;
        });
        
        $grossProfit = $totalRevenue - $totalCost;

        // Breakdown Laporan Harian Terintegrasi
        $dailyReport = $orders->groupBy(function ($order) {
            return $order->close_at ?? $order->created_at->format('Y-m-d');
        })->map(function ($dayOrders) {
            $revenue = $dayOrders->sum('total_amount');
            $onlineRev = $dayOrders->where('source', 'online')->sum('total_amount');
            $posRev = $dayOrders->where('source', 'pos')->sum('total_amount');
            
            // 🛠️ PERBAIKAN 3: Hitung Modal Harian Secara On-the-Fly dari Bahan Baku (Resep)
            $cost = $dayOrders->flatMap->items->sum(function ($item) {
                if (!$item->product) return 0;
                
                $costPerProduct = 0;
                foreach ($item->product->ingredients as $ingredient) {
                    $takaran = $ingredient->pivot->quantity_needed ?? 0;
                    $hargaBahan = $ingredient->cost_per_unit ?? 0;
                    $costPerProduct += ($takaran * $hargaBahan);
                }
                
                return $costPerProduct * $item->quantity;
            });
            
            return [
                'orders' => $dayOrders->count(),
                'online_orders' => $dayOrders->where('source', 'online')->count(),
                'pos_orders' => $dayOrders->where('source', 'pos')->count(),
                'revenue' => $revenue,
                'online_revenue' => $onlineRev,
                'pos_revenue' => $posRev,
                'cost' => $cost,
                'profit' => $revenue - $cost,
            ];
        });

        return compact(
            'orders', 'totalRevenue', 'onlineRevenue', 'posRevenue', 
            // Variabel di bawah ini sekarang bernilai akurat hasil kalkulasi real-time resep
            'totalCost', 'grossProfit', 'dailyReport', 'startDate', 'endDate'
        );
    }
}