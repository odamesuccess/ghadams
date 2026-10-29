<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Jenssegers\Agent\Agent;
use Stevebauman\Location\Facades\Location;

class LinkController extends Controller
{
    /* ================= INDEX ================= */
    public function index(Request $request)
    {
        // 1️⃣ Auto-expire links
        Link::whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where('status', '!=', 'expired')
            ->update(['status' => 'expired']);

        // 2️⃣ Search input
        $search = $request->input('search');

        // 3️⃣ Query with search + formatted output
        $qrs = Link::query()
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('url', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->through(function ($link) {
                return [
                    'id' => $link->id,
                    'name' => $link->name,
                    'url' => $link->url,
                    'status' => $link->status,
                    'logo' => $link->logo ? asset('storage/'.$link->logo) : null,
                    'qr_code' => $link->qr_code ? asset('storage/'.$link->qr_code) : null,
                    'expires_at' => $link->expires_at?->format('Y-m-d H:i'),
                    'created_at' => $link->created_at?->format('Y-m-d H:i'),
                    'updated_at' => $link->updated_at?->format('Y-m-d H:i'),
                ];
            })
            ->withQueryString();

        return Inertia::render('Links/Index', [
            'qrs' => $qrs,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /* ================= CREATE ================= */
    public function create()
    {
        return Inertia::render('Links/Create');
    }

    /* ================= STORE ================= */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'unique:links,url'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'expires_at' => ['nullable', 'date', 'after:now'],

            'status' => ['required', 'in:active,expired,disabled'],
        ]);

        DB::transaction(function () use ($request, &$validated) {

            if ($request->hasFile('logo')) {
                $fileName = Str::slug($validated['name']).'-'.time().'.'.
                    $request->file('logo')->getClientOriginalExtension();

                $validated['logo'] = $request->file('logo')
                    ->storeAs('app-qrs/logos', $fileName, 'public');
            }

            $validated['user_id'] = Auth::id();

            /** 🔐 Generate secure unique scan token */
            $validated['scan_token'] = Str::random(32);

            $link = Link::create($validated);

            $this->generateQr($link);
        });

        return redirect()->route('links.index')
            ->with('success', 'QR link created successfully.');
    }

    /* ================= EDIT ================= */
    public function edit(Link $link)
    {
        return Inertia::render('Links/Edit', [
            'link' => $link,
        ]);
    }

    /* ================= ANALYTICS ================= */
    public function analytic(Link $link)
    {
        // Canonical dataset (single source of truth)
        $baseQuery = DB::table('link_scans')
            ->where('link_id', $link->id);

        /* ================= TOTAL ================= */
        $total = (clone $baseQuery)->count();

        /* ================= ALL-TIME DAILY TREND ================= */
        $dailyScans = (clone $baseQuery)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        /* ================= PERIOD COUNTS ================= */
        $daily = (clone $baseQuery)
            ->whereDate('created_at', today())
            ->count();

        $weekly = (clone $baseQuery)
            ->whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ])
            ->count();

        $monthly = (clone $baseQuery)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        $yearly = (clone $baseQuery)
            ->whereYear('created_at', now()->year)
            ->count();

        /* ================= DEVICE / LOCATION BREAKDOWN ================= */
        $devices = (clone $baseQuery)
            ->selectRaw("COALESCE(device,'Unknown') as device, COUNT(*) as total")
            ->groupByRaw("COALESCE(device,'Unknown')")
            ->get();

        $countries = (clone $baseQuery)
            ->selectRaw("COALESCE(country,'Unknown') as country, COUNT(*) as total")
            ->groupByRaw("COALESCE(country,'Unknown')")
            ->get();

        $regions = (clone $baseQuery)
            ->selectRaw("COALESCE(region,'Unknown') as region, COUNT(*) as total")
            ->groupByRaw("COALESCE(region,'Unknown')")
            ->get();

        $cities = (clone $baseQuery)
            ->selectRaw("COALESCE(city,'Unknown') as city, COUNT(*) as total")
            ->groupByRaw("COALESCE(city,'Unknown')")
            ->get();

        /* ================= HISTORY ================= */
        $history = (clone $baseQuery)
            ->select([
                'id',
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as scan_time"),
                DB::raw("COALESCE(city,'Unknown') as city"),
                DB::raw("COALESCE(region,'Unknown') as region"),
                DB::raw("COALESCE(country,'Unknown') as country"),
                DB::raw("COALESCE(device,'Unknown') as device"),
                DB::raw("COALESCE(browser,'Unknown') as browser"),
            ])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Links/Analytic', [
            'link' => [
                'id' => $link->id,
                'name' => $link->name,
                'total_scans' => $total, // ✅ Now accurate
            ],
            'dailyScans' => $dailyScans,
            'devices' => $devices,
            'countries' => $countries,
            'regions' => $regions,
            'cities' => $cities,
            'linkScanHistory' => $history,

