@php
    $isActive = (string) old('is_active', $shift->exists ? ($shift->is_active ? '1' : '0') : '1');
@endphp

<div>
    <x-input-label for="code" :value="__('Code')" />
    <x-text-input id="code" name="code" type="text" class="mt-1 block min-h-12 w-full rounded-xl text-base lowercase" :value="old('code', $shift->code)" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('code')" />
</div>

<div>
    <x-input-label for="display_name" :value="__('Display Name')" />
    <x-text-input id="display_name" name="display_name" type="text" class="mt-1 block min-h-12 w-full rounded-xl text-base" :value="old('display_name', $shift->display_name)" required />
    <x-input-error class="mt-2" :messages="$errors->get('display_name')" />
</div>

<div>
    <x-input-label for="start_time" :value="__('Start Time')" />
    <x-text-input id="start_time" name="start_time" type="time" class="mt-1 block min-h-12 w-full rounded-xl text-base" :value="old('start_time', $shift->startTimeForInput())" required />
    <x-input-error class="mt-2" :messages="$errors->get('start_time')" />
</div>

<div>
    <x-input-label for="end_time" :value="__('End Time')" />
    <x-text-input id="end_time" name="end_time" type="time" class="mt-1 block min-h-12 w-full rounded-xl text-base" :value="old('end_time', $shift->endTimeForInput())" required />
    <x-input-error class="mt-2" :messages="$errors->get('end_time')" />
</div>

<div class="space-y-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
    <label for="is_active" class="flex min-h-12 items-center justify-between gap-4">
        <span>
            <span class="block text-sm font-medium text-slate-900">Active</span>
            <span class="block text-sm text-slate-500">Available for later generator selection</span>
        </span>
        <input type="hidden" name="is_active" value="0">
        <input id="is_active" name="is_active" type="checkbox" value="1" @checked($isActive === '1') class="h-6 w-6 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
    </label>
    <x-input-error :messages="$errors->get('is_active')" />
</div>