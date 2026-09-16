<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-6 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-md space-y-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Configuration</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Staff</h1>
                </div>

                <a href="{{ route('staff.create') }}" class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    + Add Staff
                </a>
            </div>

            @if (session('status'))
                <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
                    @if (session('status') === 'staff-created')
                        Staff member added.
                    @elseif (session('status') === 'staff-updated')
                        Staff member updated.
                    @elseif (session('status') === 'staff-deleted')
                        Staff member deleted.
                    @endif
                </div>
            @endif

            @if (session('import-summary'))
                @php($summary = session('import-summary'))
                <div class="rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-200">
                    <p class="font-semibold">Imported {{ $summary['imported'] }} staff.</p>
                    <p class="mt-1">Skipped {{ $summary['skipped'] }} existing records.</p>
                    @if (! empty($summary['items']))
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($summary['items'] as $item)
                                <li>{{ $item['label'] }} — {{ $item['reason'] }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif

            <div class="flex gap-2">
                <a href="{{ route('staff.import.create') }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                    Import
                </a>
                <a href="{{ route('staff.export') }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    Export
                </a>
            </div>

            @if ($staff->isEmpty())
                <div class="rounded-2xl bg-white p-5 text-sm leading-6 text-slate-600 shadow-sm ring-1 ring-slate-200">
                    No staff have been added yet.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($staff as $staffMember)
                        <a href="{{ route('staff.edit', $staffMember) }}" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                            <div class="flex min-h-20 items-start justify-between gap-4">
                                <div class="min-w-0 space-y-1">
                                    <p class="text-xl font-semibold tracking-tight text-slate-950">{{ $staffMember->short_code }}</p>
                                    <p class="break-words text-base font-semibold text-slate-800">{{ $staffMember->name }}</p>
                                    <p class="text-sm text-slate-600">{{ $staffMember->rank_prefix }} {{ $staffMember->staff_number }}</p>
                                </div>

                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    @if ($staffMember->is_base_member)
                                        <span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800">Base</span>
                                    @endif

                                    @if ($staffMember->is_active)
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