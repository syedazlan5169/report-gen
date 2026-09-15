<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-6 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-md space-y-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Configuration</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Shifts</h1>
                </div>

                <a href="{{ route('shifts.create') }}" class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    + Add Shift
                </a>
            </div>

            @if (session('status'))
                <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
                    @if (session('status') === 'shift-created')
                        Shift added.
                    @elseif (session('status') === 'shift-updated')
                        Shift updated.
                    @elseif (session('status') === 'shift-deleted')
                        Shift deleted.
                    @endif
                </div>
            @endif

            @if ($shifts->isEmpty())
                <div class="rounded-2xl bg-white p-5 text-sm leading-6 text-slate-600 shadow-sm ring-1 ring-slate-200">
                    No shifts have been added yet.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($shifts as $shift)
                        <a href="{{ route('shifts.edit', $shift) }}" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                            <div class="flex min-h-24 items-start justify-between gap-4">
                                <div class="min-w-0 space-y-1">
                                    <p class="text-xl font-semibold tracking-tight text-slate-950">{{ strtoupper($shift->code) }}</p>
                                    <p class="break-words text-base font-semibold text-slate-800">{{ $shift->display_name }}</p>
                                    <p class="text-sm font-medium text-slate-600">{{ $shift->timeRangeForDisplay() }}</p>
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    @if ($shift->is_active)
                                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Active</span>
                                    @else
                                        <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">Inactive</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>