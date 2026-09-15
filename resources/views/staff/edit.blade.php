<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-6 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-md space-y-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Staff</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Edit Staff</h1>
            </div>

            @if (session('status') === 'staff-updated')
                <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
                    Staff member updated.
                </div>
            @endif

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <form method="POST" action="{{ route('staff.update', $staffMember) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')

                    @include('staff.partials.form', ['staffMember' => $staffMember])

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <a href="{{ route('staff.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                            Cancel
                        </a>

                        <x-primary-button class="min-h-11 rounded-xl px-5">
                            {{ __('Save Changes') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <form method="POST" action="{{ route('staff.destroy', $staffMember) }}" onsubmit="return confirm('Delete this staff member?');">
                    @csrf
                    @method('DELETE')

                    <x-danger-button class="min-h-11 rounded-xl px-5">
                        {{ __('Delete Staff') }}
                    </x-danger-button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>