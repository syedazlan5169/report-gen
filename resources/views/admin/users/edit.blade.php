<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-6 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-md space-y-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Admin</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Edit User</h1>
            </div>

            @if (session('status') === 'user-updated')
                <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
                    User updated.
                </div>
            @elseif (session('status') === 'user-self-demote-blocked')
                <div class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 ring-1 ring-amber-200">
                    You can't remove your own admin access.
                </div>
            @elseif (session('status') === 'user-self-delete-blocked')
                <div class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 ring-1 ring-amber-200">
                    You can't delete your own account.
                </div>
            @endif

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <form method="POST" action="{{ route('admin.users.update', $targetUser) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    @include('admin.users.partials.form', ['targetUser' => $targetUser])

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <a href="{{ route('admin.users.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                            Cancel
                        </a>

                        <x-primary-button class="min-h-11 rounded-xl px-5">
                            {{ __('Save Changes') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>

            @unless ($targetUser->is(auth()->user()))
                @unless ($targetUser->is_admin)
                    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <form method="POST" action="{{ route('admin.users.impersonate', $targetUser) }}">
                            @csrf

                            <x-secondary-button class="min-h-11 rounded-xl px-5">
                                {{ __('Impersonate User') }}
                            </x-secondary-button>
                        </form>
                    </div>
                @endunless

                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                    <form method="POST" action="{{ route('admin.users.destroy', $targetUser) }}" onsubmit="return confirm('Delete this user?');">
                        @csrf
                        @method('DELETE')

                        <x-danger-button class="min-h-11 rounded-xl px-5">
                            {{ __('Delete User') }}
                        </x-danger-button>
                    </form>
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
