<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class QrAnalyticsController extends Controller
{
    public function index()
    {
        /*
        |--------------------------------------------------------------------------
        | Cache ONLY raw analytics data (safe for serialization)
        |--------------------------------------------------------------------------
        */
        $data = cache()->remember('qr.analytics', 30, function () {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Totals per link (FAST subqueries, no heavy JOIN)
            |--------------------------------------------------------------------------
            */
            $totals = DB::table('links')
                ->where('status', 'active')
                ->select('links.id', 'links.name')

                ->selectSub(
                    fn ($q) => $q->from('link_scans')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('link_scans.link_id', 'links.id'),
                    'total_scans'
                )

                ->selectSub(
                    fn ($q) => $q->from('link_scans')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('link_scans.link_id', 'links.id')
                        ->whereDate('created_at', today()),
                    'daily_scans'
                )

                ->selectSub(
                    fn ($q) => $q->from('link_scans')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('link_scans.link_id', 'links.id')
                        ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                    'weekly_scans'
                )

                ->selectSub(
                    fn ($q) => $q->from('link_scans')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('link_scans.link_id', 'links.id')
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year),
                    'monthly_scans'
                )

                ->selectSub(
                    fn ($q) => $q->from('link_scans')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('link_scans.link_id', 'links.id')
                        ->whereYear('created_at', now()->year),
                    'yearly_scans'
                )

                ->orderBy('links.name')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | 2️⃣ Continuous 30-day daily trend
            |--------------------------------------------------------------------------
            */
            $start = now()->subDays(29)->startOfDay();
            $end = now()->endOfDay();

            $rawDaily = DB::table('link_scans')
                ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->whereBetween('created_at', [$start, $end])
                ->groupBy('date')
                ->pluck('total', 'date');

            $dailyScans = collect(CarbonPeriod::create($start, $end))
                ->map(fn (Carbon $date) => [
                    'date' => $date->toDateString(),
                    'total' => $rawDaily[$date->toDateString()] ?? 0,
                ])
                ->values();

            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Device distribution
            |--------------------------------------------------------------------------
            */
            $devices = DB::table('link_scans')
                ->join('links', 'link_scans.link_id', '=', 'links.id')
                ->where('links.status', 'active')
                ->selectRaw("COALESCE(device,'Unknown') as device, COUNT(*) as total")
                ->groupByRaw("COALESCE(device,'Unknown')")
                ->orderByDesc('total')
                ->get();
            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Geographic distributions
            |--------------------------------------------------------------------------
            */
            $countries = DB::table('link_scans')
                ->join('links', 'link_scans.link_id', '=', 'links.id')
                ->where('links.status', 'active')
                ->selectRaw("COALESCE(country,'Unknown') as country, COUNT(*) as total")
                ->groupByRaw("COALESCE(country,'Unknown')")
                ->orderByDesc('total')
                ->get();

            $regions = DB::table('link_scans')
                ->join('links', 'link_scans.link_id', '=', 'links.id')
                ->where('links.status', 'active')
                ->selectRaw("COALESCE(region,'Unknown') as region, COUNT(*) as total")
                ->groupByRaw("COALESCE(region,'Unknown')")
                ->orderByDesc('total')
                ->get();

            $cities = DB::table('link_scans')
                ->join('links', 'link_scans.link_id', '=', 'links.id')
                ->where('links.status', 'active')
                ->selectRaw("COALESCE(city,'Unknown') as city, COUNT(*) as total")
                ->groupByRaw("COALESCE(city,'Unknown')")
                ->orderByDesc('total')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | 5️⃣ Recent scans
            |--------------------------------------------------------------------------
            */
            $allScans = DB::table('link_scans')
                ->join('links', 'link_scans.link_id', '=', 'links.id')
                ->where('links.status', 'active')
                ->select(
                    'links.name',
                    'link_scans.created_at',
                    DB::raw("COALESCE(link_scans.country,'Unknown') as country")
                )
                ->orderByDesc('link_scans.created_at')
                ->limit(200)
                ->get();

            /*
            |--------------------------------------------------------------------------
            | 6️⃣ Optimized latest scan summary per link (FAST JOIN-SUBQUERY)
            |--------------------------------------------------------------------------
            */
            $scanTimes = DB::table('link_scans')
                ->selectRaw('link_id, MIN(created_at) as first_scan, MAX(created_at) as last_scan')
                ->groupBy('link_id');

            $latestGeo = DB::table('link_scans as ls')
                ->selectRaw('
                    ls.link_id,
                    COALESCE(ls.country,"Unknown") as last_country,
                    COALESCE(ls.region,"Unknown")  as last_region,
                    COALESCE(ls.city,"Unknown")    as last_city
                ')
                ->whereIn('ls.created_at', function ($q) {
                    $q->selectRaw('MAX(created_at)')
                        ->from('link_scans')
                        ->groupBy('link_id');
                });

            /*
|--------------------------------------------------------------------------
| 5️⃣ FULL SCAN HISTORY (ALL SCANS — NOT SUMMARY)
|--------------------------------------------------------------------------
*/
            $linkScanHistory = DB::table('link_scans')
                ->join('links', 'link_scans.link_id', '=', 'links.id')
                ->select(
                    'link_scans.id as scan_id',
                    'links.id as link_id',
                    'links.name',
                    'link_scans.created_at as scan_time',
                    DB::raw("COALESCE(link_scans.city,'Unknown') as city"),
                    DB::raw("COALESCE(link_scans.region,'Unknown') as region"),
                    DB::raw("COALESCE(link_scans.country,'Unknown') as country"),
                    DB::raw("COALESCE(link_scans.device,'Unknown') as device"),
                    DB::raw("COALESCE(link_scans.browser,'Unknown') as browser")
                )
                ->orderByDesc('link_scans.created_at')
                ->get();

            /*
            |--------------------------------------------------------------------------
            | 7️⃣ Time-based aggregations
            |--------------------------------------------------------------------------
            */
            $scansPerMinute = DB::table('link_scans')
                ->selectRaw("DATE_FORMAT(created_at,'%Y-%m-%d %H:%i') as minute, COUNT(*) as total")
                ->where('created_at', '>=', now()->subHour())
                ->groupBy('minute')
                ->orderBy('minute')
                ->get();

            $scansPerHour = DB::table('link_scans')
                ->selectRaw("DATE_FORMAT(created_at,'%Y-%m-%d %H:00') as hour, COUNT(*) as total")
                ->where('created_at', '>=', now()->subDay())
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();

            $scansPerDay = DB::table('link_scans')
                ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('day')
                ->orderBy('day')
                ->get();

            return [
                'totals' => $totals,
                'dailyScans' => $dailyScans,
                'devices' => $devices,
                'countries' => $countries,
                'regions' => $regions,
                'cities' => $cities,
                'allScans' => $allScans,
                'linkScanHistory' => $linkScanHistory,
                'scansPerMinute' => $scansPerMinute,
                'scansPerHour' => $scansPerHour,
                'scansPerDay' => $scansPerDay,
            ];
        });

        /*
        |--------------------------------------------------------------------------
        | Render AFTER cache
        |--------------------------------------------------------------------------
        */
        return Inertia::render('Links/Analytics', $data);
    }
}
