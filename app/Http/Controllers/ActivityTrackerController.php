<?php

namespace App\Http\Controllers;

use App\Support\PdfWatermark;

use App\Models\ActivityTracker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityTrackerController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('ActivityTrackers/Index', [
            'filters' => $request->only(['search', 'module', 'action']),
            'activityTrackers' => $this->query($request)->paginate(100)->withQueryString(),
            'modules' => ActivityTracker::query()->select('module')->distinct()->orderBy('module')->pluck('module'),
            'actions' => ActivityTracker::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $records = $this->query($request)->limit(10000)->get();
        return response()->streamDownload(function () use ($records) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date','User','Module','Action','Title','Description','IP Address']);
            foreach ($records as $item) {
                fputcsv($out, [
                    optional($item->created_at)->format('Y-m-d H:i:s'),
                    $item->user?->name ?? 'System',
                    $item->module,
                    $item->action,
                    $item->title,
                    $item->description,
                    $item->ip_address,
                ]);
            }
            fclose($out);
        }, 'activity-trackers.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(Request $request)
    {
        $records = $this->query($request)->limit(2000)->get();
        $pdf = Pdf::loadView('pdf.activity-trackers', [
            'records' => $records,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf);

        return $pdf->stream('activity-trackers.pdf');
    }

    public function destroy(ActivityTracker $activityTracker)
    {
        $activityTracker->delete();
        return back()->with('success', 'Activity tracker record deleted successfully.');
    }

    private function query(Request $request)
    {
        return ActivityTracker::query()
            ->with('user:id,name,email')
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('module', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('module'), fn ($query, $module) => $query->where('module', $module))
            ->when($request->input('action'), fn ($query, $action) => $query->where('action', $action))
            ->latest();
    }
}
