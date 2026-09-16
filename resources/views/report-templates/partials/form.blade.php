@php
    $isEnabled = (string) old('is_enabled', $template->exists ? ($template->is_enabled ? '1' : '0') : '1');
@endphp

<div>
    <x-input-label for="name" :value="__('Template Name')" />
    <x-text-input id="name" name="name" type="text" maxlength="150" class="mt-1 block min-h-12 w-full rounded-xl text-base" :value="old('name', $template->name)" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div
    x-data="{
        bodyHasFocused: false,
        insertPlaceholder(placeholder) {
            const textarea = this.$refs.body;
            const start = this.bodyHasFocused ? textarea.selectionStart : textarea.value.length;
            const end = this.bodyHasFocused ? textarea.selectionEnd : textarea.value.length;

            textarea.setRangeText(placeholder, start, end, 'end');
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.focus();
        },
    }"
    class="space-y-5"
>
    <div>
        <x-input-label for="body" :value="__('Template Body')" />
        <textarea id="body" name="body" rows="16" required x-ref="body" @focus="bodyHasFocused = true" class="mt-1 block min-h-80 w-full rounded-xl border-gray-300 font-mono text-sm leading-6 shadow-sm focus:border-sky-500 focus:ring-sky-500">{{ old('body', $template->body) }}</textarea>
        <x-input-error class="mt-2" :messages="$errors->get('body')" />
    </div>

    <div class="space-y-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
        <div class="space-y-1">
            <p class="text-sm font-medium text-slate-900">Available placeholders</p>
            <p class="text-sm leading-6 text-slate-500">Tap a placeholder to insert it at the cursor.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach (\App\Support\ReportTemplatePlaceholders::supported() as $placeholder)
                @php $placeholderToken = '{{'.$placeholder.'}}'; @endphp
                <button type="button" @click="insertPlaceholder(@js($placeholderToken))" class="inline-flex min-h-10 items-center rounded-lg bg-white px-3 py-2 font-mono text-sm font-medium text-slate-700 ring-1 ring-slate-200 transition hover:bg-sky-50 hover:text-sky-800 focus:outline-none focus:ring-2 focus:ring-sky-500">
                    {{ $placeholderToken }}
                </button>
            @endforeach
        </div>
        <p class="text-sm leading-6 text-slate-500">Use a blank order to place this template after existing templates.</p>
    </div>
</div>

<div>
    <x-input-label for="sort_order" :value="__('Order')" />
    <x-text-input id="sort_order" name="sort_order" type="number" min="1" inputmode="numeric" class="mt-1 block min-h-12 w-full rounded-xl text-base" :value="old('sort_order', $template->sort_order)" />
    <x-input-error class="mt-2" :messages="$errors->get('sort_order')" />
</div>

<div class="space-y-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
    <label for="is_enabled" class="flex min-h-12 items-center justify-between gap-4">
        <span>
            <span class="block text-sm font-medium text-slate-900">Enabled</span>
            <span class="block text-sm text-slate-500">Include this template in later report generation</span>
        </span>
        <input type="hidden" name="is_enabled" value="0">
        <input id="is_enabled" name="is_enabled" type="checkbox" value="1" @checked($isEnabled === '1') class="h-6 w-6 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
    </label>
    <x-input-error :messages="$errors->get('is_enabled')" />
</div>