<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SystemNotificationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Notifications/Index', [
            'filters' => $request->only(['search', 'type', 'read']),
            'notifications' => $this->query($request)->paginate(50)->withQueryString(),
            'unreadCount' => (clone $this->visibleNotificationsQuery())->whereNull('read_at')->count(),
        ]);
    }

    public function latest(): JsonResponse
    {
        $notifications = $this->visibleNotificationsQuery()
            ->with([
                'actor:id,name,email',
                'recipient:id,name,email',
            ])
            ->latest()
            ->limit(20)
            ->get();

        return response()->json([
            'unread_count' => (clone $this->visibleNotificationsQuery())->whereNull('read_at')->count(),
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(SystemNotification $notification)
    {
        abort_unless($this->canSee($notification), 403);

        $notification->forceFill(['read_at' => now()])->save();

        if (request()->expectsJson()) {
            return response()->json(['status' => 'ok', 'message' => 'Notification marked as read.']);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead()
    {
        $this->visibleNotificationsQuery()->whereNull('read_at')->update(['read_at' => now()]);

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'message' => 'All notifications marked as read.',
                'unread_count' => 0,
            ]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    public function clearAll()
    {
        $this->visibleNotificationsQuery()->delete();

        if (request()->expectsJson()) {
            return response()->json(['status' => 'ok', 'message' => 'Notifications cleared successfully.']);
        }

        return back()->with('success', 'Notifications cleared successfully.');
    }

    public function destroy(SystemNotification $notification)
    {
        abort_unless($this->canSee($notification), 403);

        $notification->delete();

        if (request()->expectsJson()) {
            return response()->json(['status' => 'ok', 'message' => 'Notification deleted successfully.']);
        }

        return back()->with('success', 'Notification deleted successfully.');
    }

    private function query(Request $request): Builder
    {
        return $this->visibleNotificationsQuery()
            ->with([
                'actor:id,name,email',
                'recipient:id,name,email',
            ])
            ->when($request->input('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhereHas('actor', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
                });
            })
            ->when($request->input('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->input('read') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($request->input('read') === 'read', fn ($q) => $q->whereNotNull('read_at'))
            ->latest();
    }

    private function visibleNotificationsQuery(): Builder
    {
        return SystemNotification::query()
            ->where(function ($query): void {
                $query
                    ->whereNull('user_id')
                    ->orWhere('user_id', Auth::id());
            });
    }

    private function canSee(SystemNotification $notification): bool
    {
        return $notification->user_id === null || $notification->user_id === Auth::id();
    }
}
