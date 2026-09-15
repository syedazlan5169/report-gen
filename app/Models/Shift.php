<?php

namespace App\Models;

use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'code', 'display_name', 'start_time', 'end_time', 'is_active'])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function startTimeForInput(): string
    {
        return $this->timeForInput($this->start_time);
    }

    public function endTimeForInput(): string
    {
        return $this->timeForInput($this->end_time);
    }

    public function timeRangeForDisplay(): string
    {
        return $this->timeForDisplay($this->start_time).' ~ '.$this->timeForDisplay($this->end_time);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    private function timeForInput(?string $time): string
    {
        return $time === null ? '' : substr($time, 0, 5);
    }

    private function timeForDisplay(?string $time): string
    {
        if ($time === null) {
            return '';
        }

        return str_replace(':', '', substr($time, 0, 5)).'HRS';
    }
}
