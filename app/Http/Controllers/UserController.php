<?php

namespace App\Http\Controllers;

use App\Support\PdfWatermark;

use App\Models\Unit;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use App\Services\LoginTrackerService;

class UserController extends Controller
{
    /* ===========================
       INDEX
    ============================ */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $relations = [
            'roles:id,name',
            'unit:id,name',
        ];

        $columns = [
            'id',
            'name',
            'sex',
            'user_status',
            'account_expires_at',
            'deactivation_reason',
            'deactivated_at',
            'deactivated_by',
            'last_login_at',
            'last_logout_at',
            'last_seen_at',
            'unit_id',
            'email',
            'contact_number',
            'date_of_birth',
            'profile_image',
            'created_at',
            'updated_at',
        ];

        return Inertia::render('Users/Index', [
            'filters' => $request->only('search'),
            'authUserId' => Auth::id(),

            'users' => User::query()
                ->with($relations)
                ->select($columns)
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('id', $search)
                            ->orWhereHas('roles', fn ($r) => $r->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('unit', fn ($u) => $u->where('name', 'like', "%{$search}%"));
                    });
                })
                ->latest('updated_at')
                ->paginate(100)
                ->withQueryString(),
        ]);
    }

    /* ===========================
       CREATE
    ============================ */
    public function create()
    {
        return Inertia::render('Users/Create', [
            'roles' => Role::pluck('name'),
            'units' => Unit::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /* ===========================
       STORE
    ============================ */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sex' => ['nullable', 'string', 'max:50'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'user_status' => ['nullable', 'in:Active,Inactive,IsSystem'],
            'deactivation_reason' => ['nullable', 'string', 'max:1000'],
            'email' => ['required', 'email', 'unique:users,email'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'password' => ['required', 'confirmed', 'min:8'],
            'profile_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg'],
            'roles' => ['required', 'array', 'size:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        if ($request->hasFile('profile_image')) {
            $validated['profile_image'] = $this->storeProfileImage(
                $request->file('profile_image'),
                $validated['name']
            );
        }

        if (($validated['user_status'] ?? 'Active') === 'Inactive') {
            $validated['deactivated_at'] = now();
            $validated['deactivated_by'] = Auth::id();
        } else {
            $validated['deactivation_reason'] = null;
        }

        $validated['password'] = Hash::make($validated['password']);
        $validated['force_password_change'] = true;
        $validated['password_changed_time'] = null;

        $user = User::create($validated);

        if ($request->filled('roles')) {
            $user->syncRoles($request->roles);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'User created. User must change password on first login.');
    }

    /* ===========================
       SHOW
    ============================ */
    public function show(User $user)
    {
        return Inertia::render('Users/Show', [
            'user' => $user->load(['roles', 'unit']),
        ]);
    }

    /* ===========================
       EDIT
    ============================ */
    public function edit(User $user)
    {
        return Inertia::render('Users/Edit', [
            'user' => $user->load(['roles', 'unit']),
            'roles' => Role::pluck('name'),
            'units' => Unit::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /* ===========================
       UPDATE
    ============================ */
    public function update(Request $request, User $user)
    {
        if (
            $user->user_status === 'IsSystem' &&
            $request->filled('user_status') &&
            $request->user_status !== 'IsSystem'
        ) {
            return back()->withErrors([
                'user_status' => 'System user status cannot be changed.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sex' => ['nullable', 'string', 'max:50'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'user_status' => ['required', 'in:Active,Inactive,IsSystem'],
            'deactivation_reason' => ['nullable', 'string', 'max:1000'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'profile_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg'],
            'roles' => ['required', 'array', 'size:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $oldPhoto = $user->profile_image;
        $newPhoto = null;
        if ($request->hasFile('profile_image')) {

            $validated['profile_image'] = $this->storeProfileImage(
                $request->file('profile_image'),
                $validated['name']
            );
            $newPhoto = $validated['profile_image'];
        } else {
            unset($validated['profile_image']);
        }

        if (($validated['user_status'] ?? $user->user_status) === 'Inactive') {
            $validated['deactivated_at'] = $user->deactivated_at ?? now();
            $validated['deactivated_by'] = $user->deactivated_by ?? Auth::id();
        } elseif (($validated['user_status'] ?? $user->user_status) !== 'Inactive') {
            $validated['deactivation_reason'] = null;
            $validated['deactivated_at'] = null;
            $validated['deactivated_by'] = null;
        }

        $roles = $validated['roles'];
        unset($validated['roles']);
        try {
            DB::transaction(function () use ($user, $validated, $roles): void {
                $user->update($validated);
                $user->syncRoles($roles);
            });
        } catch (\Throwable $exception) {
            if ($newPhoto) {
                Storage::disk('public')->delete($newPhoto);
            }
            throw $exception;
        }
        if ($newPhoto && $oldPhoto && $newPhoto !== $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /* ===========================
       DESTROY
    ============================ */
    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            abort(403, 'You cannot delete your own account.');
        }

        if ($user->user_status === 'IsSystem') {
            return back()->with('error', 'System users cannot be deleted.');
        }

        if ($user->profile_image) {
            Storage::disk('public')->delete($user->profile_image);
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /* ===========================
       RESET PASSWORD
    ============================ */
    public function resetPassword(Request $request, User $user)
    {
        if ($user->id === Auth::id()) {
            abort(403, 'You cannot reset your own password.');
        }

        if (! $user->canLogin()) {
            LoginTrackerService::record(
                $request,
                $user->isExpired() ? 'expired_blocked' : 'inactive_blocked',
                $user,
                $user->email,
                'Admin password reset link blocked because account is inactive or expired.'
            );

            return back()->with('error', 'Password reset link was not sent because this account is inactive or expired.');
        }

        $status = Password::sendResetLink([
            'email' => $user->email,
        ]);

        if ($status === Password::RESET_LINK_SENT) {
            LoginTrackerService::record(
                $request,
                'password_reset_link_sent',
                $user,
                $user->email,
                'Admin sent password reset link to user.'
            );

            return redirect()
                ->route('users.index')
                ->with('success', 'Password reset link sent to user email.');
        }

        return back()->with('error', __($status));
    }

    public function activate(Request $request, User $user)
    {
        $user->update([
            'user_status' => 'Active',
            'deactivation_reason' => null,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ]);

        return back()->with('success', 'User account activated successfully.');
    }

    public function deactivate(Request $request, User $user)
    {
        if ($user->id === Auth::id()) {
            abort(403, 'You cannot deactivate your own account.');
        }

        if ($user->user_status === 'IsSystem') {
            return back()->with('error', 'System users cannot be deactivated.');
        }

        $validated = $request->validate([
            'deactivation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $user->update([
            'user_status' => 'Inactive',
            'deactivation_reason' => $validated['deactivation_reason'] ?? 'Deactivated by administrator.',
            'deactivated_at' => now(),
            'deactivated_by' => Auth::id(),
        ]);

        return back()->with('success', 'User account deactivated successfully.');
    }

    public function expireNow(Request $request, User $user)
    {
        return back()->withErrors(['account_expires_at' => 'Account expiry is the 60th birthday. Use Deactivate to block an account earlier.']);
    }

    public function extendExpiry(Request $request, User $user)
    {
        return back()->withErrors(['account_expires_at' => 'Account expiry is calculated from date of birth. Correct the date of birth to recalculate it.']);
    }


    /* ===========================
       BULK ACTIONS
    ============================ */
    public function bulkActivate(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $updated = User::query()
            ->whereIn('id', $validated['user_ids'])
            ->where('user_status', '!=', 'IsSystem')
            ->update([
                'user_status' => 'Active',
                'deactivation_reason' => null,
                'deactivated_at' => null,
                'deactivated_by' => null,
                'updated_at' => now(),
            ]);

        return back()->with('success', "{$updated} selected user account(s) activated successfully.");
    }

    public function bulkDeactivate(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'deactivation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $userIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === Auth::id())
            ->values()
            ->all();

        $updated = User::query()
            ->whereIn('id', $userIds)
            ->where('user_status', '!=', 'IsSystem')
            ->update([
                'user_status' => 'Inactive',
                'deactivation_reason' => $validated['deactivation_reason'] ?? 'Bulk deactivated by administrator.',
                'deactivated_at' => now(),
                'deactivated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

        return back()->with('success', "{$updated} selected user account(s) deactivated successfully.");
    }

    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $userIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === Auth::id())
            ->values()
            ->all();

        $deleted = 0;

        DB::transaction(function () use ($userIds, &$deleted) {
            User::query()
                ->whereIn('id', $userIds)
                ->where('user_status', '!=', 'IsSystem')
                ->orderBy('id')
                ->chunkById(50, function ($users) use (&$deleted) {
                    foreach ($users as $user) {
                        if ($user->profile_image) {
                            Storage::disk('public')->delete($user->profile_image);
                        }

                        $user->delete();
                        $deleted++;
                    }
                });
        });

        return back()->with('success', "{$deleted} selected user account(s) deleted successfully.");
    }

    /* ===========================
       PDF EXPORT
    ============================ */
    public function pdf(User $user)
    {
        $pdf = Pdf::loadView('pdf.user', [
            'user' => $user->load('unit'),
            'currentuser' => Auth::user()?->name ?? 'System',
        ]);

        PdfWatermark::apply($pdf);

        return $pdf->stream("user-{$user->id}.pdf");
    }

    /* ===========================
       HELPERS
    ============================ */
    private function storeProfileImage($file, string $name): string
    {
        $slug = Str::slug($name);
        $ext = $file->getClientOriginalExtension();
        $rand = Str::random(6);

        $path = $file->storeAs(
            'profile-images',
            "{$slug}-{$rand}.{$ext}",
            'public'
        );
        if (!$path) {
            throw new \RuntimeException('Profile image could not be stored.');
        }
        return $path;
    }
}
