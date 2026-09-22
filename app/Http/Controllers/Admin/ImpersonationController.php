<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function store(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()) || $user->isAdmin(), 403);

        session(['impersonator_id' => $request->user()->id]);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('generator.index')->with('status', 'impersonation-started');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');

        abort_unless($impersonatorId, 403);

        Auth::login(User::findOrFail($impersonatorId));

        $request->session()->forget('impersonator_id');
        $request->session()->regenerate();

        return redirect()->route('admin.users.index')->with('status', 'impersonation-stopped');
    }
}
