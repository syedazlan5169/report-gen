<?php

namespace App\Http\Controllers;

use App\Http\Requests\StaffRequest;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $staff = $request->user()
            ->staff()
            ->orderBy('short_code')
            ->get();

        return view('staff.index', ['staff' => $staff]);
    }

    public function create(): View
    {
        return view('staff.create', [
            'staffMember' => new Staff(['is_active' => true]),
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $request->user()->staff()->create($this->staffAttributes($request, true));

        return redirect()->route('staff.index')->with('status', 'staff-created');
    }

    public function edit(Request $request, string $staff): View
    {
        $staffMember = $request->user()->staff()->findOrFail($staff);

        return view('staff.edit', ['staffMember' => $staffMember]);
    }

    public function update(StaffRequest $request, string $staff): RedirectResponse
    {
        $staffMember = $request->user()->staff()->findOrFail($staff);

        $staffMember->update($this->staffAttributes($request, false));

        return redirect()->route('staff.edit', $staffMember)->with('status', 'staff-updated');
    }

    public function destroy(Request $request, string $staff): RedirectResponse
    {
        $staffMember = $request->user()->staff()->findOrFail($staff);

        $staffMember->delete();

        return redirect()->route('staff.index')->with('status', 'staff-deleted');
    }

    /**
     * @return array{short_code: string, rank_prefix: string, staff_number: int, name: string, is_base_member: bool, is_active: bool}
     */
    private function staffAttributes(StaffRequest $request, bool $creating): array
    {
        $attributes = $request->safe()->only([
            'short_code',
            'rank_prefix',
            'staff_number',
            'name',
        ]);

        $attributes['is_base_member'] = $request->boolean('is_base_member');
        $attributes['is_active'] = $creating && ! $request->has('is_active')
            ? true
            : $request->boolean('is_active');

        return $attributes;
    }
}
