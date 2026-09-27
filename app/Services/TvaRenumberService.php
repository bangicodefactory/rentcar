<?php

namespace App\Services;

use App\Models\Tva;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TvaRenumberService
{
    /**
     * Build a read-only preview of the renumbering operation for a given
     * year (based on facture_date), optionally limited to one tenant
     * (null = every tenant, super-admin use). No data is mutated.
     *
     * Sent invoices (handed to a client) keep their number; the others take
     * the free numbers around them in date order. `conflicts` lists what
     * cannot be done without breaking date order or leaving a gap — apply
     * refuses while any exist.
     *
     * @return array{year:int,count:int,changes:int,conflicts:string[],records:array<int,array{id:int,old_number:?string,new_number:string,date:?string,sent:bool}>}
     */
    public function preview(int $year, ?int $parentId = null): array
    {
        $plan = $this->plan($this->rows($year, $parentId)->get());

        return ['year' => $year] + $plan;
    }

    /**
     * Apply renumbering inside a single transaction. Only rows whose number
     * changes are saved (sent rows never change). Refuses — throws, nothing
     * written — when the plan has conflicts.
     *
     * @return array{year:int,updated:int,records:array<int,array{id:int,old_number:?string,new_number:string,date:?string,sent:bool}>}
     */
    public function renumber(int $year, ?int $parentId = null): array
    {
        return DB::transaction(function () use ($year, $parentId) {
            $rows = $this->rows($year, $parentId)->lockForUpdate()->get();
            $plan = $this->plan($rows);

            if ($plan['conflicts']) {
                throw new \RuntimeException(implode(' ', $plan['conflicts']));
            }

            $byId = $rows->keyBy('id');
            foreach ($plan['records'] as $record) {
                if ((string) $record['old_number'] === $record['new_number']) {
                    continue;
                }
                $tva = $byId[$record['id']];
                $tva->facture_number = $record['new_number'];
                $tva->updated_at = now();
                $tva->save();
            }

            Log::info('TVA renumber completed', [
                'year'    => $year,
                'updated' => $plan['changes'],
            ]);

            return [
                'year'    => $year,
                'updated' => $plan['changes'],
                'records' => $plan['records'],
            ];
        });
    }

    private function rows(int $year, ?int $parentId)
    {
        return Tva::withoutTrashed()
            ->when($parentId !== null, fn ($q) => $q->where('parent_id', $parentId))
            ->forYear($year)
            ->orderBy('facture_date', 'asc')
            ->orderBy('id', 'asc');
    }

    /**
     * Walk the year day by day. Within a day, a sent invoice sits exactly at
     * its own number; unsent invoices of that day fill the numbers around it
     * (in id order). A sent number lower than the running counter would break
     * date order; one higher than what the same day can fill leaves a gap —
     * both are conflicts.
     */
    private function plan(Collection $rows): array
    {
        $assigned = [];   // id => new number
        $conflicts = [];
        $sentNumbers = [];
        $c = 1;

        foreach ($rows->groupBy(fn ($t) => $t->facture_date?->toDateString()) as $date => $day) {
            $sent = $day->filter(fn ($t) => $t->sent_at !== null)
                ->sortBy(fn ($t) => (int) $t->facture_number)->values();
            $unsent = $day->filter(fn ($t) => $t->sent_at === null)->values();
            $si = 0;
            $ui = 0;

            while ($si < $sent->count() || $ui < $unsent->count()) {
                if ($si < $sent->count()) {
                    $row = $sent[$si];
                    $raw = (string) $row->facture_number;

                    if (!ctype_digit($raw)) {
                        $conflicts[] = __('Sent invoice :n (:d) has a non-numeric number.', ['n' => $raw, 'd' => $date]);
                        $assigned[$row->id] = $raw;
                        $si++;
                        continue;
                    }

                    $s = (int) $raw;
                    if (isset($sentNumbers[$s])) {
                        $conflicts[] = __('Two sent invoices share number #:n.', ['n' => $s]);
                    }
                    $sentNumbers[$s] = true;

                    if ($s === $c) {
                        $assigned[$row->id] = $raw;   // exact stored text, never reformatted
                        $c++;
                        $si++;
                        continue;
                    }
                    if ($s < $c) {
                        $conflicts[] = __('Sent invoice #:n (:d) would come after invoices already numbered up to :m: renumbering would break date order.', ['n' => $s, 'd' => $date, 'm' => $c - 1]);
                        $assigned[$row->id] = $raw;   // exact stored text, never reformatted
                        $si++;
                        continue;
                    }
                    // $s > $c: fill the free numbers before it with this day's unsent rows.
                    if ($ui < $unsent->count()) {
                        $assigned[$unsent[$ui]->id] = (string) $c;
                        $c++;
                        $ui++;
                        continue;
                    }
                    $conflicts[] = __('Numbers :from to :to cannot be filled before sent invoice #:n (:d) without breaking date order.', ['from' => $c, 'to' => $s - 1, 'n' => $s, 'd' => $date]);
                    $assigned[$row->id] = $raw;   // exact stored text, never reformatted
                    $c = $s + 1;
                    $si++;
                    continue;
                }

                // Only unsent rows left today. If this takes a number a
                // later-dated sent invoice holds, that invoice is reported as
                // a date-order conflict when its day is reached.
                $assigned[$unsent[$ui]->id] = (string) $c;
                $c++;
                $ui++;
            }
        }

        $records = [];
        $changes = 0;
        foreach ($rows as $t) {
            $new = $assigned[$t->id];
            if ((string) $t->facture_number !== $new) {
                $changes++;
            }
            $records[] = [
                'id'         => (int) $t->id,
                'old_number' => $t->facture_number,
                'new_number' => $new,
                'date'       => $t->facture_date ? $t->facture_date->format('d/m/Y') : null,
                'sent'       => $t->sent_at !== null,
            ];
        }

        return [
            'count'     => count($records),
            'changes'   => $changes,
            'conflicts' => array_values(array_unique($conflicts)),
            'records'   => $records,
        ];
    }
}
