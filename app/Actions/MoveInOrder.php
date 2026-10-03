<?php

namespace App\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Moves a record one step up or down a list ordered by its "position" column by swapping places with its neighbour.
 */
class MoveInOrder
{
    /**
     * @return bool false when the record is already first (moving up) or last (moving down)
     */
    public function handle(Model $record, string $direction): bool
    {
        return DB::transaction(function () use ($record, $direction): bool {
            $up = $direction === 'up';

            $neighbour = $record->newQuery()
                ->where(fn ($query) => $query
                    ->where('position', $up ? '<' : '>', $record->position)
                    ->orWhere(fn ($query) => $query
                        ->where('position', $record->position)
                        ->where('id', $up ? '<' : '>', $record->getKey())))
                ->orderBy('position', $up ? 'desc' : 'asc')
                ->orderBy('id', $up ? 'desc' : 'asc')
                ->lockForUpdate()
                ->first();

            if ($neighbour === null) {
                return false;
            }

            [$mine, $theirs] = [$record->position, $neighbour->position];

            // equal positions (e.g. after imports) still need a real difference to swap
            if ($mine === $theirs) {
                $up ? $mine++ : $theirs++;
            }

            $record->forceFill(['position' => $theirs])->save();
            $neighbour->forceFill(['position' => $mine])->save();

            return true;
        });
    }

    /**
     * The position for a record added at the end of the list.
     *
     * @param  class-string<Model>  $model
     */
    public function nextPosition(string $model): int
    {
        return (int) $model::query()->max('position') + 1;
    }
}
