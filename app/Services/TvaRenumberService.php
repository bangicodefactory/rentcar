<?php

namespace App\Services;

use App\Models\Tva;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TvaRenumberService
{
    /**
     * Build a read-only preview of the renumbering operation for a given
     * year (based on facture_date), optionally limited to one tenant
     * (null = every tenant, super-admin use). No data is mutated.
     *
     * @return array{year:int,count:int,records:array<int,array{id:int,old_number:?string,new_number:string,date:?string}>}
     */
    public function preview(int $year, ?int $parentId = null): array
    {
        $rows = Tva::withoutTrashed()
            ->when($parentId !== null, fn ($q) => $q->where('parent_id', $parentId))
            ->forYear($year)
            ->orderBy('facture_date', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'facture_number', 'facture_date']);

        $records = [];
        $i = 1;
        foreach ($rows as $row) {
            $records[] = [
                'id'         => (int) $row->id,
                'old_number' => $row->facture_number,
                'new_number' => (string) $i,
                'date'       => $row->facture_date ? $row->facture_date->format('d/m/Y') : null,
            ];
            $i++;
        }

        return [
            'year'    => $year,
            'count'   => count($records),
            'records' => $records,
        ];
    }

    /**
     * Apply renumbering inside a single transaction. Each row is saved
     * individually so model events still fire. Any exception bubbles up
     * and rolls back the transaction.
     *
     * @return array{year:int,updated:int,records:array<int,array{id:int,old_number:?string,new_number:string,date:?string}>}
     */
    public function renumber(int $year, ?int $parentId = null): array
    {
        return DB::transaction(function () use ($year, $parentId) {
            $rows = Tva::withoutTrashed()
                ->when($parentId !== null, fn ($q) => $q->where('parent_id', $parentId))
                ->forYear($year)
                ->orderBy('facture_date', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $records = [];
            $i = 1;
            foreach ($rows as $tva) {
                $oldNumber = $tva->facture_number;

                $tva->facture_number = (string) $i;
                $tva->updated_at = now();
                $tva->save();

                $records[] = [
                    'id'         => (int) $tva->id,
                    'old_number' => $oldNumber,
                    'new_number' => (string) $i,
                    'date'       => $tva->facture_date ? $tva->facture_date->format('d/m/Y') : null,
                ];
                $i++;
            }

            $count = count($records);

            Log::info('TVA renumber completed', [
                'year'    => $year,
                'updated' => $count,
            ]);

            return [
                'year'    => $year,
                'updated' => $count,
                'records' => $records,
            ];
        });
    }
}
