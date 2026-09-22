<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-6 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-md space-y-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Admin</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Users</h1>
                </div>

                <a href="{{ route('admin.users.create') }}" class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    + Add User
                </a>
            </div>

            @if (session('status'))
                <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
                    @if (session('status') === 'user-created')
                        User added.
                    @elseif (session('status') === 'user-updated')
                        User updated.
                    @elseif (session('status') === 'user-deleted')
                        User deleted.
                    @endif
                </div>
            @endif

            @if ($users->isEmpty())
                <div class="rounded-2xl bg-white p-5 text-sm leading-6 text-slate-600 shadow-sm ring-1 ring-slate-200">
                    No users have been added yet.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($users as $listedUser)
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                            <a href="{{ route('admin.users.edit', $listedUser) }}" class="block rounded-xl transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                                <div class="flex min-h-12 items-center justify-between gap-4">
                                    <p class="break-words text-base font-semibold text-slate-800">{{ $listedUser->username }}</p>

                                    @if ($listedUser->is_admin)
                                        <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800">Admin</span>
                                    @endif
                                </div>
                            </a>

                            @unless ($listedUser->is_admin || $listedUser->is(auth()->user()))
                                <form method="POST" action="{{ route('admin.users.impersonate', $listedUser) }}" class="mt-3">
                                    @csrf
                                    <button type="submit" class="inline-flex min-h-9 items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                                        Impersonate
                                    </button>
                                </form>
                            @endunless
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
