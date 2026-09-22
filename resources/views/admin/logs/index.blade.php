<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-6 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-md space-y-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Admin</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Activity Logs</h1>
            </div>

            @if ($logs->isEmpty())
                <div class="rounded-2xl bg-white p-5 text-sm leading-6 text-slate-600 shadow-sm ring-1 ring-slate-200">
                    No activity has been logged yet.
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($logs as $log)
                        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0 space-y-1">
                                    <p class="break-words text-sm font-semibold text-slate-800">{{ $log->user?->username ?? 'Deleted user' }}</p>
                                    @if ($log->description)
                                        <p class="text-sm text-slate-600">{{ $log->description }}</p>
                                    @endif
                                    <p class="text-xs text-slate-400">{{ $log->created_at->format('Y-m-d H:i:s') }}</p>
                                </div>

                                <span @class([
                                    'shrink-0 rounded-full px-3 py-1 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $log->action === \App\Models\ActivityLog::ACTION_LOGIN,
                                    'bg-slate-200 text-slate-700' => $log->action === \App\Models\ActivityLog::ACTION_LOGOUT,
                                    'bg-sky-100 text-sky-800' => $log->action === \App\Models\ActivityLog::ACTION_REPORT_GENERATED,
                                ])>
                                    {{ str($log->action)->headline() }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div>
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
