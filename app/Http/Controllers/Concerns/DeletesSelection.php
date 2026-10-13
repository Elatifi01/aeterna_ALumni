<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Deleting the rows a user ticked in a table (docs/project.md §10).
 */
trait DeletesSelection
{
    /**
     * Soft-delete every ticked row the user may delete and return how many went.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  the scoped query for this table
     * @return int how many rows actually went
     */
    protected function deleteSelection(Request $request, Builder $query): int
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct'],
        ], [
            'ids.required' => 'Selectionnez au moins une ligne.',
            'ids.max' => 'Supprimez au plus 500 lignes a la fois.',
        ]);

        $rows = $query->whereKey($validated['ids'])->get();

        return DB::transaction(function () use ($request, $rows): int {
            $deleted = 0;

            foreach ($rows as $row) {
                if (! $request->user('web')->can('delete', $row)) {
                    continue;
                }

                $row->delete();
                $deleted++;
            }

            return $deleted;
        });
    }

    /**
     * "3 alumni supprimes." — or the honest version when nothing matched.
     */
    protected function selectionDeletedMessage(int $count, string $one, string $many): string
    {
        return match (true) {
            $count === 0 => 'Aucune ligne de la selection n a pu etre supprimee.',
            $count === 1 => "1 {$one}.",
            default => "{$count} {$many}.",
        };
    }
}
