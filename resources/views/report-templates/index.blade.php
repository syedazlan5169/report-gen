<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-6 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-md space-y-5">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Configuration</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Report Templates</h1>
                </div>

                <a href="{{ route('report-templates.create') }}" class="inline-flex min-h-11 items-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    + Add Template
                </a>
            </div>

            @if (session('status'))
                <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 ring-1 ring-emerald-200">
                    @if (session('status') === 'report-template-created')
                        Report template added.
                    @elseif (session('status') === 'report-template-updated')
                        Report template updated.
                    @elseif (session('status') === 'report-template-deleted')
                        Report template deleted.
                    @endif
                </div>
            @endif

            @if (session('import-summary'))
                @php($summary = session('import-summary'))
                <div class="rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-900 ring-1 ring-sky-200">
                    <p class="font-semibold">Imported {{ $summary['imported'] }} templates.</p>
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
                <a href="{{ route('report-templates.import.create') }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                    Import
                </a>
                <a href="{{ route('report-templates.export') }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-xl bg-slate-950 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    Export
                </a>
            </div>

            @if ($templates->isEmpty())
                <div class="rounded-2xl bg-white p-5 text-sm leading-6 text-slate-600 shadow-sm ring-1 ring-slate-200">
                    No report templates have been added yet.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($templates as $template)
                        <a href="{{ route('report-templates.edit', $template) }}" class="block rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2">
                            <div class="space-y-3">
                                <div class="flex items-start justify-between gap-4">
                                    <p class="min-w-0 break-words text-xl font-semibold tracking-tight text-slate-950">{{ $template->name }}</p>
                                    <div class="flex shrink-0 flex-col items-end gap-2">
                                        @if ($template->is_enabled)
                                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">Enabled</span>
                                        @else
                                            <span class="rounded-full bg-slate-200 px-3 py-1 text-xs font-semibold text-slate-700">Disabled</span>
                                        @endif
                                        <span class="text-xs font-medium text-slate-500">Order {{ $template->sort_order }}</span>
                                    </div>
                                </div>

                                <p class="whitespace-pre-wrap break-words text-sm leading-6 text-slate-600">{{ $template->body }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>