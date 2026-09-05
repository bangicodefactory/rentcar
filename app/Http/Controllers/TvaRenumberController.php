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
        if (!\Auth::user()->can('manage tva')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $selectedYear = (int) $request->query('year', now()->year);

        $preview = $this->service->preview($selectedYear, $this->tenantId());

        // Distinct years derived from facture_date only, within the tenant.
        $years = Tva::withoutTrashed()
            ->when($this->tenantId() !== null, fn ($q) => $q->where('parent_id', $this->tenantId()))
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
        if (!\Auth::user()->can('manage tva')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $maxYear = now()->year + 1;
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:' . $maxYear,
        ]);

        try {
            $result = $this->service->renumber((int) $data['year'], $this->tenantId());

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
        if (!\Auth::user()->can('manage tva')) {
            return response()->json(['message' => __('Permission Denied.')], 403);
        }

        $maxYear = now()->year + 1;
        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:' . $maxYear,
        ]);

        return response()->json($this->service->preview((int) $data['year'], $this->tenantId()));
    }
    /**
     * Tenant the renumber runs for: null (unscoped) for the super admin,
     * otherwise parentId(). A non-owner with no resolvable tenant gets 0,
     * which matches no rows.
     */
    private function tenantId(): ?int
    {
        return \Auth::user()->type === 'super admin' ? null : (int) parentId();
    }
}
