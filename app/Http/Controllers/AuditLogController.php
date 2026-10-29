<?php

namespace App\Http\Controllers;

use App\Support\PdfWatermark;

use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('AuditLogs/Index', [
            'filters' => $request->only(['search', 'module', 'action']),
            'auditLogs' => $this->query($request)->paginate(50)->withQueryString(),
            'modules' => AuditLog::query()
                ->select('module')
                ->whereNotNull('module')
                ->distinct()
                ->orderBy('module')
                ->pluck('module'),
            'actions' => AuditLog::query()
                ->select('action')
                ->whereNotNull('action')
                ->distinct()
                ->orderBy('action')
                ->pluck('action'),
        ]);
    }

    public function exportCsv(Request $request)
    {
        $logs = $this->query($request)->limit(10000)->get();

        return response()->streamDownload(function () use ($logs): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Date',
                'User',
                'Email',
                'Module',
                'Action',
                'Title',
                'Description',
                'IP Address',
                'Country',
                'City',
                'User Agent',
            ]);

            foreach ($logs as $log) {
                fputcsv($out, [
                    optional($log->created_at)->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'System',
                    $log->user?->email ?? 'System generated event',
                    $log->module,
                    $log->action,
                    $log->title,
                    $log->description,
                    $log->ip_address,
                    $log->ip_country,
                    $log->ip_city,
                    $log->user_agent,
                ]);
            }

            fclose($out);
        }, 'audit-logs.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $logs = $this->query($request)->limit(2000)->get();

        $pdf = Pdf::loadView('pdf.audit-logs', [
            'logs' => $logs,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        PdfWatermark::apply($pdf);

        return $pdf->stream('audit-logs.pdf');
    }

    /**
     * Audit logs are immutable accountability records and must never be deleted.
     */
    public function destroy(AuditLog $auditLog)
    {
        abort(403, 'Audit logs are immutable and cannot be deleted.');
    }

    private function query(Request $request)
    {
        $search = $request->input('search');

        return AuditLog::query()
            ->with('user:id,name,email')
            ->when($search, function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('module', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%")
                        ->orWhere('ip_country', 'like', "%{$search}%")
                        ->orWhere('ip_city', 'like', "%{$search}%")
                        ->orWhere('user_agent', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($u) use ($search): void {
                            $u->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->input('module'), fn ($q, $v) => $q->where('module', $v))
            ->when($request->input('action'), fn ($q, $v) => $q->where('action', $v))
            ->latest();
    }
}
