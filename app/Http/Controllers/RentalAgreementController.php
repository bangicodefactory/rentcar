<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\DriverBlacklist;
use App\Models\Notification;
use App\Models\Place;
use App\Models\RentalAgreement;
use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use App\Models\Signature;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class RentalAgreementController extends Controller
{

    public function index(Request $request)
    {
        if (\Auth::user()->can('manage rental agreement')) {
            // F-18 (perf-audit): select only the columns the list renders so we
            // don't pull the large terms_condition/description TEXT blobs, and
            // eager-load driver/vehicle to kill the per-row N+1.
            // F-21 follow-up: the prod list is unbounded (1.7k+ rows, ~1.4s);
            // paginate + server-side search so we ship one page at a time. Row
            // shape is unchanged — only the envelope is now a paginator.
            $search = trim((string) $request->get('search', ''));
            // Status labels the search term matches (the old client-side filter
            // searched the status *label*, e.g. typing "active"/"completed").
            // Match the English label or the raw key, then filter by those keys.
            $statusKeys = collect(RentalAgreement::$status)
                ->filter(fn ($label, $key) => $search !== '' && (stripos($label, $search) !== false || stripos($key, $search) !== false))
                ->keys()
                ->all();
            $agreements = RentalAgreement::where('parent_id', parentId())
                ->select(['id', 'agreement_id', 'date', 'rental_start_date', 'rental_end_date', 'rental_duration', 'status', 'driver', 'vehicle', 'created_at'])
                ->with(['drivers:id,name', 'vehicles:id,name,license_plate'])
                ->when($search !== '', function ($q) use ($search, $statusKeys) {
                    $q->where(function ($w) use ($search, $statusKeys) {
                        // Match the *displayed* agreement ID (prefix + number) so
                        // searching "RAG-1827" works like the old client filter.
                        $w->whereRaw('CONCAT(?, agreement_id) LIKE ?', [rentalAgreementPrefix(), "%{$search}%"])
                            ->orWhereHas('drivers', function ($d) use ($search) {
                                $d->where('name', 'like', "%{$search}%");
                            })
                            ->orWhereHas('vehicles', function ($v) use ($search) {
                                $v->where('name', 'like', "%{$search}%")
                                    ->orWhere('license_plate', 'like', "%{$search}%");
                            })
                            ->when(! empty($statusKeys), fn ($s) => $s->orWhereIn('status', $statusKeys));
                    });
                })
                ->orderBy('created_at', 'desc')
                ->paginate(25)
                ->withQueryString();
            return Inertia::render('RentalAgreement/Index', [
                'agreements' => $agreements->through(fn($a) => [
                    'id'                => $a->id,
                    'encrypted_id'      => Crypt::encrypt($a->id),
                    'agreement_id'      => rentalAgreementPrefix() . $a->agreement_id,
                    'driver_name'       => $a->drivers?->name ?? '-',
                    'vehicle_label'     => $a->vehicles ? $a->vehicles->name . ' - ' . $a->vehicles->license_plate : '-',
                    'date'              => $a->date,
                    'rental_start_date' => $a->rental_start_date,
                    'rental_end_date'   => $a->rental_end_date,
                    'rental_duration'   => $a->rental_duration,
                    'status'            => $a->status,
                ]),
                'statuses' => collect(RentalAgreement::$status)->map(fn($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'filters'  => ['search' => $search],
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function create()
    {
        if (\Auth::user()->can('create rental agreement')) {
            $vehicles = Vehicle::where('parent_id', parentId())->orderBy('created_at', 'desc')->get();

            $drivers = User::where('parent_id', parentId())
                ->where('type', 'driver')
                ->orderBy('created_at', 'desc') // newest driver first (unified across pickers)
                ->orderBy('id', 'desc')         // tie-break: imported drivers share a created_at
                ->get();
            // Flag blacklisted drivers so the picker can warn before submit (BAN-252).
            $blacklists = DriverBlacklist::activeFor($drivers->pluck('id')->all(), parentId());

            $defaultTerms = str_replace('\n', "\n", config('client.terms.rental_agreement', ''));
            return Inertia::render('RentalAgreement/Create', [
                'vehicles'     => $vehicles->map(fn($v) => ['id' => $v->id, 'label' => $v->name . ' - ' . $v->license_plate]),
                'drivers'      => $drivers->map(fn($u) => [
                    'id'               => $u->id,
                    'name'             => $u->name,
                    'blacklisted'      => $blacklists->has($u->id),
                    'blacklist_reason' => optional($blacklists->get($u->id))->reason,
                ])->values(),
                'statuses'     => collect(RentalAgreement::$status)->map(fn($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'defaultTerms' => $defaultTerms,
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create rental agreement')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'vehicle' => 'required',
                    'rental_start_date' => 'required',
                    'rental_end_date' => 'required|after_or_equal:rental_start_date',
                    'rental_duration' => 'required',
                    'rental_start_time' => 'required',
                    'rental_end_time' => 'required',
                    'driver' => 'required',
                    'create_booking' => 'required|boolean',
                ]
            );
            if ($validator->fails()) {
                return back()->withErrors($validator);
            }

            // Blacklist check (BAN-252): both driver and the optional driver2.
            // Warn-and-override — block unless acknowledged; the React picker
            // surfaces the warning so this is the server-side safety net.
            $driverIds = array_values(array_filter([$request->driver, $request->driver2]));
            $blacklists = DriverBlacklist::where('parent_id', parentId())
                ->whereIn('driver_user_id', $driverIds)
                ->whereNull('lifted_at')
                ->get();
            if ($blacklists->isNotEmpty() && !$request->boolean('acknowledge_blacklist')) {
                return back()->withInput()
                    ->with('error', __('Blacklisted driver(s): ') . $blacklists->pluck('reason')->implode('; '));
            }

                    // Combine date and time
        $start_datetime = $request->rental_start_date . ' ' . $request->rental_start_time;
        $end_datetime = $request->rental_end_date . ' ' . $request->rental_end_time;

            $rentalAgreement = new RentalAgreement();
            $rentalAgreement->agreement_id = $this->agreementNumber();
            $rentalAgreement->date = date('Y-m-d');
            $rentalAgreement->rental_start_date = $start_datetime;
            $rentalAgreement->rental_end_date = $end_datetime;
            // $rentalAgreement->rental_start_date = $request->rental_start_date;
            // $rentalAgreement->rental_end_date = $request->rental_end_date;
            $rentalAgreement->rental_duration = $request->rental_duration;
            $rentalAgreement->vehicle = $request->vehicle;
            $rentalAgreement->driver = $request->driver;
            // Add driver 2 
            $rentalAgreement->driver2 = $request->driver2 ?? null;
            $rentalAgreement->terms_condition = $request->terms_condition;
            $rentalAgreement->description = $request->description;
            $rentalAgreement->status = $request->status;
            $rentalAgreement->parent_id = parentId();
            $rentalAgreement->save();

            // Record an override per blacklisted driver if the owner proceeded.
            foreach ($blacklists as $bl) {
                $bl->recordOverride('rental_agreement', $rentalAgreement->id, (int) $bl->driver_user_id);
            }

            $user = User::find($request->driver);
            $module = 'new_agreement';
            $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
            $setting = settings();
            $errorMessage = '';
            if (!empty($notification) && $notification->enabled_email == 1) {
                $notification_responce = MessageReplace($notification, $rentalAgreement->id);
                $data['subject'] = $notification_responce['subject'];
                $data['message'] = $notification_responce['message'];
                $data['module'] = $module;
                $data['logo'] = $setting['company_logo'];
                $to = $user->email;

                $response = commonEmailSend($to, $data);
                if ($response['status'] == 'error') {
                    $errorMessage = $response['message'];
                }
            }

            // Create booking if not exists (check by boolean field in rental agreement)
            if ($request->create_booking == 1) {
                $booking = new Booking();
                $booking->booking_id = (new BookingController())->bookingNumber();
                $booking->vehicle = $request->vehicle;
                $booking->driver = $request->driver;

                // Form sends separate date/time fields; use them directly (no explode)
                if (!empty($request->rental_start_date)) {
                    $booking->start_date = $request->rental_start_date;
                    $booking->start_time = $request->rental_start_time ?? null;
                }
                if (!empty($request->rental_end_date)) {
                    $booking->end_date = $request->rental_end_date;
                    $booking->end_time = $request->rental_end_time ?? null;
                }

                // Use an existing place (e.g. Tetouan) or first place for parent; fallback 0
                $defaultPlaceId = Place::where('parent_id', parentId())
                    ->where(function ($q) {
                        $q->where('name', 'Tetouan')->orWhere('city', 'Tetouan');
                    })
                    ->value('id')
                    ?? Place::where('parent_id', parentId())->value('id')
                    ?? 0;
                $booking->pickup_address = $defaultPlaceId;
                $booking->drop_off_address = $defaultPlaceId;
                // Enum key, not the display label: every other code path uses
                // 'yet_to_start' (Booking::$status). Writing the label here
                // produced a status no filter/badge mapping recognised.
                $booking->status = 'yet_to_start';
                $booking->payment_status = 'impaye';
                $booking->notes = null;

                // rental_duration = daily price (from form); considerDays = computed from start/end date
                $dailyPrice = (float) $request->rental_duration;
                $start_datetime = $request->rental_start_date . ' ' . ($request->rental_start_time ?? '00:00');
                $end_datetime = $request->rental_end_date . ' ' . ($request->rental_end_time ?? '00:00');
                $rateData = vehicleRateCalculation($dailyPrice, $start_datetime, $end_datetime);
                $considerDays = (int) $rateData['considerDays'];
                $totalRate = (float) $rateData['totalRate'];

                $booking->details = json_encode([
                    'considerDays' => $considerDays,
                    'totalRate'    => $totalRate,
                ]);
                $booking->amount = (int) round($totalRate);

                $vehicle = Vehicle::find($request->vehicle);
                $booking->vehicle_details = [
                    'id' => $vehicle->id,
                    'name' => $vehicle->name,
                    'license_plate' => $vehicle->license_plate,
                ];
                $booking->parent_id = parentId();
                $booking->daily_price_final = $dailyPrice;
                $booking->save();
            }





            return redirect()->route('rental-agreement.index')->with('success', __('Rental agreement successfully created.') . '</br>' . $errorMessage);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show($ids)
    {
        if (\Auth::user()->can('show rental agreement')) {
            $id = Crypt::decrypt($ids);
            $rentalAgreement = RentalAgreement::find($id);

            // Batch-load both drivers' user records and Driver profiles in 2 queries
            $driverIds    = array_values(array_filter([$rentalAgreement->driver, $rentalAgreement->driver2]));
            $users        = User::whereIn('id', $driverIds)->get()->keyBy('id');
            // keyBy() keeps the LAST row per key; sort newest-first so the kept
            // profile is the lowest id — what Driver::where(user_id)->first()
            // returned before this was batched (drivers.user_id isn't unique).
            $driverProfiles = Driver::whereIn('user_id', $driverIds)->get()->sortByDesc('id')->keyBy('user_id');

            $user_1        = $users->get($rentalAgreement->driver);
            $user_2        = $rentalAgreement->driver2 ? $users->get($rentalAgreement->driver2) : null;
            $driver1Profile = $driverProfiles->get($rentalAgreement->driver);
            $driver_2      = $rentalAgreement->driver2 ? $driverProfiles->get($rentalAgreement->driver2) : null;

            $settings = settings();

            // display Terms and conditions
            $terms = str_replace('\n', "\n", config('client.terms.rental_agreement', ''));
            $terms = nl2br($terms);

            //display Signature
            $driver1Signature = $this->getUserSignature($rentalAgreement->driver);
            $driver2Signature = $this->getUserSignature($rentalAgreement->driver2);

            return Inertia::render('RentalAgreement/Show', [
                'agreement' => [
                    'id'                => $rentalAgreement->id,
                    'agreement_id'      => rentalAgreementPrefix() . $rentalAgreement->agreement_id,
                    'date'              => $rentalAgreement->date,
                    'rental_start_date' => $rentalAgreement->rental_start_date,
                    'rental_end_date'   => $rentalAgreement->rental_end_date,
                    'rental_duration'   => $rentalAgreement->rental_duration,
                    'status'            => $rentalAgreement->status,
                    'status_label'      => RentalAgreement::$status[$rentalAgreement->status] ?? $rentalAgreement->status,
                    'driver1' => [
                        'name'           => $user_1?->name,
                        'phone_number'   => $user_1?->phone_number,
                        'license_number' => $driver1Profile?->license_number,
                        'address'        => $driver1Profile?->address,
                        'birth_date'     => $driver1Profile?->birth_date,
                        'reference'      => $driver1Profile?->reference,
                    ],
                    'driver2' => $driver_2 && $user_2 ? [
                        'name'           => $user_2->name,
                        'phone_number'   => $user_2->phone_number,
                        'license_number' => $driver_2->license_number,
                        'address'        => $driver_2->address,
                        'birth_date'     => $driver_2->birth_date,
                        'reference'      => $driver_2->reference,
                    ] : null,
                    'vehicle_name'        => $rentalAgreement->vehicles?->name,
                    'vehicle_model'       => $rentalAgreement->vehicles?->model,
                    'vehicle_plate'       => $rentalAgreement->vehicles?->license_plate,
                    'driver1_signature'   => $driver1Signature,
                    'driver2_signature'   => $driver2Signature,
                ],
                'settings' => $settings,
                'terms'    => $terms,
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function edit(RentalAgreement $rentalAgreement)
    {
        if (\Auth::user()->can('edit rental agreement')) {
            $vehicles = Vehicle::where('parent_id', parentId())->get();

            $drivers = User::where('parent_id', parentId())->where('type', 'driver')->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get();

            $status = RentalAgreement::$status;

            return Inertia::render('RentalAgreement/Edit', [
                'agreement' => [
                    'id'                => $rentalAgreement->id,
                    'vehicle'           => $rentalAgreement->vehicle,
                    'driver'            => $rentalAgreement->driver,
                    'driver2'           => $rentalAgreement->driver2,
                    'rental_start_date' => $rentalAgreement->rental_start_date,
                    'rental_end_date'   => $rentalAgreement->rental_end_date,
                    'rental_duration'   => $rentalAgreement->rental_duration,
                    'status'            => $rentalAgreement->status,
                    'terms_condition'   => $rentalAgreement->terms_condition,
                    'description'       => $rentalAgreement->description,
                ],
                'vehicles' => $vehicles->map(fn($v) => ['id' => $v->id, 'label' => $v->name . ' - ' . $v->license_plate]),
                'drivers'  => $drivers->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->values(),
                'statuses' => collect(RentalAgreement::$status)->map(fn($l, $v) => ['value' => $v, 'label' => $l])->values(),
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function update(Request $request, RentalAgreement $rentalAgreement)
    {
        if (\Auth::user()->can('edit rental agreement')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'vehicle' => 'required',
                    'rental_start_date' => 'required',
                    'rental_end_date' => 'required|after_or_equal:rental_start_date',
                    'rental_duration' => 'required',
                    'rental_start_time' => 'required',
                    'rental_end_time' => 'required',
                    'driver' => 'required',
                ]
            );
            if ($validator->fails()) {
                return back()->withErrors($validator);
            }

                    // Combine date and time
        $start_datetime = $request->rental_start_date . ' ' . $request->rental_start_time;
        $end_datetime = $request->rental_end_date . ' ' . $request->rental_end_time;


            $agreementStatus = $rentalAgreement->status != $request->status;

            $rentalAgreement->rental_start_date = $start_datetime;
            $rentalAgreement->rental_end_date = $end_datetime;
            $rentalAgreement->rental_duration = $request->rental_duration;
            $rentalAgreement->vehicle = $request->vehicle;
            $rentalAgreement->driver = $request->driver;
            $rentalAgreement->driver2 = $request->driver2; // Update driver2
            $rentalAgreement->terms_condition = $request->terms_condition;
            $rentalAgreement->description = $request->description;
            $rentalAgreement->status = $request->status;
            $rentalAgreement->save();

            if ($agreementStatus) {
                $user = User::find($request->driver);
                $module = 'agreement_status';
                $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
                $setting = settings();
                $errorMessage = '';
                if (!empty($notification) && $notification->enabled_email == 1) {
                    $notification_responce = MessageReplace($notification, $rentalAgreement->id);
                    $data['subject'] = $notification_responce['subject'];
                    $data['message'] = $notification_responce['message'];
                    $data['module'] = $module;
                    $data['logo'] = $setting['company_logo'];
                    $to = $user->email;

                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = $response['message'];
                    }
                }
            }
            $errorMessage=!empty($errorMessage)?$errorMessage:'';


            return redirect()->route('rental-agreement.index')->with('success', __('Rental agreement successfully updated.').'</br>'.$errorMessage);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(RentalAgreement $rentalAgreement)
    {
        if (\Auth::user()->can('delete rental agreement')) {
            $rentalAgreement->delete();
            return redirect()->route('rental-agreement.index')->with('success', __('Rental agreement successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function agreementNumber()
    {
        $latest = RentalAgreement::where('parent_id', parentId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->agreement_id + 1;
    }

    /**
 * Get the latest signature for a user
 * 
 * @param int $userId
 * @return string|null Path to signature file
 */
private function getUserSignature($userId)
{
    // $signature = Signature::where('user_id', $userId)
    //                ->latest()
    //                ->first();
    
    // if ($signature && Storage::disk('public')->exists($signature->signature_path)) {
    //     // return Storage::disk('public')->path($signature->signature_path);
    //     return Storage::disk('public')->url( $signature->signature_path);
    // }
    
    // return null;

    $signature = Signature::where('user_id', $userId)
                 ->latest()
                 ->first();
    
    if ($signature && Storage::disk('public')->exists($signature->signature_path)) {
        // Convert to base64 for reliable printing
        $imagePath = Storage::disk('public')->path($signature->signature_path);
        $imageData = file_get_contents($imagePath);
        $base64 = base64_encode($imageData);
        return 'data:image/png;base64,' . $base64;
    }
    
    return null;
}



}
