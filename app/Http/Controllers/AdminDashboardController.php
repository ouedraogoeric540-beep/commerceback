<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Shop;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AdminDashboardController extends Controller
{
    public function getStats(Request $request)
    {
        // On met en cache les données complexes pendant 1 heure pour ne pas ralentir le serveur
        $stats = Cache::remember('admin.dashboard.stats', 3600, function () {
            
            $now = Carbon::now();
            $lastMonth = Carbon::now()->subMonth();

            // --- 1. METRICS (KPIs) ---
            
            // Utilisateurs
            $totalUsers = User::count();
            $usersLastMonth = User::where('created_at', '<', $now->startOfMonth())->count();
            $userGrowth = $usersLastMonth > 0 ? (($totalUsers - $usersLastMonth) / $usersLastMonth) * 100 : 0;

            // Boutiques
            $totalShops = Shop::where('status', 'approved')->count();
            
            // GMV (Commandes payées ou terminées)
            $currentMonthGMV = Order::whereIn('status', ['paid', 'completed'])
                ->whereMonth('created_at', $now->month)
                ->whereYear('created_at', $now->year)
                ->sum('total_amount');

            $lastMonthGMV = Order::whereIn('status', ['paid', 'completed'])
                ->whereMonth('created_at', $lastMonth->month)
                ->whereYear('created_at', $lastMonth->year)
                ->sum('total_amount');
            
            $gmvGrowth = $lastMonthGMV > 0 ? (($currentMonthGMV - $lastMonthGMV) / $lastMonthGMV) * 100 : ($currentMonthGMV > 0 ? 100 : 0);

            // Revenus plateforme (estimés à 10% de commission par défaut pour l'exemple)
            // Idéalement, cela devrait venir de la table ledger_transactions plus tard.
            $currentMonthRevenue = $currentMonthGMV * 0.10; 
            $lastMonthRevenue = $lastMonthGMV * 0.10;
            $revenueGrowth = $lastMonthRevenue > 0 ? (($currentMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : ($currentMonthRevenue > 0 ? 100 : 0);

            // --- 2. CHARTS (Graphiques) ---
            
            // Ventes des 30 derniers jours (Groupé par jour)
            $salesLast30Days = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(total_amount) as total')
            )
            ->whereIn('status', ['paid', 'completed'])
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

            // Remplir les jours vides
            $chartData = [];
            for ($i = 29; $i >= 0; $i--) {
                $dateString = Carbon::now()->subDays($i)->format('Y-m-d');
                $dayData = $salesLast30Days->firstWhere('date', $dateString);
                $chartData[] = [
                    'date' => Carbon::now()->subDays($i)->format('d M'),
                    'total' => $dayData ? (float)$dayData->total : 0,
                    'revenue' => $dayData ? (float)($dayData->total * 0.10) : 0,
                ];
            }

            // Répartition par Statut de Commande (Alternative à la catégorie)
            $ordersByStatus = Order::select('status', DB::raw('COUNT(*) as count'))
                ->groupBy('status')
                ->get()
                ->map(function($item) {
                    return [
                        'name' => ucfirst($item->status),
                        'value' => $item->count
                    ];
                })->values()->toArray();

            return [
                'metrics' => [
                    'gmv' => [
                        'value' => $currentMonthGMV,
                        'growth' => round($gmvGrowth, 1),
                    ],
                    'revenue' => [
                        'value' => $currentMonthRevenue,
                        'growth' => round($revenueGrowth, 1),
                    ],
                    'users' => [
                        'value' => $totalUsers,
                        'growth' => round($userGrowth, 1),
                    ],
                    'shops' => [
                        'value' => $totalShops,
                        'growth' => 0, // Pas d'historique simple pour les boutiques dans cet exemple
                    ]
                ],
                'charts' => [
                    'revenueOverTime' => $chartData,
                    'ordersByStatus' => $ordersByStatus
                ]
            ];
        });

        // --- 3. ACTIONNABLES & ACTIVITÉ (Temps Réel) ---
        // On ne les met pas en cache car on veut une réactivité immédiate pour l'admin

        $pendingKyc = Shop::where('status', 'pending')->count();
        
        $pendingWithdrawals = 0; // Withdrawal::where('status', 'pending')->count();

        $recentActivity = [];
        
        // Dernières boutiques en attente
        $recentShops = Shop::where('status', 'pending')->orderBy('created_at', 'desc')->take(3)->get();
        foreach ($recentShops as $shop) {
            $recentActivity[] = [
                'id' => 'shop_'.$shop->id,
                'type' => 'kyc',
                'title' => 'Nouvelle boutique en attente',
                'description' => 'La boutique "'.$shop->name.'" attend d\'être validée.',
                'time' => $shop->created_at->diffForHumans(),
                'created_at' => $shop->created_at
            ];
        }

        // Dernières commandes
        $recentOrders = Order::with('user')->orderBy('created_at', 'desc')->take(5)->get();
        foreach ($recentOrders as $order) {
            $recentActivity[] = [
                'id' => 'order_'.$order->id,
                'type' => 'order',
                'title' => 'Nouvelle commande',
                'description' => 'Commande #'.$order->id.' passée pour '.$order->total_amount.' XOF.',
                'time' => $order->created_at->diffForHumans(),
                'created_at' => $order->created_at
            ];
        }

        // Trier l'activité par date décroissante
        usort($recentActivity, function($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });

        $stats['actionables'] = [
            'pendingKyc' => $pendingKyc,
            'pendingWithdrawals' => $pendingWithdrawals,
        ];
        
        $stats['activity'] = array_slice($recentActivity, 0, 8); // Garder les 8 plus récents

        return response()->json($stats);
    }
}
