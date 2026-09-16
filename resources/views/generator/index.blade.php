<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-6 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-md space-y-6">
            <header class="space-y-2">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Daily Workflow</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950 sm:text-3xl">Generate Reports</h1>
            </header>

            @if ($activeShifts->isEmpty())
                <div class="rounded-2xl bg-amber-50 p-5 text-sm leading-6 text-amber-900 ring-1 ring-amber-200">
                    No active shifts are configured yet.
                    <a href="{{ route('shifts.index') }}" class="font-semibold underline">Add a shift</a>
                </div>
            @endif

            @if ($activeShifts->isNotEmpty())
                <form method="POST" action="{{ route('generator.generate') }}" class="space-y-6">
                    @csrf

                    <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                        <h2 class="mb-3 text-lg font-semibold text-slate-900">Shift</h2>

                        <div class="space-y-3">
                            @foreach ($activeShifts as $shift)
                                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left transition hover:border-sky-200 hover:bg-sky-50">
                                    <div class="flex items-center gap-3">
                                        <input type="radio" name="shift_id" value="{{ $shift->id }}" class="h-4 w-4 border-slate-300 text-sky-600 focus:ring-sky-500" @checked((string) $selectedShiftId === (string) $shift->id)>
                                        <span class="font-medium text-slate-900">{{ $shift->display_name }}</span>
                                    </div>
                                    <span class="text-sm font-medium text-slate-600">{{ $shift->timeRangeForDisplay() }}</span>
                                </label>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">Cuti/Kursus</h2>
                            @if ($baseStaff->isEmpty())
                                <span class="text-xs font-medium text-amber-700">No base staff</span>
                            @endif
                        </div>

                        @if ($baseStaff->isEmpty())
                            <p class="rounded-xl bg-slate-100 px-3 py-2 text-sm text-slate-600">No active base staff are available. Add staff in <a href="{{ route('staff.index') }}" class="font-semibold underline">Staff</a>.</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($baseStaff as $staff)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-left transition hover:border-sky-200 hover:bg-sky-50">
                                        <input type="checkbox" name="leave_staff_ids[]" value="{{ $staff->id }}" class="mt-1 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" @checked(in_array($staff->id, $selectedLeaveIds, true))>
                                        <span>
                                            <span class="block font-medium text-slate-900">{{ $staff->short_code }}</span>
                                            <span class="block text-sm text-slate-600">{{ $staff->rank_prefix }} {{ $staff->staff_number }} - {{ $staff->name }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <section class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">Lembur</h2>
                            @if ($overtimeStaff->isEmpty())
                                <span class="text-xs font-medium text-slate-500">Unavailable</span>
                            @endif
                        </div>

                        @if ($overtimeStaff->isEmpty())
                            <p class="rounded-xl bg-slate-100 px-3 py-2 text-sm text-slate-600">No active non-base staff are available.</p>
                        @else
                            <div class="space-y-3">
                                @foreach ($overtimeStaff as $staff)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-left transition hover:border-sky-200 hover:bg-sky-50">
                                        <input type="checkbox" name="overtime_staff_ids[]" value="{{ $staff->id }}" class="mt-1 h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500" @checked(in_array($staff->id, $selectedOvertimeIds, true))>
                                        <span>
                                            <span class="block font-medium text-slate-900">{{ $staff->short_code }}</span>
                                            <span class="block text-sm text-slate-600">{{ $staff->rank_prefix }} {{ $staff->staff_number }} - {{ $staff->name }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    @if ($enabledTemplates->isNotEmpty() && $activeShifts->isNotEmpty())
                        <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-sky-600 px-4 text-base font-semibold text-white shadow-sm transition hover:bg-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                            Generate Reports
                        </button>
                    @elseif ($enabledTemplates->isEmpty())
                        <div class="rounded-2xl bg-slate-100 p-4 text-sm text-slate-600 ring-1 ring-slate-200">
                            No enabled report templates.
                            <a href="{{ route('report-templates.index') }}" class="font-semibold underline">Open Templates</a>
                        </div>
                    @endif
                </form>
            @endif

            @if ($generatedReports !== [])
                <div class="space-y-4">
                    @foreach ($generatedReports as $report)
                        <article class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h3 class="text-lg font-semibold text-slate-900">{{ $report['name'] }}</h3>
                                <button
                                    type="button"
                                    x-data="{ copied: false, text: @js($report['body']) }"
                                    @click.prevent="navigator.clipboard ? navigator.clipboard.writeText(text).then(() => { copied = true; setTimeout(() => copied = false, 1200); }) : (() => { const textarea = document.createElement('textarea'); textarea.value = text; document.body.appendChild(textarea); textarea.select(); document.execCommand('copy'); document.body.removeChild(textarea); copied = true; setTimeout(() => copied = false, 1200); })()"
                                    class="inline-flex min-h-10 items-center rounded-lg border border-slate-200 bg-slate-100 px-3 text-sm font-medium text-slate-700 transition hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500"
                                >
                                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                                </button>
                            </div>

                            <pre class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">{{ $report['body'] }}</pre>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
