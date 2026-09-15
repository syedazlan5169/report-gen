<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShiftRequest;
use App\Models\Shift;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShiftController extends Controller
{
    public function index(Request $request): View
    {
        $shifts = $request->user()
            ->shifts()
            ->orderBy('start_time')
            ->orderBy('code')
            ->get();

        return view('shifts.index', ['shifts' => $shifts]);
    }

    public function create(): View
    {
        return view('shifts.create', [
            'shift' => new Shift(['is_active' => true]),
        ]);
    }

    public function store(ShiftRequest $request): RedirectResponse
    {
        $request->user()->shifts()->create($this->shiftAttributes($request, true));

        return redirect()->route('shifts.index')->with('status', 'shift-created');
    }

    public function edit(Request $request, string $shift): View
    {
        $shift = $request->user()->shifts()->findOrFail($shift);

        return view('shifts.edit', ['shift' => $shift]);
    }

    public function update(ShiftRequest $request, string $shift): RedirectResponse
    {
        $shift = $request->user()->shifts()->findOrFail($shift);

        $shift->update($this->shiftAttributes($request, false));

        return redirect()->route('shifts.edit', $shift)->with('status', 'shift-updated');
    }

    public function destroy(Request $request, string $shift): RedirectResponse
    {
        $shift = $request->user()->shifts()->findOrFail($shift);

        $shift->delete();

        return redirect()->route('shifts.index')->with('status', 'shift-deleted');
    }

    /**
     * @return array{code: string, display_name: string, start_time: string, end_time: string, is_active: bool}
     */
    private function shiftAttributes(ShiftRequest $request, bool $creating): array
    {
        $attributes = $request->safe()->only([
            'code',
            'display_name',
            'start_time',
            'end_time',
        ]);

        $attributes['is_active'] = $creating && ! $request->has('is_active')
            ? true
            : $request->boolean('is_active');

        return $attributes;
    }
}