            'totals' => [[
                'id' => $link->id,
                'name' => $link->name,
                'daily_scans' => $daily,
                'weekly_scans' => $weekly,
                'monthly_scans' => $monthly,
                'yearly_scans' => $yearly,
                'total_scans' => $total, // ✅ Same canonical value
            ]],
        ]);
    }

    /* ================= SHOW (Details) ================= */
    public function show(Link $link)
    {
        return Inertia::render('Links/Show', [
            'link' => [
                'id' => $link->id,
                'name' => $link->name,
                'url' => $link->url,
                'status' => $link->status,
                'updated_at' => $link->updated_at?->format('Y-m-d H:i'),
                'expires_at' => $link->expires_at?->format('Y-m-d H:i'),
                'created_at' => $link->created_at?->format('Y-m-d H:i'),
                'logo' => $link->logo
                    ? asset('storage/'.$link->logo)
                    : null,
                // 🔥 ADD THIS
                'qr_code' => $link->qr_code
                    ? asset('storage/'.$link->qr_code)
                    : null,
            ],
        ]);
    }

    /* ================= UPDATE ================= */
    public function update(Request $request, Link $link)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', Rule::unique('links')->ignore($link->id)],
            'logo' => ['nullable', 'image', 'max:2048'],
            'expires_at' => ['nullable', 'date', 'after:now'],

            'status' => ['required', 'in:active,expired,disabled'],
        ]);

        DB::transaction(function () use ($request, $link, &$validated) {

            if ($request->hasFile('logo')) {

                if ($link->logo && Storage::disk('public')->exists($link->logo)) {
                    Storage::disk('public')->delete($link->logo);
                }

                $fileName = Str::slug($validated['name']).'-'.time().'.'.
                    $request->file('logo')->getClientOriginalExtension();

                $validated['logo'] = $request->file('logo')
                    ->storeAs('app-qrs/logos', $fileName, 'public');
            } else {
                $validated['logo'] = $link->logo;
            }

            $link->update($validated);
        });

        return redirect()->route('links.index')
            ->with('success', 'QR link updated successfully.');
    }

    /* ================= DESTROY ================= */
    public function destroy(Link $link)
    {
        if ($link->user_id !== Auth::id() && ! Auth::user()->hasRole('Super Admin')) {
            abort(403);
        }

        if ($link->logo && Storage::disk('public')->exists($link->logo)) {
            Storage::disk('public')->delete($link->logo);
        }

        if ($link->qr_code && Storage::disk('public')->exists($link->qr_code)) {
            Storage::disk('public')->delete($link->qr_code);
        }

        $link->delete();

        return redirect()->route('links.index')
            ->with('success', 'QR link deleted successfully.');
    }

    /* ================= QR GENERATION ================= */
    private function generateQr(Link $link): void
    {
        if ($link->qr_code && Storage::disk('public')->exists($link->qr_code)) {
            return;
        }

        $qrDir = 'app-qrs/images';
        $qrPath = "{$qrDir}/qr-{$link->id}.png";

        Storage::disk('public')->makeDirectory($qrDir);

        $qrCode = new QrCode(
            data: url('/r/'.$link->scan_token),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 900,
            margin: 12
        );

        $logo = $link->logo
            ? new Logo(storage_path("app/public/{$link->logo}"), resizeToWidth: 150)
            : null;

        // $label = new Label(
        //     text: $link->name,
        //     margin: new Margin(6, 0, 12, 0),
        //     font: new OpenSans(36)
        // );

        // $writer = new PngWriter();
        // $result = $writer->write($qrCode, $logo, $label);

        $writer = new PngWriter;

        /* No label → clean QR code */
        $result = $writer->write($qrCode, $logo, null);

        Storage::disk('public')->put($qrPath, $result->getString());

        $link->updateQuietly(['qr_code' => $qrPath]);
    }

    /* ================= REDIRECT ================= */
    public function redirect(Request $request, $token)
    {
        $link = Link::where('scan_token', $token)->firstOrFail();

        if ($link->status !== 'active') {
            return redirect()->route('qr.expired');
        }

        $ip = $request->ip();
        $ua = $request->userAgent();
        $agent = new Agent;

        if ($agent->isRobot()) {
            return redirect()->away($link->url);
        }

        // Handle private/local IPs safely
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $country = 'Local';
            $region = 'Local';
            $city = 'Local';
        } else {
            $position = cache()->remember("geo_$ip", 86400, fn () => Location::get($ip));

            $country = $position?->countryName ?? 'Unknown';
            $region = $position?->regionName ?? 'Unknown';
            $city = $position?->cityName ?? 'Unknown';
        }

        $recent = DB::table('link_scans')
            ->where('link_id', $link->id)
            ->where('ip_address', $ip)
            ->where('user_agent', $ua)
            ->where('created_at', '>=', now()->subMinute())
            ->exists();

        if (! $recent) {
            DB::transaction(function () use ($link, $ip, $ua, $country, $region, $city, $agent) {

                $link->increment('scan_count');

                DB::table('link_scans')->insert([
                    'link_id' => $link->id,
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                    'device' => $agent->device(),
                    'browser' => $agent->browser(),
                    'country' => $country,
                    'region' => $region,
                    'city' => $city,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                cache()->forget('qr.analytics');
            });
        }

        return redirect()->away($link->url);
    }

    public function linksTable()
    {
        $links = Link::latest()->paginate(20);

        return Inertia::render('Links/AllLinks', [
            'links' => $links,
        ]);
    }
}
