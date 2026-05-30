<?php

namespace App\Http\Controllers;

use App\Models\Tva;
use App\Services\TvaRenumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TvaRenumberController extends Controller
{
    protected TvaRenumberService $service;

    public function __construct(TvaRenumberService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $selectedYear = (int) $request->query('year', now()->year);

        $preview = $this->service->preview($selectedYear);

        // Distinct years derived from facture_date only.
        $years = Tva::withoutTrashed()
            ->whereNotNull('facture_date')
            ->selectRaw('YEAR(facture_date) as y')
            ->groupBy('y')
            ->orderByDesc('y')
            ->pluck('y');

        return Inertia::render('Tva/Renumber', [
            'preview'      => $preview,
            'selectedYear' => $selectedYear,
            'years'        => $years->values(),
        ]);
    }

    public function apply(Request $request)
    {
        $maxYear = now()->year + 1;
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:' . $maxYear,
        ]);

        try {
            $result = $this->service->renumber((int) $data['year']);

            return back()->with('success', __('Renumbered :n invoices for :y', [
                'n' => $result['updated'],
                'y' => $result['year'],
            ]));
        } catch (\Throwable $e) {
            Log::error('TVA renumber failed', [
                'year'  => $data['year'],
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', __('Renumbering failed: ') . $e->getMessage());
        }
    }

    public function previewJson(Request $request)
    {
        $maxYear = now()->year + 1;
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:' . $maxYear,
        ]);

        return response()->json($this->service->preview((int) $data['year']));
    }
}
