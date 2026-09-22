<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('username')->get();

        return view('admin.users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('admin.users.create', [
            'targetUser' => new User(['is_admin' => false]),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        User::create([
            'username' => $request->string('username')->toString(),
            'password' => Hash::make($request->string('password')->toString()),
            'is_admin' => $request->boolean('is_admin'),
        ]);

        return redirect()->route('admin.users.index')->with('status', 'user-created');
    }

    public function edit(Request $request, User $user): View
    {
        return view('admin.users.edit', ['targetUser' => $user]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        if ($user->is($request->user()) && ! $request->boolean('is_admin')) {
            return redirect()->route('admin.users.edit', $user)
                ->with('status', 'user-self-demote-blocked');
        }

        $user->username = $request->string('username')->toString();
        $user->is_admin = $request->boolean('is_admin');

        if ($request->filled('password')) {
            $user->password = Hash::make($request->string('password')->toString());
        }

        $user->save();

        return redirect()->route('admin.users.edit', $user)->with('status', 'user-updated');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return redirect()->route('admin.users.index')->with('status', 'user-self-delete-blocked');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', 'user-deleted');
    }
}
