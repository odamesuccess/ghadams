<?php

namespace App\Http\Controllers;

use App\Support\PdfWatermark;

use App\Models\LoginTracker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LoginTrackerController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('LoginTrackers/Index', [
            'filters' => $request->only(['search', 'event']),
            'loginTrackers' => $this->query($request)->paginate(100)->withQueryString(),
            'events' => LoginTracker::query()->select('event')->distinct()->orderBy('event')->pluck('event'),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $records = $this->query($request)->limit(10000)->get();
        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date','User','Email','Event','IP','Browser','Platform','Device','Login At','Logout At','Duration Seconds','Description']);
            foreach ($records as $item) {
                fputcsv($out, [
                    optional($item->created_at)->format('Y-m-d H:i:s'),
                    $item->user?->name ?? 'System',
                    $item->email,
                    $item->event,
                    $item->ip_address,
                    $item->browser,
                    $item->platform,
                    $item->device,
                    optional($item->login_at)->format('Y-m-d H:i:s'),
                    optional($item->logout_at)->format('Y-m-d H:i:s'),
                    $item->session_duration_seconds,
                    $item->description,
                ]);
            }
            fclose($out);
        }, 'login-trackers.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Request $request)
    {
        $records = $this->query($request)->limit(2000)->get();
        $pdf = Pdf::loadView('pdf.login-trackers', [
            'records' => $records,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf);

        return $pdf->stream('login-trackers.pdf');
    }

    public function destroy(LoginTracker $loginTracker)
    {
        $loginTracker->delete();
        return back()->with('success', 'Login tracker record deleted successfully.');
    }

    private function query(Request $request)
    {
        return LoginTracker::query()
            ->with('user:id,name,email')
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                        ->orWhere('event', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('browser', 'like', "%{$search}%")
                        ->orWhere('platform', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('event'), fn ($query, $event) => $query->where('event', $event))
            ->latest();
    }
}
