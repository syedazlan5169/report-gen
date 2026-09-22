@php
    $isAdmin = (string) old('is_admin', $targetUser->is_admin ? '1' : '0');
@endphp

<div>
    <x-input-label for="username" :value="__('Username')" />
    <x-text-input id="username" name="username" type="text" class="mt-1 block min-h-12 w-full rounded-xl text-base" :value="old('username', $targetUser->username)" required autofocus />
    <x-input-error class="mt-2" :messages="$errors->get('username')" />
</div>

<div>
    <x-input-label for="password" :value="$targetUser->exists ? __('New Password (optional)') : __('Password')" />
    <x-text-input id="password" name="password" type="password" class="mt-1 block min-h-12 w-full rounded-xl text-base" autocomplete="new-password" />
    <x-input-error class="mt-2" :messages="$errors->get('password')" />
</div>

<div>
    <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block min-h-12 w-full rounded-xl text-base" autocomplete="new-password" />
    <x-input-error class="mt-2" :messages="$errors->get('password_confirmation')" />
</div>

<div class="space-y-3 rounded-xl bg-slate-50 p-4 ring-1 ring-slate-200">
    <label for="is_admin" class="flex min-h-12 items-center justify-between gap-4">
        <span>
            <span class="block text-sm font-medium text-slate-900">Admin</span>
            <span class="block text-sm text-slate-500">Can manage other users</span>
        </span>
        <input type="hidden" name="is_admin" value="0">
        <input id="is_admin" name="is_admin" type="checkbox" value="1" @checked($isAdmin === '1') class="h-6 w-6 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
    </label>
    <x-input-error :messages="$errors->get('is_admin')" />
</div>
