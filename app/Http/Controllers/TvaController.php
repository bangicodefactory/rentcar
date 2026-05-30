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

        // Retrieve all (let DataTables handle client-side paging). If dataset grows large, switch to server-side.
        $tvas = $query->with(['booking', 'booking.drivers'])->orderByDesc('facture_date')->get();

        return Inertia::render('Tva/Index', [
            'tvas' => $tvas->map(fn($t) => [
                'id'                => $t->id,
                'facture_number'    => $t->facture_number,
                'booking_id_display'=> $t->booking && isset($t->booking->booking_id)
                                        ? bookingPrefix() . $t->booking->booking_id
                                        : null,
                'driver_name'       => $t->client_name ?? optional(optional($t->booking)->drivers)->name,
                'designation'       => $t->designation,
                'facture_date'      => $t->facture_date?->format('Y-m-d'),
                'montant_ttc'       => $t->montant_ttc,
            ]),
            'filters' => $request->only(['from_date', 'to_date', 'driver_name', 'filter_day', 'filter_month', 'filter_year']),
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
        $request->validate([
            'invoice_ids' => 'required|array',
        ]);

        $invoices = Tva::whereIn('id', $request->invoice_ids)->get();
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

                // Try multiple possible logo paths
                $possiblePaths = [
                    storage_path('upload/logo/' . $logoFile),
                    storage_path('app/upload/logo/' . $logoFile),
                    public_path('storage/logo/' . $logoFile),
                    public_path('upload/logo/' . $logoFile)
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
        $tva = Tva::findOrFail($id);
        $books = Booking::where('parent_id', parentId())->pluck('booking_id', 'id'); // id => booking_id

        $vehicles = Vehicle::all(); // or however you're getting the vehicle list

        $booking = Booking::find($tva->booking_id); // to get the selected booking

        return view('tva.edit', compact('tva', 'books', 'vehicles', 'booking'));
    }


    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'facture_date' => 'required|date',
            'montant_ttc' => 'required|numeric',
            'unit_price_ht' => 'required|numeric',
            'tva' => 'required|numeric',
            'facture_number' => 'required|string|max:255',
        ]);

        $tva = Tva::findOrFail($id);

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
        $tva = Tva::findOrFail($id);
        return view('tva.show', compact('tva'));
    }
    public function destroy($id)
    {
        $tva = Tva::findOrFail($id);
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
        $request->validate([
            'month' => 'required|date_format:Y-m',
        ]);

        $monthStart = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        // 1. Delete existing TVA records in the selected month (facture_date within month)
        $deleteQuery = Tva::whereYear('facture_date', $monthStart->year)
            ->whereMonth('facture_date', $monthStart->month);
        $deletedCount = $deleteQuery->count();
        $deleteQuery->delete();

        // 2. Pull BookingPayments in that month to build TVAs (per payment)
        $paymentQuery = BookingPayment::whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
        $payments = $paymentQuery->get();

        $setting = settings();
        $createdCount = 0;

        // Continue numbering globally
        $lastNumber = (int) $request->tva_number;
        
        if ($lastNumber <= 0) {
            $lastFacture = Tva::orderByDesc('id')->first();
            $lastNumber = 0;
            if ($lastFacture && preg_match('/\d+$/', $lastFacture->facture_number, $matches)) {
                $lastNumber = (int)$matches[0];
            }
        }

        $factureCounter = $lastNumber;

        foreach ($payments as $payment) {
            $booking = Booking::with('drivers')->find($payment->booking_id);
            if (!$booking) {
                continue;
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

            // Rental days (for unit price / quantity consistency with earlier logic)
            $startDate = Carbon::parse($booking->start_date);
            $endDate = Carbon::parse($booking->end_date);
            $totalDays = $startDate->diffInDays($endDate) ?: 1;

            // Financials based on payment amount (TTC) with fixed 20% VAT assumption
            $paymentTtc = (float)$payment->amount;
            $totalHt = round($paymentTtc / 1.2, 2);
            $tvaAmount = round($paymentTtc - $totalHt, 2);
            $unitPriceHt = $totalDays > 0 ? round($paymentTtc / $totalDays, 2) : $paymentTtc; // spread across days

            $factureCounter++;
            $factureNumber = $factureCounter;

            $tva = new Tva();
            $tva->booking_id = $booking->id;
            if (isset($booking->parent_id)) {
                $tva->parent_id = $booking->parent_id;
            }
            $tva->month = $monthStart->month;
            $tva->year = $monthStart->year;
            $tva->facture_number = $factureNumber;
            $tva->facture_date = $payment->date ?? now();
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

        return view('tva.report', compact(
            'monthlyStats',
            'yearlyStats',
            'topClients',
            'chartData',
            'selectedYear',
            'availableYears',
            'topRentedCars',
            'topProfitableCars',
            'carPerformanceStats',
            'carStats'
        ));
    }
}
