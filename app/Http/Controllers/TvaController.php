<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Tva;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Driver;
use App\Models\BookingPayment;
use Inertia\Inertia;

class TvaController extends Controller
{
    //
    public function index(Request $request)
    {
        if (!\Auth::user()->can('manage tva')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        // Base query scoped to current parent (tenant) and not soft deleted
        $query = Tva::whereNull('deleted_at');
        if (function_exists('parentId') && parentId()) {
            $query->where('parent_id', parentId());
        }

        // Unified filtering on facture_date (business date) instead of created_at
        if ($request->filled('from_date')) {
            $query->whereDate('facture_date', '>=', $request->get('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('facture_date', '<=', $request->get('to_date'));
        }
        if ($request->filled('filter_day')) {
            $query->whereDate('facture_date', $request->get('filter_day'));
        }
        if ($request->filled('filter_month')) {
            $query->whereMonth('facture_date', $request->get('filter_month'));
        }
        if ($request->filled('filter_year')) {
            $query->whereYear('facture_date', $request->get('filter_year'));
        }

        // Filter by driver name if provided: match on saved client_name or related booking driver's user name
        if ($request->filled('driver_name')) {
            $name = $request->get('driver_name');
            $query->where(function ($q) use ($name) {
                $q->where('client_name', 'like', "%{$name}%")
                    ->orWhereHas('booking.drivers', function ($q2) use ($name) {
                        $q2->where('name', 'like', "%{$name}%");
                    });
            });
        }

        // All matching ids (scoped, same filters) so the list's "select all" can
        // cover every page, not just the current 25, for bulk download.
        $allIds = (clone $query)->pluck('id');

        // Month view: when a specific month AND year are selected, show all of
        // that month's invoices on one page (cap 300) instead of paginating —
        // mirrors BookingController@index. Any other filter combo keeps the
        // default 25 per page.
        $monthYearSelected = $request->filled('filter_month') && $request->filled('filter_year');
        $perPage = $monthYearSelected ? 300 : 25;

        // F-21 (perf-audit): paginate server-side instead of loading every row
        // (the whole tenant result — 1k+ rows — was previously sent to the client).
        // withQueryString() keeps the active filters on the pagination links; the
        // id tiebreaker makes paging deterministic when facture_date ties (common).
        $tvas = $query->with(['booking', 'booking.drivers'])
            ->orderByDesc('facture_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Tva/Index', [
            'tvas' => $tvas->through(fn($t) => [
                'id'                => $t->id,
                'facture_number'    => $t->facture_number,
                'booking_id_display'=> $t->booking && isset($t->booking->booking_id)
                                        ? bookingPrefix() . $t->booking->booking_id
                                        : null,
                'driver_name'       => $t->client_name ?? optional(optional($t->booking)->drivers)->name,
                'designation'       => $t->designation,
                // Display in the tenant's configured System Date Format
                // (company_date_format) — the Blade list used dateFormat() here;
                // the port had hardcoded Y-m-d, ignoring the setting.
                'facture_date'      => $t->facture_date ? dateFormat($t->facture_date) : null,
                'montant_ttc'       => $t->montant_ttc,
                'payment_method'    => $t->payment_method,
            ]),
            'filters' => $request->only(['from_date', 'to_date', 'driver_name', 'filter_day', 'filter_month', 'filter_year']),
            'all_ids' => $allIds,
        ]);
    }
    public function create()
    {
        $books = Booking::where('parent_id', parentId())->get()->pluck('name', 'id');
        // $books->prepend(__('Select Vehicle'), '');


        return view('tva.create', compact('books'));
    }
    public function bulkDownload(Request $request)
    {
        if (!\Auth::user()->can('manage tva')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $request->validate([
            'invoice_ids' => 'required|array',
        ]);

        $invoices = $this->scopeToTenant(Tva::whereIn('id', $request->invoice_ids))->get();
        if ($invoices->isEmpty()) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        $zipFileName = 'invoices_' . now()->format('Ymd_His') . '.zip';
        $zipPath = storage_path("app/public/{$zipFileName}");
        $zip = new \ZipArchive;

        if ($zip->open($zipPath, \ZipArchive::CREATE) === TRUE) {
            foreach ($invoices as $invoice) {
                $items = [
                    (object) [
                        'description' => $invoice->designation,
                        'quantity' => $invoice->quantity,
                        'unit_price' => $invoice->unit_price_ht,
                        'total_ttc' => $invoice->montant_ttc,

                    ]
                ];
                $invoice->items = $items;

                // Fetch client ICE from related booking/driver
                $clientIce = null;
                try {
                    $bookingForInvoice = Booking::with('drivers')->find($invoice->booking_id);
                    if ($bookingForInvoice && $bookingForInvoice->driver) {
                        $driverRow = Driver::where('user_id', $bookingForInvoice->driver)->first();
                        $clientIce = $driverRow ? $driverRow->ICE_company : null;
                    }
                } catch (\Exception $e) {
                    // Ignore and leave $clientIce as null
                }

                $settings = settings();
                $logoFile = $settings['company_logo'] ?? '2_logo.png'; // Updated default logo name

                // Try multiple possible logo paths. The canonical location is the
                // `public` disk: storage/app/public/upload/logo/<file> (served to the
                // React templates via the public/storage symlink as
                // /storage/upload/logo/<file>). The previous list omitted it, so the
                // facture logo never resolved and the template printed the literal
                // "LOGO" placeholder. List it first; keep the legacy paths as fallbacks.
                $possiblePaths = [
                    storage_path('app/public/upload/logo/' . $logoFile),
                    public_path('storage/upload/logo/' . $logoFile),
                    storage_path('upload/logo/' . $logoFile),
                    storage_path('app/upload/logo/' . $logoFile),
                    public_path('storage/logo/' . $logoFile),
                    public_path('upload/logo/' . $logoFile),
                ];

                $logoPath = null;
                foreach ($possiblePaths as $path) {
                    if (file_exists($path) && is_readable($path)) {
                        $logoPath = $path;
                        break;
                    }
                }

                // Convert to base64 for DomPDF compatibility if logo exists
                $logoBase64 = null;
                if ($logoPath && file_exists($logoPath)) {
                    try {
                        $imageData = file_get_contents($logoPath);
                        $imageInfo = getimagesize($logoPath);
                        if ($imageData && $imageInfo) {
                            $mimeType = $imageInfo['mime'];
                            $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                            Log::info('Logo loaded successfully: ' . $logoPath . ' - Size: ' . strlen($imageData) . ' bytes');
                        }
                    } catch (Exception $e) {
                        // Log the error for debugging
                        Log::error('Logo loading error: ' . $e->getMessage() . ' for path: ' . $logoPath);
                        $logoBase64 = null;
                    }
                } else {
                    Log::info('No logo found. Searched paths: ' . implode(', ', $possiblePaths));
                }

                // Debug: Always log what we're passing to the template
                Log::info('LogoBase64 status: ' . ($logoBase64 ? 'Generated successfully' : 'NULL'));
                
                // Convert admin signature to base64 for DomPDF compatibility
                $signatureBase64 = null;
                if (!empty($settings['admin_signature'])) {
                    // Try multiple possible signature paths
                    $possibleSignaturePaths = [
                        storage_path('app/public/' . $settings['admin_signature']),
                        public_path('storage/' . $settings['admin_signature']),
                        base_path('public/storage/' . $settings['admin_signature']),
                        storage_path('app/' . $settings['admin_signature']),
                    ];
                    
                    $signaturePath = null;
                    foreach ($possibleSignaturePaths as $path) {
                        if (file_exists($path) && is_readable($path)) {
                            $signaturePath = $path;
                            break;
                        }
                    }
                    
                    if ($signaturePath && file_exists($signaturePath)) {
                        try {
                            $imageData = file_get_contents($signaturePath);
                            $imageInfo = getimagesize($signaturePath);
                            if ($imageData && $imageInfo) {
                                $mimeType = $imageInfo['mime'];
                                $signatureBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
                                Log::info('Signature loaded successfully: ' . $signaturePath);
                            }
                        } catch (Exception $e) {
                            Log::error('Signature loading error: ' . $e->getMessage() . ' for path: ' . $signaturePath);
                        }
                    } else {
                        Log::warning('Signature file not found. Searched paths: ' . implode(', ', $possibleSignaturePaths));
                    }
                }
                
                $ttcInWords = $this->numberToFrenchWords(floor($invoice->montant_ttc)) . ' dirhams';
                if (fmod($invoice->montant_ttc, 1) > 0) {
                    $ttcInWords .= ' et ' . round(fmod($invoice->montant_ttc, 1) * 100) . ' centimes';
                }
                // $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice', [
                //     'tva' => $invoice,
                //     'settings' => $settings,
                //     'logoPath' => $logoPath,
                // ]);
                // $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice2', [
                //     'tva' => $invoice,
                //     'settings' => $settings,
                //     'logoPath' => $logoPath,
                // ]);
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.invoice1', [
                    'tva' => $invoice,
                    'settings' => $settings,
                    'logoPath' => $logoBase64,
                    'ttcInWords' => $ttcInWords,
                    'clientIce' => $clientIce,
                    'signaturePath' => $signatureBase64,
                ]);
                $pdfContent = $pdf->output();
                $fileName = 'invoice_' . $invoice->facture_number . '.pdf';
                $zip->addFromString($fileName, $pdfContent);
            }
            $zip->close();
        }

        return response()->download($zipPath)->deleteFileAfterSend(true);
    }
    public function edit($id)
    {
        if (!\Auth::user()->can('manage tva')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $tva = $this->scopeToTenant(Tva::query())->findOrFail($id);

        return Inertia::render('Tva/Edit', [
            'tva' => [
                'id'            => $tva->id,
                'booking_id'    => $tva->booking_id,
                'designation'   => $tva->designation,
                'facture_number'=> $tva->facture_number,
                'facture_date'  => $tva->facture_date?->format('Y-m-d'),
                'quantity'      => $tva->quantity,
                'unit_price_ht' => $tva->unit_price_ht,
                'total_ht'      => $tva->total_ht,
                'tva'           => $tva->tva,
                'montant_ttc'   => $tva->montant_ttc,
            ],
        ]);
    }


    public function update(Request $request, $id)
    {
        if (!\Auth::user()->can('manage tva')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $validated = $request->validate([
            'facture_date' => 'required|date',
            'montant_ttc' => 'required|numeric',
            'unit_price_ht' => 'required|numeric',
            'tva' => 'required|numeric',
            'facture_number' => 'required|string|max:255',
        ]);

        $tva = $this->scopeToTenant(Tva::query())->findOrFail($id);

        $tva->facture_date = $validated['facture_date'];
        $tva->montant_ttc = $validated['montant_ttc'];
        $tva->unit_price_ht = $validated['unit_price_ht'];
        $tva->tva = $validated['tva'];
        $tva->facture_number = $validated['facture_number'];
        $tva->total_ht = $request->total_ht;


        $tva->save();

    return redirect()->route('tva.index')->with('success', 'TVA updated successfully.');
    }


    public function show($id)
    {
        if (!\Auth::user()->can('manage tva')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $tva = $this->scopeToTenant(Tva::query())->findOrFail($id);

        return Inertia::render('Tva/Show', [
            'tva' => [
                'id'             => $tva->id,
                'facture_number' => $tva->facture_number,
                'facture_date'   => $tva->facture_date?->format('Y-m-d'),
                'client_name'    => $tva->client_name,
                'quantity'       => $tva->quantity,
                'unit_price_ht'  => $tva->unit_price_ht,
                'total_ht'       => $tva->total_ht,
                'tva'            => $tva->tva,
                'montant_ttc'    => $tva->montant_ttc,
                'designation'    => $tva->designation,
                'payment_method' => $tva->payment_method,
            ],
        ]);
    }
    public function destroy($id)
    {
        if (!\Auth::user()->can('manage tva')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $tva = $this->scopeToTenant(Tva::query())->findOrFail($id);
        $tva->delete();
        return redirect()->back()->with('success', 'The TVA has been deleted.');
    }
    protected function numberToFrenchWords($num)
    {
        if (!is_numeric($num)) {
            throw new \Exception('Le nombre doit être numérique');
        }

        $num = (float) $num;
        if ($num < 0 || $num > 999999999999) {
            throw new \Exception('Le nombre doit être entre 0 et 999 999 999 999');
        }

        if ($num === 0) {
            return 'Zéro';
        }

        $units = [
            '',
            'un',
            'deux',
            'trois',
            'quatre',
            'cinq',
            'six',
            'sept',
            'huit',
            'neuf',
            'dix',
            'onze',
            'douze',
            'treize',
            'quatorze',
            'quinze',
            'seize',
            'dix-sept',
            'dix-huit',
            'dix-neuf'
        ];

        $tens = [
            '',
            '',
            'vingt',
            'trente',
            'quarante',
            'cinquante',
            'soixante',
            'soixante',
            'quatre-vingt',
            'quatre-vingt'
        ];

        $convertUnder100 = function ($n) use ($units, $tens) {
            if ($n < 20) {
                return ucfirst($units[$n]);
            }

            $ten = floor($n / 10);
            $unit = $n % 10;

            if ($ten === 7) {
                return $unit === 1 ? 'Soixante-et-onze' : 'Soixante-' . ($unit === 0 ? 'dix' : $units[10 + $unit]);
            }
            if ($ten === 8) {
                return $unit === 0 ? 'Quatre-vingts' : 'Quatre-vingt-' . $units[$unit];
            }
            if ($ten === 9) {
                return $unit === 0 ? 'Quatre-vingt-dix' : 'Quatre-vingt-' . $units[10 + $unit];
            }

            return ucfirst($tens[$ten]) . ($unit === 0 ? '' : ($unit === 1 && $ten !== 8 && $ten !== 9 ? '-et-un' : '-' . $units[$unit]));
        };

        $convertUnder1000 = function ($n) use ($convertUnder100, $units) {
            if ($n === 0) {
                return '';
            }

            $hundreds = floor($n / 100);
            $remainder = $n % 100;
            $result = '';

            if ($hundreds > 0) {
                $result = $hundreds === 1 ? 'Cent' : ucfirst($units[$hundreds]) . ' cent';
                if ($remainder === 0 && $hundreds > 1) {
                    $result .= 's';
                }
            }

            if ($remainder > 0) {
                $result .= $result ? ' ' : '';
                $result .= $convertUnder100($remainder);
            }

            return $result;
        };

        $billions = floor($num / 1000000000);
        $millions = floor(($num % 1000000000) / 1000000);
        $thousands = floor(($num % 1000000) / 1000);
        $remainder = $num % 1000;

        $result = '';

        if ($billions > 0) {
            $result .= $convertUnder1000($billions) . ($billions === 1 ? ' milliard' : ' milliards');
        }

        if ($millions > 0) {
            $result .= $result ? ' ' : '';
            $result .= $convertUnder1000($millions) . ($millions === 1 ? ' million' : ' millions');
        }

        if ($thousands > 0) {
            $result .= $result ? ' ' : '';
            $result .= $thousands === 1 ? 'mille' : $convertUnder1000($thousands) . ' mille';
        }

        if ($remainder > 0) {
            $result .= $result ? ' ' : '';
            $result .= $convertUnder1000($remainder);
        }

        // Capitalize first letter of the entire result
        return ucfirst(strtolower($result));
    }

    public function generateMonthlyTva(Request $request)
    {
        if (!\Auth::user()->can('manage tva')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        // The leading "!" resets all unspecified fields (day, time) to their
        // defaults, so the day becomes 01. Without it, createFromFormat carries
        // *today's* day-of-month and a short target month overflows — e.g. on
        // the 30th, "2024-02" parses as 2024-02-30 → rolls over to 2024-03-01,
        // generating the wrong month.
        $monthStart = Carbon::createFromFormat('!Y-m', $request->month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        // Everything below runs in one transaction so a mid-loop failure can't
        // leave a month half-deleted / half-regenerated, and so the per-year
        // counter reads are serialised under lockForUpdate against a concurrent
        // generation. (Hard uniqueness still needs the DB unique index — IST-230.)
        return \DB::transaction(function () use ($monthStart, $monthEnd) {
        // 1. Delete existing TVA records in the selected month (facture_date within month)
        // Scoped to the caller's tenant (super admin unscoped): a month rebuild
        // must never soft-delete or re-issue another business's invoices.
        $deleteQuery = $this->scopeToTenant(Tva::whereYear('facture_date', $monthStart->year)
            ->whereMonth('facture_date', $monthStart->month));
        $deletedCount = $deleteQuery->count();
        $deleteQuery->delete();

        // 2. Pull BookingPayments in that month to build TVAs (per payment)
        $paymentQuery = $this->scopeToTenant(BookingPayment::whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()]));
        $payments = $paymentQuery->get();

        $setting = settings();
        $createdCount = 0;

        // Per-year numbering: invoice numbers reset to 1 at the start of each
        // year and continue from the highest number already issued *within the
        // selected year*. The current month's records were soft-deleted above,
        // so they don't count toward the running total when a month is
        // regenerated.
        //
        // The sequence is keyed by the BOOKING's parent_id (the business the
        // invoice belongs to), NOT the generating user's parentId(). This makes
        // numbering independent of who runs generation — an owner, an employee,
        // or a super admin all continue the same per-business sequence. A single
        // run can legitimately span multiple businesses, so we keep one counter
        // per parent_id, seeded lazily the first time each business is seen.
        //
        // NOTE: months generated out of order continue from the year's max
        // rather than slotting in chronologically — use the Renumber tool to
        // re-sequence a year by date afterwards.
        $factureCounters = [];
        // Memoise the (booking-scoped) due amount per booking so a booking with
        // several payments in the month isn't re-summed on every payment row.
        $dueByBooking = [];

        foreach ($payments as $payment) {
            $booking = Booking::with('drivers')->find($payment->booking_id);
            if (!$booking) {
                continue;
            }

            // Under the "invoice only when fully paid" policy, a payment whose
            // booking still has an outstanding balance gets no facture — skip it.
            // Round to cents (float amounts) so a residual isn't read as owing.
            if (feature('invoice_on_full_payment')) {
                $due = $dueByBooking[$booking->id] ??= round((float) $booking->getTotalDueAmount(), 2);
                if ($due > 0) {
                    continue;
                }
            }

            // Counter key = the business this invoice belongs to (may be null
            // for legacy bookings with no parent). Seed from that business's
            // own year-max the first time we encounter it.
            $bookingParentId = $booking->parent_id ?? null;
            $counterKey = $bookingParentId ?? '__null__';
            if (!array_key_exists($counterKey, $factureCounters)) {
                $factureCounters[$counterKey] = $this->lastFactureNumberForYear($monthStart->year, $bookingParentId);
            }

            // Driver / client
            $driverName = 'N/A';
            $driverAddress = '';
            if ($booking->drivers) {
                $driverName = $booking->drivers->name ?? 'N/A';
                $driver = Driver::where('user_id', $booking->driver)->first();
                $driverAddress = $driver->address ?? '';
            }

            // Vehicle snapshot
            $vd = $booking->vehicle_details;
            if (is_string($vd)) {
                $decoded = json_decode($vd, true);
                if (is_array($decoded)) {
                    $vd = $decoded;
                }
            }
            if (!is_array($vd)) {
                $vd = [];
            }
            $vehicleName = $vd['name'] ?? '';
            $vehicleLicensePlate = $vd['license_plate'] ?? '';

            // Facture days: prefer the per-payment invoice_days persisted at
            // payment time (a manual override or a cash-split share) so a monthly
            // rebuild reproduces the same Qty the live invoice used. Fall back to
            // the booking's full rental span for payments without a stored value.
            $totalDays = ($payment->invoice_days && $payment->invoice_days > 0)
                ? (int) $payment->invoice_days
                : (Carbon::parse($booking->start_date)->diffInDays(Carbon::parse($booking->end_date)) ?: 1);

            // Financials based on payment amount (TTC) with fixed 20% VAT assumption
            $paymentTtc = (float)$payment->amount;
            $totalHt = round($paymentTtc / 1.2, 2);
            $tvaAmount = round($paymentTtc - $totalHt, 2);
            $unitPriceHt = $totalDays > 0 ? round($paymentTtc / $totalDays, 2) : $paymentTtc; // spread across days

            $factureCounters[$counterKey]++;
            $factureNumber = $factureCounters[$counterKey];

            $tva = new Tva();
            $tva->booking_id = $booking->id;
            $tva->parent_id = $bookingParentId;
            $tva->month = $monthStart->month;
            $tva->year = $monthStart->year;
            $tva->facture_number = $factureNumber;
            // Fall back to the generated month (not now()) so the invoice's year
            // always matches the counter's year; the BETWEEN query above already
            // excludes null-date payments, so this is purely defensive.
            $tva->facture_date = $payment->date ?? $monthStart->toDateString();
            $tva->client_name = $driverName;
            $tva->client_address = $driverAddress;
            $tva->company_name = $setting['company_name'];
            $tva->company_address = $setting['company_address'];
            $tva->designation = trim($vehicleName . (($vehicleName && $vehicleLicensePlate) ? ' - ' : '') . $vehicleLicensePlate);
            $tva->idpaiment = $payment->id;
            $tva->quantity = number_format($totalDays, 2, '.', '');
            $tva->unit_price_ht = number_format($unitPriceHt, 2, '.', '');
            $tva->total_ht = number_format($totalHt, 2, '.', '');
            $tva->tva = number_format($tvaAmount, 2, '.', '');
            $tva->montant_ttc = number_format($paymentTtc, 2, '.', '');
            $tva->ice_number = $setting['ice'];
            $tva->rc_number = $setting['rc'];
            $tva->nif_number = $setting['if'];
            // `generated_date` is a NOT NULL timestamp in the DB schema.
            $tva->generated_date = $payment->date ?? now();
            $tva->total_amount = number_format($paymentTtc, 2, '.', '');
            $tva->tva_amount = number_format($tvaAmount, 2, '.', '');
            $tva->payment_method = $payment->payment_method ?? 'Espece';
            // DB column is required (no default); keep consistent with seeder usage.
            $tva->status = 1;
            $tva->save();
            $createdCount++;
        }

        return redirect()->back()->with('success', "{$deletedCount} TVA supprimées. {$createdCount} TVA(s) générées pour " . $monthStart->format('F Y'));
        });
    }

    /**
     * Highest invoice number already issued within the given year for one
     * business (parent_id). Returns 0 when that business has no invoices in the
     * year, so its first invoice of a fresh year becomes 1. A null $parentId
     * matches legacy rows that were never assigned a parent.
     *
     * Scoped by the BOOKING's parent_id (passed in), never the session user, so
     * numbering does not depend on who triggers generation. lockForUpdate
     * serialises the read against a concurrent generation inside the same
     * transaction (best-effort; the DB unique index in IST-230 is the real
     * guarantee). Trailing digits are extracted so legacy/manually-edited
     * numbers like "FACT-12" still participate; the max is computed in PHP
     * because the column is a string and won't sort numerically in SQL.
     */
    private function lastFactureNumberForYear(int $year, $parentId): int
    {
        $numbers = Tva::query()
            ->where(fn ($q) => $parentId === null
                ? $q->whereNull('parent_id')
                : $q->where('parent_id', $parentId))
            ->whereYear('facture_date', $year)
            ->lockForUpdate()
            ->pluck('facture_number');

        $max = 0;
        foreach ($numbers as $number) {
            if (preg_match('/\d+$/', (string) $number, $matches)) {
                $max = max($max, (int) $matches[0]);
            }
        }

        return $max;
    }

    public function report(Request $request)
    {
        if (!\Auth::user()->can('manage tva report')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        // Base query scoped to current parent (tenant) and not soft deleted
        $query = Tva::whereNull('deleted_at');
        if (function_exists('parentId') && parentId()) {
            $query->where('parent_id', parentId());
        }

        // Get current year for default filter
        $currentYear = now()->year;
        $selectedYear = $request->get('year', $currentYear);
        
        // Filter by year
        $query->whereYear('facture_date', $selectedYear);

        // Get all TVA records for the selected year
        $tvas = $query->with(['booking', 'booking.drivers'])->get();

        // Calculate monthly statistics
        $monthlyStats = [];
        $totalTvaAmount = 0;
        $totalHtAmount = 0;
        $totalTtcAmount = 0;

        for ($month = 1; $month <= 12; $month++) {
            $monthlyTvas = $tvas->filter(function ($tva) use ($month) {
                return Carbon::parse($tva->facture_date)->month == $month;
            });

            $monthTvaAmount = $monthlyTvas->sum('tva_amount');
            $monthHtAmount = $monthlyTvas->sum('total_ht');
            $monthTtcAmount = $monthlyTvas->sum('montant_ttc');
            $count = $monthlyTvas->count();

            $monthlyStats[$month] = [
                'month_name' => Carbon::create($selectedYear, $month, 1)->format('F'),
                'count' => $count,
                'tva_amount' => $monthTvaAmount,
                'ht_amount' => $monthHtAmount,
                'ttc_amount' => $monthTtcAmount,
            ];

            $totalTvaAmount += $monthTvaAmount;
            $totalHtAmount += $monthHtAmount;
            $totalTtcAmount += $monthTtcAmount;
        }

        // Calculate yearly statistics
        $yearlyStats = [
            'total_invoices' => $tvas->count(),
            'total_tva_amount' => $totalTvaAmount,
            'total_ht_amount' => $totalHtAmount,
            'total_ttc_amount' => $totalTtcAmount,
            'average_tva_per_month' => $totalTvaAmount / 12,
            'average_invoice_value' => $tvas->count() > 0 ? $totalHtAmount / $tvas->count() : 0,
        ];

        // Get top clients by TVA amount
        $topClients = $tvas->groupBy('client_name')
            ->map(function ($clientTvas) {
                return [
                    'client_name' => $clientTvas->first()->client_name,
                    'total_tva' => $clientTvas->sum('tva_amount'),
                    'total_ttc' => $clientTvas->sum('montant_ttc'),
                    'count' => $clientTvas->count(),
                ];
            })
            ->sortByDesc('total_tva')
            ->take(5);

        // Get car statistics
        $carStats = $tvas->filter(function ($tva) {
                return !empty($tva->designation);
            })
            ->groupBy('designation')
            ->map(function ($carTvas) {
                return [
                    'car_name' => $carTvas->first()->designation,
                    'rental_count' => $carTvas->count(),
                    'total_revenue_ht' => $carTvas->sum('total_ht'),
                    'total_revenue_ttc' => $carTvas->sum('montant_ttc'),
                    'total_tva' => $carTvas->sum('tva_amount'),
                    'average_rental_value_ht' => $carTvas->count() > 0 ? $carTvas->sum('total_ht') / $carTvas->count() : 0,
                    'total_rental_days' => $carTvas->sum('quantity'),
                ];
            })
            ->sortByDesc('rental_count');

        // Top 5 most rented cars
        $topRentedCars = $carStats->take(5);

        // Top 5 most profitable cars by revenue HT
        $topProfitableCars = $carStats->sortByDesc('total_revenue_ht')->take(5);

        // Car performance statistics
        $carPerformanceStats = [
            'total_unique_cars' => $carStats->count(),
            'most_rented_car' => $carStats->first(),
            'most_profitable_car' => $carStats->sortByDesc('total_revenue_ht')->first(),
            'average_rentals_per_car' => $carStats->count() > 0 ? $carStats->sum('rental_count') / $carStats->count() : 0,
            'total_rental_days' => $carStats->sum('total_rental_days'),
        ];

        // Prepare chart data
        $chartData = [
            'months' => array_values(array_column($monthlyStats, 'month_name')),
            'tva_amounts' => array_values(array_column($monthlyStats, 'tva_amount')),
            'ht_amounts' => array_values(array_column($monthlyStats, 'ht_amount')),
            'ttc_amounts' => array_values(array_column($monthlyStats, 'ttc_amount')),
            'counts' => array_values(array_column($monthlyStats, 'count')),
        ];

        // Get available years for dropdown
        $availableYears = Tva::selectRaw('YEAR(facture_date) as year')
            ->whereNull('deleted_at')
            ->when(function_exists('parentId') && parentId(), function ($q) {
                return $q->where('parent_id', parentId());
            })
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year');

        return Inertia::render('Tva/Report', [
            'monthlyStats'       => array_values($monthlyStats),
            'yearlyStats'        => $yearlyStats,
            'topClients'         => $topClients->values()->toArray(),
            'chartData'          => $chartData,
            'selectedYear'       => (int) $selectedYear,
            'availableYears'     => $availableYears->toArray(),
            'topRentedCars'      => $topRentedCars->values()->toArray(),
            'topProfitableCars'  => $topProfitableCars->values()->toArray(),
            'carPerformanceStats'=> $carPerformanceStats,
        ]);
    }
    /**
     * Constrain a tvas / booking_payments query to the caller's tenant.
     * Super admin is unscoped (parentId() returns the SA's own id, which is
     * never a tenant's parent_id). A non-owner with no resolvable tenant
     * matches nothing rather than everything - index/report's
     * `if (parentId())` guard silently dropped the filter in that case.
     */
    private function scopeToTenant($query)
    {
        if (\Auth::user()->type === 'super admin') {
            return $query;
        }

        $parentId = (int) parentId();
        if ($parentId <= 0) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('parent_id', $parentId);
    }
}
