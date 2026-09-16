<x-app-layout>
    <div class="min-h-[calc(100vh-4rem)] bg-slate-50 px-4 py-6 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-md space-y-5">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-sky-700">Configuration</p>
                <h1 class="text-2xl font-semibold tracking-tight text-slate-950">Import Staff</h1>
            </div>

            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <p class="mb-4 text-sm leading-6 text-slate-600">
                    Existing conflicting records are skipped. Existing records are never overwritten.
                </p>

                <form method="POST" action="{{ route('staff.import.store') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf

                    <div>
                        <label for="file" class="mb-2 block text-sm font-medium text-slate-700">Choose JSON file</label>
                        <input id="file" name="file" type="file" accept="application/json,.json,text/plain" class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/20" required>
                        @error('file')
                            <p class="mt-2 text-sm font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-2">
                        <a href="{{ route('staff.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                            Cancel
                        </a>

                        <x-primary-button class="min-h-11 rounded-xl px-5">
                            Import Staff
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
