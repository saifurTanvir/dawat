<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * F71: an admin opens the panel as one of their staff, to see exactly what
 * that staff member sees, and comes back with one tap. Only admins start it,
 * only for staff (never another admin); the way back needs the session that
 * started it.
 */
final class StaffView
{
    public const KEY = 'staff_view';

    public static function start(User $admin, User $staff): RedirectResponse
    {
        if (! $admin->isAdmin() || $staff->isAdmin() || ! $staff->isStaff() || $staff->isBlocked()) {
            throw ValidationException::withMessages(['staff' => 'শুধু অ্যাডমিন একজন স্টাফের মতো করে দেখতে পারেন।']);
        }
        $session = session();
        Auth::guard('web')->login($staff);
        $session->put(self::KEY, ['admin' => $admin->id, 'staff' => $staff->id, 'name' => $staff->name ?: $staff->phone]);

        return redirect('/admin');
    }

    /** @return array{admin: int, staff: int, name: string}|null */
    public static function current(): ?array
    {
        $view = session(self::KEY);

        return is_array($view) && auth('web')->id() === ($view['staff'] ?? null) ? $view : null;
    }

    public static function stop(): RedirectResponse
    {
        $view = session()->pull(self::KEY);
        $admin = is_array($view) && auth('web')->id() === ($view['staff'] ?? null) ? User::find($view['admin'] ?? 0) : null;
        if (! $admin?->isAdmin()) {
            return redirect('/admin');
        }
        Auth::guard('web')->login($admin);

        return redirect('/admin/staff');
    }
}
