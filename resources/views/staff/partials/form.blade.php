@php
    $isBaseMember = (string) old('is_base_member', $staffMember->is_base_member ? '1' : '0');
    $isActive = (string) old('is_active', $staffMember->exists ? ($staffMember->is_active ? '1' : '0') : '1');
@endphp

<div>
    <x-input-label for="short_code" :value="__('Short Code')" />
    <x-text-input id="short_code" name="short_code" type="text" class="mt-1 block min-h-12 w-full rounded-xl text-base uppercase" :value="old('short_code', $staffMember->short_code)" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('short_code')" />
</div>

<div>
    <x-input-label for="rank_prefix" :value="__('Rank / Prefix')" />
    <x-text-input id="rank_prefix" name="rank_prefix" type="text" class="mt-1 block min-h-12 w-full rounded-xl text-base" :value="old('rank_prefix', $staffMember->rank_prefix)" required />
    <x-input-error class="mt-2" :messages="$errors->get('rank_prefix')" />
</div>

<div>
    <x-input-label for="staff_number" :value="__('Staff Number')" />
    <x-text-input id="staff_number" name="staff_number" type="number" min="1" inputmode="numeric" class="mt-1 block min-h-12 w-full rounded-xl text-base" :value="old('staff_number', $staffMember->staff_number)" required />
    <x-input-error class="mt-2" :messages="$errors->get('staff_number')" />
</div>

<div>
    <x-input-label for="name" :value="__('Name')" />
    <x-text-input id="name" name="name" type="text" class="mt-1 block min-h-12 w-full rounded-xl text-base" :value="old('name', $staffMember->name)" required />
    <x-input-error class="mt-2" :messages="$errors->get('name')" />
</div>

<div class="space-y-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
    <label for="is_base_member" class="flex min-h-12 items-center justify-between gap-4">
        <span>
            <span class="block text-sm font-medium text-slate-900">Base Member</span>
            <span class="block text-sm text-slate-500">Normal default team member</span>
        </span>
        <input type="hidden" name="is_base_member" value="0">
        <input id="is_base_member" name="is_base_member" type="checkbox" value="1" @checked($isBaseMember === '1') class="h-6 w-6 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
    </label>
    <x-input-error :messages="$errors->get('is_base_member')" />

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