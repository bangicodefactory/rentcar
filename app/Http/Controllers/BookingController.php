<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Driver;
use App\Models\DriverBlacklist;
use App\Models\Notification;
use App\Models\Place;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Tva;
use App\Services\CashPaymentSplitter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Support\ExcelValue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class BookingController extends Controller
{

    /**
     * Normalise the booking-list filters from the request.
     *
     * Returns [$search, $month] where $month is '' unless it is a well-formed
     * YYYY-MM (so an arbitrary value can't reach whereYear/whereMonth).
     *
     * @return array{0:string,1:string}
     */
    private function bookingFilters(Request $request): array
    {
        $search = trim((string) $request->get('search', ''));
        $month  = trim((string) $request->get('month', ''));
        $monthValid = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1;

        return [$search, $monthValid ? $month : ''];
    }

    /**
     * Base query for the booking list, scoped to the tenant and the active
     * search/month filters. Shared by index() (paginated rows) and
     * matchingIds() (all ids for the same filter) so the two can't drift.
     */
    private function filteredBookings(Request $request)
    {
        [$search, $month] = $this->bookingFilters($request);

        return Booking::where('parent_id', '=', parentId())
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('booking_id', 'like', "%{$search}%")
                        ->orWhere('vehicle_details', 'like', "%{$search}%")
                        ->orWhereHas('drivers', function ($d) use ($search) {
                            $d->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($month !== '', function ($q) use ($month) {
                [$year, $mon] = explode('-', $month);
                $q->whereYear('start_date', $year)->whereMonth('start_date', $mon);
            })
            ->orderBy('created_at', 'desc');
    }

    public function index(Request $request)
    {
        if (! \Auth::user()->can('manage booking')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        [$search, $month] = $this->bookingFilters($request);

        // When a month is selected, show the month's bookings on one page
        // (capped at 300 to keep the query and payload bounded); otherwise keep
        // the default 25 per page. The paginator shape is unchanged, so the
        // list and <Pagination> need no changes — <Pagination> hides itself
        // when everything fits on one page.
        $perPage = $month !== '' ? 300 : 25;

        $bookings = $this->filteredBookings($request)
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Booking/Index', [
            'filters' => ['search' => $search, 'month' => $month],
            'bookings' => $bookings->through(fn($b) => [
                'id'             => $b->id,
                'encrypted_id'   => Crypt::encrypt($b->id),
                'booking_id'     => bookingPrefix() . $b->booking_id,
                'driver_name'    => $b->drivers?->name ?? '-',
                'vehicle_label'  => optional($b->vehicleDetails())->name . ' - ' . optional($b->vehicleDetails())->license_plate,
                'start_date'     => $b->start_date,
                'start_time'     => $b->start_time,
                'end_date'       => $b->end_date,
                'end_time'       => $b->end_time,
                'status'         => $b->status,
                'payment_status' => $b->payment_status,
            ]),
            'statuses'       => collect(Booking::$status)->map(fn($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'paymentStatuses' => collect(Booking::$paymentStatus)->map(fn($l, $v) => ['value' => $v, 'label' => $l])->values(),
            'paymentMethods' => collect(BookingPayment::$paymentMethod)->map(fn($l, $v) => ['value' => $v, 'label' => $l])->values(),
        ]);
    }


    public function create()
    {
        if (\Auth::user()->can('create booking')) {
            $vehicles = Vehicle::where('parent_id', parentId())->limit(500)->get();

            // Load every driver for the tenant (newest first). The driver
            // SearchableSelect filters client-side, so a capped slice made older
            // drivers unfindable once a tenant had >500 of them (BAN-266).
            // Server-side search is the follow-up for larger scale.
            $drivers = User::where('parent_id', parentId())
                ->where('type', 'driver')
                ->orderBy('created_at', 'desc')
                ->orderBy('id', 'desc') // tie-break: imported drivers share a created_at
                ->get();
            // Flag blacklisted drivers so the picker can warn before submit (BAN-252).
            $blacklists = DriverBlacklist::activeFor($drivers->pluck('id')->all(), parentId());
            $driversProp = $drivers->map(fn($d) => [
                'id'               => $d->id,
                'name'             => $d->name,
                'blacklisted'      => $blacklists->has($d->id),
                'blacklist_reason' => optional($blacklists->get($d->id))->reason,
            ])->values();


            $status = Booking::$status;
            $paymentStatus = Booking::$paymentStatus;

            $places = Place::where('parent_id', parentId())->limit(500)->get();
            $addon = Addon::where('parent_id', parentId())->limit(500)->get()->pluck('name', 'id');

            return Inertia::render('Booking/Create', [
                'vehicles' => $vehicles->map(fn($v) => ['id' => $v->id, 'label' => $v->name . ' - ' . $v->license_plate]),
                'drivers'  => $driversProp,
                'statuses' => collect(Booking::$status)->map(fn($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'places'   => $places->map(fn($p) => ['id' => $p->id, 'name' => $p->name]),
                'addons'   => $addon->map(fn($name, $id) => ['id' => $id, 'name' => $name])->values(),
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    // public function store(Request $request)
    // {
    //     if (!\Auth::user()->can('create booking')) {
    //         return redirect()->back()->with('error', __('Permission Denied.'));
    //     }

    //     // 🔹 Validate inputs
    //     $validator = \Validator::make(
    //         $request->all(),
    //         [
    //             'vehicle' => 'required|exists:vehicles,id',
    //             'start_date_time' => 'required|date',
    //             'end_date_time' => 'required|date|after:start_date_time',
    //             'driver' => 'required|exists:users,id',
    //             'pickup_address' => 'required|string',
    //             'drop_off_address' => 'required|string',
    //             'status' => 'required|string',
    //             'amount' => 'required|numeric|min:0',
    //         ]
    //     );

    //     if ($validator->fails()) {
    //         // Return with full validation error bag instead of only first error
    //         return redirect()
    //             ->back()
    //             ->withErrors($validator)
    //             ->withInput();
    //     }

    //     // 🔹 Vehicle details
    //     $vehicle_detail = Vehicle::find($request->vehicle);

    //     // 🔹 Create booking
    //     $booking = new Booking();
    //     $booking->booking_id = $this->bookingNumber();
    //     $booking->vehicle = $request->vehicle;
    //     $booking->driver = $request->driver;

    //     if (!empty($request->start_date_time)) {
    //         $startDateTime = explode(' ', $request->start_date_time);
    //         $booking->start_date = $startDateTime[0];
    //         $booking->start_time = $startDateTime[1];
    //     }
    //     if (!empty($request->end_date_time)) {
    //         $endDateTime = explode(' ', $request->end_date_time);
    //         $booking->end_date = $endDateTime[0];
    //         $booking->end_time = $endDateTime[1];
    //     }

    //     $booking->pickup_address = $request->pickup_address;
    //     $booking->drop_off_address = $request->drop_off_address;
    //     $booking->addon = !empty($request->addon) ? implode(',', $request->addon) : null;
    //     $booking->status = $request->status;
    //     $booking->notes = $request->notes;
    //     $booking->amount = $request->amount;
    //     $booking->payment_status = 'impaye';
    //     $booking->payment_notes = null;
    //     $booking->details = $request->details;
    //     // Store only the minimal vehicle snapshot (avoid double encoding with model cast)
    //     $booking->vehicle_details = [
    //         'id' => $vehicle_detail->id,
    //         'name' => $vehicle_detail->name,
    //         'license_plate' => $vehicle_detail->license_plate,
    //     ];
    //     $booking->parent_id = parentId();
    //     $booking->daily_price_final = $request->daily_price ?? 0;
    //     $booking->save();

    //     // 🔹 User & driver
    //     $user = User::find($request->driver);
    //     $driver1 = Driver::where('user_id', $request->driver)->first();

    //     // 🔹 Notification by email (optional)
    //     $module = 'new_booking';
    //     $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
    //     $setting = settings();
    //     $errorMessage = '';
    //     if (!empty($notification) && $notification->enabled_email == 1) {
    //         $notification_responce = MessageReplace($notification, $booking->id);
    //         $data['subject'] = $notification_responce['subject'];
    //         $data['message'] = $notification_responce['message'];
    //         $data['module'] = $module;
    //         $data['logo'] = $setting['company_logo'];
    //         $to = $user->email;

    //         $response = commonEmailSend($to, $data);
    //         if ($response['status'] == 'error') {
    //             $errorMessage = $response['message'];
    //         }
    //     }

    //     // 🔹 TVA Calculation
    //     // $startDate = Carbon::parse($booking->start_date);
    //     // $endDate = Carbon::parse($booking->end_date);
    //     // $totalDays = max(1, $startDate->diffInDays($endDate));

    //     // // vehicle_details is cast to object in Booking model; cast to array for safe key access
    //     // $vehicleDetailsObj = $booking->vehicleDetails();
    //     // $vehicle_name = $vehicleDetailsObj->name ?? '';
    //     // $vehicle_license_plate = $vehicleDetailsObj->license_plate ?? '';

    //     // $totalHT = round($booking->amount * 0.8, 2);
    //     // $tvaAmount = round($booking->amount * 0.2, 2);


    //     // // Global last facture number (ignoring tenant scoping per new requirement)
    //     // $lastFacture = Tva::orderByDesc('id')->first();
    //     // $lastNumber = 0;
    //     // if ($lastFacture && preg_match('/\d+$/', $lastFacture->facture_number, $matches)) {
    //     //     $lastNumber = (int)$matches[0];
    //     // }
    //     // $factureCounter = $lastNumber;
    //     // $factureCounter++;
    //     // $factureNumber = $factureCounter;


    //     // $tva = new Tva();
    //     // $tva->facture_number = $factureNumber;
    //     // $tva->facture_date = $booking->created_at;
    //     // $tva->client_name = $user->name;
    //     // $tva->client_address = $driver1 ? $driver1->address : '';
    //     // $tva->company_name = $setting['company_name'];
    //     // $tva->company_address = $setting['company_address'];
    //     // $tva->designation = $vehicle_name . '-' . $vehicle_license_plate;
    //     // $tva->quantity = (float)$totalDays;
    //     // $tva->total_ht = number_format($totalHT, 2, '.', '');
    //     // $tva->tva = number_format($tvaAmount, 2, '.', '');
    //     // $tva->unit_price_ht = number_format($totalDays > 0 ? round($totalHT / $totalDays, 2) : 0, 2, '.', '');
    //     // $tva->montant_ttc = number_format($booking->amount, 2, '.', '');
    //     // $tva->ice_number = $setting['ice'];
    //     // $tva->rc_number = $setting['rc'];
    //     // $tva->nif_number = $setting['if'];
    //     // $tva->parent_id = parentId();
    //     // $tva->booking_id = $booking->id;
    //     // $tva->generated_date = now()->toDateString();
    //     // $tva->total_amount = number_format($booking->amount, 2, '.', '');
    //     // $tva->tva_amount = number_format($tvaAmount, 2, '.', '');
    //     // $tva->save();

    //     return redirect()->route('booking.show', Crypt::encrypt($booking->id))
    //         ->with('success', __('Booking successfully created.') . '</br>' . $errorMessage);
    // }


    public function store(Request $request)
    {
        if (!\Auth::user()->can('create booking')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        // 🔹 Validate inputs
        $validator = \Validator::make(
            $request->all(),
            [
                'vehicle' => 'required|exists:vehicles,id',
                'start_date_time' => 'required|date',
                'end_date_time' => 'required|date|after:start_date_time',
                'driver' => 'required|exists:users,id',
                'pickup_address' => 'required|string',
                'drop_off_address' => 'required|string',
                'status' => 'required|string',
                'amount' => 'required|numeric|min:0',
            ]
        );

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        // 🔹 Blacklist check (BAN-252): warn-and-override. If the driver is
        // blacklisted and the owner hasn't acknowledged, block; the React picker
        // surfaces the warning so this only fires as the server-side safety net.
        $blacklist = DriverBlacklist::where('parent_id', parentId())
            ->where('driver_user_id', $request->driver)
            ->whereNull('lifted_at')
            ->first();
        if ($blacklist && !$request->boolean('acknowledge_blacklist')) {
            return redirect()->back()->withInput()
                ->with('error', __('This driver is blacklisted: ') . $blacklist->reason);
        }

        // 🔹 Vehicle details
        $vehicle_detail = Vehicle::find($request->vehicle);

        // 🔹 Create booking
        $booking = new Booking();
        $booking->booking_id = $this->bookingNumber();
        $booking->vehicle = $request->vehicle;
        $booking->driver = $request->driver;

        if (!empty($request->start_date_time)) {
            $startDateTime = explode(' ', $request->start_date_time);
            $booking->start_date = $startDateTime[0];
            $booking->start_time = $startDateTime[1];
        }
        if (!empty($request->end_date_time)) {
            $endDateTime = explode(' ', $request->end_date_time);
            $booking->end_date = $endDateTime[0];
            $booking->end_time = $endDateTime[1];
        }

        $booking->pickup_address = $request->pickup_address;
        $booking->drop_off_address = $request->drop_off_address;
        $booking->addon = !empty($request->addon) ? implode(',', $request->addon) : null;
        $booking->status = $request->status;
        $booking->notes = $request->notes;
        $booking->amount = $request->amount;
        $booking->payment_status = 'impaye';
        $booking->payment_notes = null;
        $booking->details = $request->details;
        // Store only the minimal vehicle snapshot (avoid double encoding with model cast)
        $booking->vehicle_details = [
            'id' => $vehicle_detail->id,
            'name' => $vehicle_detail->name,
            'license_plate' => $vehicle_detail->license_plate,
        ];
        $booking->parent_id = parentId();
        $booking->daily_price_final = $request->daily_price ?? 0;
        $booking->save();

        // Record the override if the owner proceeded past a blacklist warning.
        if ($blacklist) {
            $blacklist->recordOverride('booking', $booking->id, (int) $request->driver);
        }

        // 🔹 User & driver
        $user = User::find($request->driver);
        $driver1 = Driver::where('user_id', $request->driver)->first();

        // 🔹 Notification by email (optional)
        $module = 'new_booking';
        $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
        $setting = settings();
        $errorMessage = '';
        if (!empty($notification) && $notification->enabled_email == 1) {
            $notification_responce = MessageReplace($notification, $booking->id);
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

        

        return redirect()->route('booking.show', Crypt::encrypt($booking->id))
            ->with('success', __('Booking successfully created.')  . $errorMessage);
    }
    public function show($id)
    {
        if (\Auth::user()->can('show booking')) {
            try {
                $decryptedId = Crypt::decrypt($id);
            } catch (\Exception $e) {
                // If it's not an encrypted value, assume it's a numeric id
                $decryptedId = $id;
            }

            // Enforce tenant scope and fail with 404 if not found
            $booking = Booking::where('id', $decryptedId)
                ->where('parent_id', parentId())
                ->first();

            if (!$booking) {
                abort(404);
            }

            $settings = settings();

            $details = $booking->details;
            $detailsObj = is_string($details) ? json_decode($details) : $details;
            $parts = [];
            if (!empty($detailsObj->totalDays))  $parts[] = $detailsObj->totalDays . ' Days';
            if (!empty($detailsObj->totalHours)) $parts[] = $detailsObj->totalHours . ' Hours';

            $startDate = Carbon::parse($booking->start_date);
            $endDate   = Carbon::parse($booking->end_date);
            $totalDays = max(1, $startDate->diffInDays($endDate));
            $dueAmount = $booking->getTotalDueAmount();
            $totalDaysAmount = $booking->amount > 0 ? ($dueAmount * $totalDays) / $booking->amount : 1;
            $defaultQuantity = max(1, round($totalDaysAmount));

            $driver = $booking->drivers;
            $driverProfile = $driver ? Driver::where('user_id', $driver->id)->first() : null;

            return Inertia::render('Booking/Show', [
                'booking' => [
                    'id'                   => $booking->id,
                    'encrypted_id'         => Crypt::encrypt($booking->id),
                    'booking_id'           => bookingPrefix() . $booking->booking_id,
                    'status'               => $booking->status,
                    'status_label'         => Booking::$status[$booking->status] ?? $booking->status,
                    'payment_status'       => $booking->payment_status,
                    'payment_status_label' => Booking::$paymentStatus[$booking->payment_status] ?? $booking->payment_status,
                    'start_date'           => $booking->start_date,
                    'start_time'           => $booking->start_time,
                    'end_date'             => $booking->end_date,
                    'end_time'             => $booking->end_time,
                    'created_at'           => $booking->created_at?->format('Y-m-d'),
                    'notes'                => $booking->notes,
                    'driver_name'          => $driver?->name ?? '',
                    'driver_phone'         => $driver?->phone_number ?? '',
                    'driver_email'         => $driver?->email ?? '',
                    'driver_ice'           => $driverProfile?->ICE_company ?? '',
                    'vehicle_name'         => optional($booking->vehicleDetails())->name ?? '-',
                    'duration'             => implode(', ', $parts),
                    'addons'               => $booking->addons()->map(fn($a) => ['id' => $a->id, 'name' => $a->name, 'price' => $a->price]),
                    'pickup_address'       => $booking->pickupAddress ? ['name' => $booking->pickupAddress->name, 'price' => $booking->pickupAddress->price ?? null] : null,
                    'drop_off_address'     => $booking->dropOffAddress ? ['name' => $booking->dropOffAddress->name, 'price' => $booking->dropOffAddress->price ?? null] : null,
                    'payments'             => $booking->payments->map(fn($p) => ['id' => $p->id, 'date' => $p->date, 'payment_method' => $p->payment_method, 'notes' => $p->notes, 'amount' => $p->amount]),
                    'total_amount'         => number_format($booking->getTotalAmount(), 2),
                    'total_ht'             => number_format($booking->getTotalAmount() * 0.8, 2),
                    'tva_amount'           => number_format($booking->getTotalAmount() * 0.2, 2),
                    'paid_amount'          => number_format($booking->getTotalAmount() - $booking->getTotalDueAmount(), 2),
                    'due_amount'           => $booking->getTotalDueAmount(),
                ],
                'settings'        => $settings,
                'paymentMethods'  => collect(BookingPayment::$paymentMethod)->map(fn($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'defaultQuantity' => $defaultQuantity,
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function edit($id)
    {
        if (\Auth::user()->can('edit booking')) {
            $booking = Booking::find(Crypt::decrypt($id));
            $booking->start_date_time = date('Y/m/d H:i', strtotime($booking->start_date . ' ' . $booking->start_time));
            $booking->end_date_time = date('Y/m/d H:i', strtotime($booking->end_date . ' ' . $booking->end_time));

            // All drivers for the tenant (newest first); see create() — a capped
            // slice made older drivers unfindable in the picker (BAN-266).
            $drivers = User::where('parent_id', parentId())->where('type', 'driver')->orderBy('created_at', 'desc')->orderBy('id', 'desc')->get()->pluck('name', 'id');

            $status = Booking::$status;
            $paymentStatus = Booking::$paymentStatus;
            $places = Place::where('parent_id', parentId())->limit(500)->get();

            $addon = Addon::where('parent_id', parentId())->limit(500)->get()->pluck('name', 'id');

            $startDateTime = Carbon::createFromFormat('Y/m/d H:i', date('Y/m/d H:i', strtotime($booking->start_date_time)));
            $endDateTime = Carbon::createFromFormat('Y/m/d H:i', date('Y/m/d H:i', strtotime($booking->end_date_time)));

            $startDateTimeStr = $startDateTime->format('Y-m-d H:i:s');
            $endDateTimeStr = $endDateTime->format('Y-m-d H:i:s');

            $booked = Booking::where('id', '!=', $booking->id)->whereNotIn('status', ['completed', 'cancelled'])
                ->where(function ($query) use ($startDateTimeStr, $endDateTimeStr) {
                    $query->where(function ($query) use ($startDateTimeStr, $endDateTimeStr) {
                        $query->where(DB::raw('CONCAT(start_date, " ", start_time)'), '>=', $startDateTimeStr)->where(DB::raw('CONCAT(start_date, " ", start_time)'), '<=', $endDateTimeStr);
                    })->orWhere(function ($query) use ($startDateTimeStr, $endDateTimeStr) {
                        $query->where(DB::raw('CONCAT(end_date, " ", end_time)'), '>=', $startDateTimeStr)->where(DB::raw('CONCAT(end_date, " ", end_time)'), '<=', $endDateTimeStr);
                    })->orWhere(function ($query) use ($startDateTimeStr, $endDateTimeStr) {
                        $query->where(DB::raw('CONCAT(start_date, " ", start_time)'), '<=', $startDateTimeStr)->where(DB::raw('CONCAT(end_date, " ", end_time)'), '>=', $endDateTimeStr);
                    });
                })->distinct()->pluck('vehicle')->toArray();

            $vehicles = Vehicle::where('parent_id', parentId())->whereNotIn('id', $booked)->limit(500)->get();

            return Inertia::render('Booking/Edit', [
                'booking'  => [
                    'id'              => $booking->id,
                    'vehicle'         => $booking->vehicle,
                    'driver'          => $booking->driver,
                    'start_date_time' => $booking->start_date_time,
                    'end_date_time'   => $booking->end_date_time,
                    'pickup_address'  => $booking->pickup_address,
                    'drop_off_address' => $booking->drop_off_address,
                    'addon'           => $booking->addon,
                    'discount'        => $booking->discount,
                    'status'          => $booking->status,
                    'notes'           => $booking->notes,
                    'daily_price_final' => $booking->daily_price_final,
                    'amount'          => $booking->amount,
                    'details'         => $booking->details,
                ],
                'vehicles' => $vehicles->map(fn($v) => ['id' => $v->id, 'label' => $v->name . ' - ' . $v->license_plate]),
                'drivers'  => $drivers->map(fn($name, $id) => ['id' => $id, 'name' => $name])->values(),
                'statuses' => collect(Booking::$status)->map(fn($l, $v) => ['value' => $v, 'label' => $l])->values(),
                'places'   => $places->map(fn($p) => ['id' => $p->id, 'name' => $p->name]),
                'addons'   => $addon->map(fn($name, $id) => ['id' => $id, 'name' => $name])->values(),
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function update(Request $request, Booking $booking)
    {
        if (\Auth::user()->can('create booking')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'vehicle' => 'required',
                    'start_date_time' => 'required',
                    'end_date_time' => 'required',
                    'driver' => 'required',
                    'pickup_address' => 'required',
                    'drop_off_address' => 'required',
                    'status' => 'required',
                    'amount' => 'required',
                    'daily_price' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $bookingStatus = $booking->status != $request->status;


            $vehicle_detail = Vehicle::find($request->vehicle);
            $booking->vehicle = $request->vehicle;
            $booking->driver = $request->driver;
            if (!empty($request->start_date_time)) {
                $startDateTime = explode(' ', $request->start_date_time);
                $booking->start_date = $startDateTime[0];
                $booking->start_time = $startDateTime[1];
            }
            if (!empty($request->end_date_time)) {
                $endDateTime = explode(' ', $request->end_date_time);
                $booking->end_date = $endDateTime[0];
                $booking->end_time = $endDateTime[1];
            }
            $booking->pickup_address = $request->pickup_address;
            $booking->drop_off_address = $request->drop_off_address;
            if (!empty($request->status)) {
                $booking->status = $request->status;
            }
            $booking->addon = !empty($request->addon) ? implode(',', $request->addon) : null;
            $booking->amount = $request->amount;
            $booking->payment_notes = null;
            $booking->details = $request->details;
            $booking->vehicle_details = [
                'id' => $vehicle_detail->id,
                'name' => $vehicle_detail->name,
                'license_plate' => $vehicle_detail->license_plate,
            ];
            $booking->daily_price_final = $request->daily_price;
            $booking->save();

            //update dynamic with tva section
            // $tva = Tva::where('booking_id', $booking->id)->first();
            // if ($tva) {
            //     // Get totalDays from details object (now automatically cast from JSON)
            //     $details = $booking->details;
            //     // If it's a string (from request), decode it
            //     if (is_string($details)) {
            //         $details = json_decode($details);
            //     }

            //     $quantity = isset($details->totalDays) ? $details->totalDays : 1;
            //     $unit_price_ht = $booking->daily_price_final * 0.8;
            //     $total_ht = $booking->amount * 0.8; // Assuming amount is total TTC, calculate HT
            //     $tva_rate = 0.20; // 20%
            //     // $tva_amount = $total_ht * $tva_rate;
            //     $montant_ttc = $booking->amount;

            //     // Update designation if vehicle details changed
            //     $vd = (array)$booking->vehicle_details;
            //     $designationName = trim(($vd['name'] ?? ''));
            //     $designationPlate = trim(($vd['license_plate'] ?? ''));
            //     $tva->designation = trim($designationName . (($designationName && $designationPlate) ? ' - ' : '') . $designationPlate);
            //     $tva->quantity = $quantity;
            //     $tva->total_ht = $total_ht;
            //     $tva->unit_price_ht = $tva->quantity > 0 ? round($tva->total_ht / $tva->quantity, 2) : 0;
            //     $tva->tva = $montant_ttc * 0.2; // Assuming 20% TVA
            //     $tva->montant_ttc = $montant_ttc;
            //     $tva->total_amount = $montant_ttc;
            //     $tva->tva_amount = $montant_ttc * 0.2;
            //     $tva->updated_at = now();
            //     $tva->save();
            // }


            if ($bookingStatus) {
                $user = User::find($request->driver);
                $module = 'booking_status';
                $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
                $setting = settings();
                $errorMessage = '';
                if (!empty($notification) && $notification->enabled_email == 1) {
                    $notification_responce = MessageReplace($notification, $booking->id);
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
            $errorMessage = !empty($errorMessage) ? $errorMessage : '';
            return redirect()->route('booking.index')->with('success', __('Booking successfully updated.') . '</br>' . $errorMessage);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }



    public function destroy(Booking $booking)
    {
        if (\Auth::user()->can('delete booking')) {
            // Delete associated TVA record first
            Tva::where('booking_id', $booking->id)->delete();

            // Then delete the booking
            $booking->delete();
            return redirect()->route('booking.index')->with('success', __('Booking successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function bulkDestroy(Request $request)
    {
        if (!\Auth::user()->can('delete booking')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return redirect()->back()->with('error', __('No bookings selected.'));
        }

        // Scope to the caller's tenant before deleting: the request carries
        // arbitrary client-supplied ids, so resolve which ones the tenant
        // actually owns and only delete those (and their TVA rows). Without
        // this, a crafted id list could delete another tenant's bookings —
        // bulkMarkPaid already scopes the same way.
        $ownedIds = Booking::whereIn('id', $ids)
            ->where('parent_id', parentId())
            ->pluck('id');

        if ($ownedIds->isEmpty()) {
            return redirect()->back()->with('error', __('No bookings selected.'));
        }

        Tva::whereIn('booking_id', $ownedIds)->delete();
        Booking::whereIn('id', $ownedIds)->delete();

        return redirect()->route('booking.index')->with('success', __('Selected bookings successfully deleted.'));
    }

    public function bulkMarkPaid(Request $request)
    {
        // Records payments + factures (same as the single-payment flow), so it
        // is gated on the same permission as paymentStore, not merely 'edit
        // booking' — otherwise edit-only users could create financial records
        // in bulk that they can't create individually.
        if (!\Auth::user()->can('create booking payment')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids'            => 'required|array|min:1',
            'ids.*'          => 'integer',
            'payment_method' => 'required|string',
            'date'           => 'nullable|date',
        ]);

        $date     = $validated['date'] ?? now()->toDateString();
        $method   = $validated['payment_method'];
        $isCash   = strtolower($method) === 'espece';
        $cashMax  = (float) config('client.cash_payment_max', 5000);
        $splitCash = $isCash && feature('cash_split');

        $paid = 0;
        $skippedAlreadyPaid = 0;
        $skippedCash = 0;

        DB::transaction(function () use ($validated, $date, $method, $isCash, $cashMax, $splitCash, &$paid, &$skippedAlreadyPaid, &$skippedCash) {
            // Tenant-scoped: only the caller's own bookings can be touched.
            $bookings = Booking::whereIn('id', $validated['ids'])
                ->where('parent_id', parentId())
                ->get();

            foreach ($bookings as $booking) {
                $remaining = round((float) $booking->getTotalDueAmount(), 2);

                // Already fully paid → nothing to record.
                if ($remaining <= 0) {
                    $skippedAlreadyPaid++;
                    continue;
                }

                // Same rule as the single-payment flow: cash over the ceiling is
                // either split into compliant receipts (cash_split on) or refused.
                if ($isCash && $remaining > $cashMax) {
                    if (!$splitCash) {
                        $skippedCash++;
                        continue;
                    }
                    $this->recordSplitCashPayment($booking, $remaining, $method, $date, null, null);
                    $paid++;
                    continue;
                }

                // Records the payment for the outstanding balance, the matching
                // facture, and flips the status — identical to paymentStore.
                $this->recordBookingPayment($booking, $remaining, $method, $date);
                $paid++;
            }
        });

        $msg = __(':count booking(s) marked as paid.', ['count' => $paid]);
        $notes = [];
        if ($skippedAlreadyPaid > 0) {
            $notes[] = __(':count already fully paid', ['count' => $skippedAlreadyPaid]);
        }
        if ($skippedCash > 0) {
            $notes[] = __(':count skipped (cash over 5000)', ['count' => $skippedCash]);
        }
        if (!empty($notes)) {
            $msg .= ' — ' . implode(', ', $notes);
        }

        return redirect()->route('booking.index')->with('success', $msg);
    }

    /**
     * Record one payment on a booking plus its matching facture (TVA), then
     * update the booking's payment status. Shared by the single-payment flow
     * (paymentStore) and the bulk "mark as paid" action so the two stay
     * identical. Validation (amount > 0, cash <= 5000, permissions) is the
     * caller's responsibility.
     *
     * @param int|null $quantity Optional invoice quantity (days); derived from
     *                           the booking dates + amount when null.
     */
    private function recordBookingPayment(Booking $booking, float $amount, string $paymentMethod, string $date, ?string $notes = null, ?int $quantity = null): BookingPayment
    {
        // Payment, facture(s), and status change are one atomic unit: a flush
        // can create several factures, so a mid-flush failure must not leave the
        // payment committed with a partial invoice set and a stale status. Safe
        // to nest — the split/bulk callers already open their own transaction.
        return DB::transaction(function () use ($booking, $amount, $paymentMethod, $date, $notes, $quantity) {
            $payment = new BookingPayment();
            $payment->booking_id = $booking->id;
            $payment->amount = $amount;
            $payment->date = $date;
            $payment->payment_method = $paymentMethod;
            $payment->notes = $notes;
            // Persist the invoice day-count so deferred invoicing reproduces the
            // exact days (manual override or cash-split share) at flush time.
            $payment->invoice_days = ($quantity && $quantity > 0) ? $quantity : null;
            $payment->parent_id = parentId();
            $payment->save();

            // Status from the freshly-summed payments (includes the row just saved).
            // Round to cents before the zero-test: payment amounts are floats, so
            // an unrounded residual (e.g. 1e-13 from installment sums) would read
            // as "still owing" and wrongly defer invoicing. Matches bulkMarkPaid.
            $fullyPaid = round((float) Booking::find($booking->id)->getTotalDueAmount(), 2) <= 0;
            $status = $fullyPaid ? 'paye' : 'partiellement_paye';

            if (feature('invoice_on_full_payment')) {
                // Defer invoicing: emit a facture for every still-uninvoiced payment
                // on the booking only once its balance is fully cleared.
                if ($fullyPaid) {
                    $this->flushBookingFactures($booking);
                }
            } else {
                // Legacy behaviour: one facture per payment, created immediately.
                $this->createFactureForPayment($booking, $payment);
            }

            Booking::statusChange($booking->id, $status);

            return $payment;
        });
    }

    /**
     * Build and persist one facture (TVA) for a single payment, continuing the
     * global facture-number sequence. The quantity (days) is the value stored on
     * the payment (a manual override or a cash-split share) when present, else
     * derived from the booking dates + payment amount.
     */
    private function createFactureForPayment(Booking $booking, BookingPayment $payment): Tva
    {
        $amount        = (float) $payment->amount;
        $date          = $payment->date;
        $paymentMethod = $payment->payment_method;

        $setting = settings();
        $user    = User::find($booking->driver);
        $driver1 = Driver::where('user_id', $booking->driver)->first();

        $totalDays = $this->deriveInvoiceDays($booking, $amount, $payment->invoice_days);

        $vd = $booking->vehicleDetails();
        $vehicleName  = $vd->name ?? '';
        $vehiclePlate = $vd->license_plate ?? '';

        $totalHT   = round($amount / 1.2, 2);
        $tvaAmount = round($amount - $totalHT, 2);

        // Global last facture number (matches paymentStore; per-year unification
        // is tracked in IST-230).
        $lastFacture = Tva::orderByDesc('id')->first();
        $lastNumber = ($lastFacture && preg_match('/\d+$/', (string) $lastFacture->facture_number, $matches)) ? (int) $matches[0] : 0;
        $factureNumber = $lastNumber + 1;

        $tva = new Tva();
        $tva->facture_number = $factureNumber;
        $tva->facture_date = $date;
        $tva->idpaiment = $payment->id;
        $tva->client_name = optional($user)->name ?? '';
        $tva->client_address = $driver1 ? $driver1->address : '';
        $tva->company_name = $setting['company_name'];
        $tva->company_address = $setting['company_address'];
        $tva->designation = $vehicleName . '-' . $vehiclePlate;
        $tva->quantity = (float) $totalDays;
        $tva->total_ht = number_format($totalHT, 2, '.', '');
        $tva->tva = number_format($tvaAmount, 2, '.', '');
        $tva->unit_price_ht = number_format($totalDays > 0 ? round($totalHT / $totalDays, 2) : 0, 2, '.', '');
        $tva->montant_ttc = number_format($amount, 2, '.', '');
        $tva->ice_number = $setting['ice'] ?? null;
        $tva->rc_number = $setting['rc'] ?? null;
        $tva->nif_number = $setting['if'] ?? null;
        $tva->parent_id = parentId();
        $tva->booking_id = $booking->id;
        $tva->generated_date = now()->toDateString();
        $tva->total_amount = number_format($booking->amount, 2, '.', '');
        $tva->tva_amount = number_format($tvaAmount, 2, '.', '');
        $tva->payment_method = $paymentMethod;
        // DB column is required (no default); keep consistent with seeder usage.
        $tva->status = 1;
        $tva->save();

        return $tva;
    }

    /**
     * Emit a facture for every payment on the booking that doesn't yet have one,
     * in id order so facture numbers stay successive. Idempotent — already-
     * invoiced payments are skipped, so re-clearing an over-paid booking (or a
     * later payment on an already-paid one) never duplicates invoices.
     *
     * The "already invoiced" set includes soft-deleted factures (withTrashed):
     * a manually deleted invoice must not be silently regenerated by a later
     * flush. Re-issuing it is a deliberate action (the Renumber/Generate tools).
     */
    private function flushBookingFactures(Booking $booking): void
    {
        $invoicedPaymentIds = Tva::withTrashed()
            ->where('booking_id', $booking->id)
            ->whereNotNull('idpaiment')
            ->pluck('idpaiment')
            ->all();

        $payments = BookingPayment::where('booking_id', $booking->id)
            ->whereNotIn('id', $invoicedPaymentIds)
            ->orderBy('id')
            ->get();

        foreach ($payments as $payment) {
            $this->createFactureForPayment($booking, $payment);
        }
    }

    /**
     * Rental days a single facture should show for a given payment amount:
     * an explicit override when provided, else proportional to the booking's
     * total (mirrors the null-quantity branch of recordBookingPayment).
     */
    private function deriveInvoiceDays(Booking $booking, float $amount, ?int $quantity): int
    {
        if ($quantity && $quantity > 0) {
            return $quantity;
        }
        $startDate = Carbon::parse($booking->start_date);
        $endDate   = Carbon::parse($booking->end_date);
        $days      = max(1, $startDate->diffInDays($endDate));
        $bookingAmount = (float) $booking->amount;

        return $bookingAmount > 0 ? max(1, (int) round(($amount * $days) / $bookingAmount)) : $days;
    }

    /**
     * Record a cash payment that exceeds the legal ceiling as several compliant
     * receipts (each <= cash_payment_max), on distinct days across the rental
     * period, with the rental days apportioned. Each receipt goes through
     * recordBookingPayment, so it gets its own BookingPayment + facture and the
     * booking status is kept in sync — identical to a normal payment.
     *
     * @return int Number of receipts created.
     */
    private function recordSplitCashPayment(Booking $booking, float $amount, string $paymentMethod, string $date, ?string $notes, ?int $quantity): int
    {
        $cashMax   = (float) config('client.cash_payment_max', 5000);
        $totalDays = $this->deriveInvoiceDays($booking, $amount, $quantity);

        $plan = app(CashPaymentSplitter::class)->plan(
            $amount,
            Carbon::parse($booking->start_date),
            Carbon::parse($booking->end_date),
            $totalDays,
            $cashMax
        );

        DB::transaction(function () use ($booking, $plan, $paymentMethod, $notes) {
            foreach ($plan as $receipt) {
                $this->recordBookingPayment(
                    $booking,
                    $receipt['amount'],
                    $paymentMethod,
                    $receipt['date'],
                    $notes,
                    $receipt['days']
                );
            }
        });

        return count($plan);
    }

    /**
     * Return every booking id matching the current list filters (search/month),
     * across all pages. Backs the React "Select all N matching" affordance so a
     * bulk action can act on the whole filtered set — the pre-Inertia behaviour,
     * where the client-side DataTable held every row in the DOM. The ids feed
     * the existing bulk endpoints, which enforce their own delete/edit
     * permission; this read-only endpoint is gated on the same `manage booking`
     * permission as the list itself and stays scoped to the tenant.
     */
    public function matchingIds(Request $request)
    {
        if (! \Auth::user()->can('manage booking')) {
            abort(403);
        }

        return response()->json([
            'ids' => $this->filteredBookings($request)->pluck('id'),
        ]);
    }

    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bookings');

        $headers = [
            'NOM & PRENOM',
            'DATE DEBUT (JJ/MM/AAAA)',
            'HEURE DEBUT (HH:MM)',
            'LA MARQUE',
            'IMMATRICULATION',
            'DATE RETOUR (JJ/MM/AAAA)',
            'HEURE RETOUR (HH:MM)',
            'PERIODE',
            'PRIX',
            'METHOD',
        ];

        $colWidths = [25, 22, 18, 16, 18, 22, 18, 12, 14, 16];

        foreach ($headers as $col => $header) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue($colLetter . '1', $header);
            $sheet->getColumnDimension($colLetter)->setWidth($colWidths[$col]);
        }

        // Style header row
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        // Example rows
        $sheet->fromArray([
            'HASSAN SALEM',
            date('d/m/Y'),
            '09:00',
            'CLIO V',
            '69742/A/44',
            date('d/m/Y', strtotime('+2 days')),
            '11:00',
            2,
            600,
            'espèce',
        ], null, 'A2');

        $sheet->fromArray([
            'NIDAL ALAOUI',
            date('d/m/Y'),
            '10:00',
            'CUPRA',
            '73738/A/44',
            date('d/m/Y', strtotime('+10 days')),
            '21:00',
            10,
            '',
            'virement',
        ], null, 'A3');

        // Notes sheet
        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Notes');
        $notes->setCellValue('A1', 'COLONNE');
        $notes->setCellValue('B1', 'DESCRIPTION');
        $notesData = [
            ['NOM & PRENOM',       'Nom complet du conducteur (doit exister dans le système)'],
            ['DATE DEBUT',         'Format JJ/MM/AAAA  ex: 01/02/2026'],
            ['HEURE DEBUT',        'Format HH:MM  ex: 09:00'],
            ['LA MARQUE',          'Marque/modèle du véhicule  ex: IBIZA, CLIO V'],
            ['IMMATRICULATION',    'Plaque d\'immatriculation (doit exister dans le système)'],
            ['DATE RETOUR',        'Format JJ/MM/AAAA  ex: 03/02/2026'],
            ['HEURE RETOUR',       'Format HH:MM  ex: 18:30'],
            ['PERIODE',            'Nombre de jours de location (informatif)'],
            ['PRIX',               'Montant total en DH (laisser vide si inconnu)'],
            ['METHOD',             'Mode de paiement  ex: Espece, Virement bancaire, Carte, Chèque (laisser vide si inconnu)'],
        ];
        foreach ($notesData as $i => $nd) {
            $notes->setCellValue('A' . ($i + 2), $nd[0]);
            $notes->setCellValue('B' . ($i + 2), $nd[1]);
        }
        $notes->getColumnDimension('A')->setWidth(22);
        $notes->getColumnDimension('B')->setWidth(55);
        $notes->getStyle('A1:B1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        ]);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        $filename = 'bookings_import_template.xlsx';

        $tempFile = storage_path('app/booking_tpl_' . uniqid() . '.xlsx');
        $writer->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ])->deleteFileAfterSend(true);
    }

    public function importExcel(Request $request)
    {
        if (!\Auth::user()->can('create booking')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv']);

        $file = $request->file('file');

        try {
            $spreadsheet = IOFactory::load($file->getPathname());
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Could not read the file: ') . $e->getMessage());
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, false);

        if (count($rows) < 2) {
            return redirect()->back()->with('error', __('The file has no data rows.'));
        }

        $pid         = parentId();
        $driverRole  = Role::where('name', 'driver')->where('parent_id', $pid)->first();

        // Cache already-loaded drivers and vehicles to avoid duplicate DB hits per row
        $driversCache  = User::where('parent_id', $pid)->where('type', 'driver')->get()->keyBy(fn($u) => strtolower(trim($u->name)));
        $vehiclesCache = Vehicle::where('parent_id', $pid)->get()->keyBy(fn($v) => Vehicle::plateKey($v->license_plate));

        // Pre-fetch counters and email set to eliminate per-row queries
        $nextDriverId   = (Driver::where('parent_id', $pid)->max('driver_id') ?? 0) + 1;
        $nextVehicleId  = (Vehicle::where('parent_id', $pid)->max('vehicle_id') ?? 0) + 1;
        $existingEmails = User::pluck('email')->mapWithKeys(fn($e) => [$e => true])->all();

        $imported = 0;
        $skipped  = [];

        try {
        foreach ($rows as $rowIndex => $row) {
            if ($rowIndex === 0) {
                continue; // skip header
            }

            // Columns: NOM & PRENOM | DATE DEBUT | HEURE | LA MARQUE | IMMATRICULATION | DATE RETOUR | HEURE RETOUR | PERIODE | PRIX | METHOD
            [
                $driverName,
                $startDate,
                $startTime,
                $marque,
                $licensePlate,
                $endDate,
                $endTime,
                $periode,
                $prix,
                $method,
            ] = array_pad($row, 10, null);

            $driverName  = trim((string) $driverName);
            $licensePlate = trim((string) $licensePlate);
            $marque       = trim((string) $marque);
            $lineNum      = $rowIndex + 1;
            $errors       = [];

            // Skip fully empty rows
            if (empty(array_filter(array_map('trim', array_map('strval', $row))))) {
                continue;
            }

            try {
                // Validate required fields before auto-creating
                if (empty($driverName)) {
                    $errors[] = __('The NOM & PRENOM column is empty');
                }
                if (empty($licensePlate)) {
                    $errors[] = __('The IMMATRICULATION column is empty');
                }

                $startDateParsed = $this->parseExcelDate($startDate);
                $endDateParsed   = $this->parseExcelDate($endDate);

                if (!$startDateParsed) {
                    $errors[] = __('Invalid start date :value', ['value' => $startDate]);
                }
                if (!$endDateParsed) {
                    $errors[] = __('Invalid end date :value', ['value' => $endDate]);
                }

                $startTimeFmt = $this->parseExcelTime($startTime) ?? '00:00:00';
                $endTimeFmt   = $this->parseExcelTime($endTime) ?? '00:00:00';

                // The rental must end after it starts. This rejects rows whose
                // start is not before the end — e.g. day/month-swapped dates (a
                // May return parsed as March) that would otherwise import
                // silently with a zero/negative duration.
                if ($startDateParsed && $endDateParsed) {
                    $startAt = Carbon::parse($startDateParsed . ' ' . $startTimeFmt);
                    $endAt   = Carbon::parse($endDateParsed . ' ' . $endTimeFmt);
                    if ($startAt >= $endAt) {
                        $errors[] = __('Start date (:start) must be before end date (:end)', [
                            'start' => $startDateParsed . ' ' . $startTimeFmt,
                            'end'   => $endDateParsed . ' ' . $endTimeFmt,
                        ]);
                    }
                }

                if (!empty($errors)) {
                    $skipped[] = [
                        'row'    => $lineNum,
                        'nom'    => $driverName,
                        'plaque' => $licensePlate,
                        'debut'  => (string) $startDate,
                        'retour' => (string) $endDate,
                        'errors' => $errors,
                    ];
                    continue;
                }

                // Auto-create driver if not found
                $driverKey = strtolower($driverName);
                if (!isset($driversCache[$driverKey])) {
                    $emailBase = strtolower(str_replace([' ', "'"], ['.', ''], $driverName));
                    $email     = $emailBase . '@import.local';
                    $suffix    = 1;
                    while (isset($existingEmails[$email])) {
                        $email = $emailBase . $suffix . '@import.local';
                        $suffix++;
                    }
                    $existingEmails[$email] = true; // reserve for this import session

                    $newUser           = new User();
                    $newUser->name     = $driverName;
                    $newUser->email    = $email;
                    $newUser->password = \Hash::make('123456');
                    $newUser->type     = 'driver';
                    $newUser->profile  = 'avatar.png';
                    $newUser->lang     = 'english';
                    $newUser->parent_id = $pid;
                    $newUser->save();

                    if ($driverRole) {
                        $newUser->assignRole($driverRole);
                    }

                    // Create Driver profile record
                    $newDriver            = new Driver();
                    $newDriver->driver_id = $nextDriverId++;
                    $newDriver->user_id  = $newUser->id;
                    $newDriver->parent_id = $pid;
                    $newDriver->save();

                    $driversCache[$driverKey] = $newUser;
                }
                $driver = $driversCache[$driverKey];

                // Auto-create vehicle if not found. Key on the normalized plate
                // so NBSP/whitespace variants from the spreadsheet reuse the
                // existing vehicle instead of creating a duplicate (IST-229).
                $plateKey = Vehicle::plateKey($licensePlate);
                if (!isset($vehiclesCache[$plateKey])) {
                    $newVehicle             = new Vehicle();
                    $newVehicle->vehicle_id = $nextVehicleId++;
                    $newVehicle->name         = $marque ?: $licensePlate;
                    $newVehicle->model        = $marque ?: null;
                    $newVehicle->license_plate = Vehicle::normalizePlate($licensePlate);
                    $newVehicle->parent_id    = $pid;
                    $newVehicle->save();

                    $vehiclesCache[$plateKey] = $newVehicle;
                }
                $vehicle = $vehiclesCache[$plateKey];

                $amount        = (is_numeric($prix) && $prix >= 0) ? (int) $prix : 0;
                $paymentMethod = $this->normalizeImportPaymentMethod($method);

                $booking = new Booking();
                $booking->booking_id        = $this->bookingNumber();
                $booking->vehicle           = $vehicle->id;
                $booking->driver            = $driver->id;
                $booking->start_date        = $startDateParsed;
                $booking->start_time        = $startTimeFmt;
                $booking->end_date          = $endDateParsed;
                $booking->end_time          = $endTimeFmt;
                $booking->pickup_address    = 0;
                $booking->drop_off_address  = 0;
                $booking->status            = 'yet_to_start';
                $booking->amount            = $amount;
                $booking->payment_status    = 'impaye';
                $booking->payment_method    = $paymentMethod;
                $booking->daily_price_final = 0;
                $booking->notes             = null;
                $booking->vehicle_details   = [
                    'id'            => $vehicle->id,
                    'name'          => $vehicle->name,
                    'license_plate' => $vehicle->license_plate,
                ];
                $booking->parent_id = $pid;
                $booking->save();
                $imported++;
            } catch (\Exception $e) {
                $skipped[] = [
                    'row'    => $lineNum,
                    'nom'    => $driverName,
                    'plaque' => $licensePlate,
                    'debut'  => (string) $startDate,
                    'retour' => (string) $endDate,
                    'errors' => [$e->getMessage()],
                ];
            }
        }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Import failed: ') . $e->getMessage());
        }

        if (!empty($skipped)) {
            session()->flash('import_skipped', $skipped);
            session()->flash('reopen_import_modal', true);
        }

        $msg = $imported > 0
            ? __(':count booking(s) imported successfully.', ['count' => $imported])
            : __('No bookings imported.');

        return redirect()->route('booking.index')->with('success', $msg);
    }

    /**
     * Normalize a raw METHOD value from an imported reservation row to one of
     * the canonical payment methods (BookingPayment::$paymentMethod).
     *
     * Excel files come in with accented, lowercase, or misspelled French
     * ('espèce', 'chèque', 'virment'), none of which match the canonical
     * values used across the rest of the app. Matching is accent- and
     * case-insensitive via Str::ascii(), so an already-canonical value round
     * trips back to itself. Empty stays null; an unrecognized value is kept as
     * its trimmed original (no data loss).
     */
    private function normalizeImportPaymentMethod($raw): ?string
    {
        if ($raw === null || trim((string) $raw) === '') {
            return null;
        }

        $trimmed = trim((string) $raw);
        $key     = Str::lower(Str::ascii($trimmed));

        $map = [
            'espece'    => 'Espece',
            'especes'   => 'Espece',
            'cash'      => 'Espece',
            'liquide'   => 'Espece',
            'carte'     => 'Carte',
            'cartes'    => 'Carte',
            'virement'  => 'Virement bancaire',
            'virements' => 'Virement bancaire',
            'virment'   => 'Virement bancaire', // common typo seen in real files
            'cheque'    => 'Chèque',
            'cheques'   => 'Chèque',
        ];

        return $map[$key] ?? $trimmed;
    }

    /**
     * Both parsers now live in App\Support\ExcelValue so the traffic-violation
     * importer reads spreadsheet dates by the exact same rules — in particular
     * the day-first (d/m/Y) contract from IST-231. Behavior is unchanged.
     */
    private function parseExcelDate($value): ?string
    {
        return ExcelValue::date($value);
    }

    private function parseExcelTime($value): ?string
    {
        return ExcelValue::time($value);
    }

    public function bookingNumber()
    {
        $latest = Booking::where('parent_id', parentId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->booking_id + 1;
    }

    public function paymentCreate($id)
    {
        if (!\Auth::user()->can('create booking payment')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $booking = $this->tenantBooking($id);
        if (!$booking) {
            abort(404);
        }
        $paymentMethod = BookingPayment::$paymentMethod;
        
        // Calculate default quantity (total days adjusted by payment amount)
        $startDate = Carbon::parse($booking->start_date);
        $endDate = Carbon::parse($booking->end_date);
        $totalDays = max(1, $startDate->diffInDays($endDate));
        $dueAmount = $booking->getTotalDueAmount();
        $totalDaysAmount = $booking->amount > 0 ? ($dueAmount * $totalDays) / $booking->amount : 0;
        $defaultQuantity = max(1, round($totalDaysAmount));
        
        return view('booking.payment', compact('booking', 'paymentMethod', 'defaultQuantity'));
    }

    public function paymentStore(Request $request, $id)
    {
        if (\Auth::user()->can('create booking payment')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'amount' => 'required|numeric',
                    'date' => 'required',
                    'payment_method' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                if (!$request->hasHeader('X-Inertia') && $request->ajax()) {
                    return response()->json(['status' => 'error', 'message' => $messages->first()], 422);
                }
                return redirect()->back()->withErrors($messages->toArray())->withInput();
            }
            // Amount must be > 0
            $rawAmount = $request->amount;
            // Replace potential comma decimal delimiter
            if (is_string($rawAmount)) {
                $rawAmount = str_replace(',', '.', $rawAmount);
            }
            $numericAmount = (float)$rawAmount;
            if ($numericAmount <= 0) {
                $msg = __('Amount 0');
                if (!$request->hasHeader('X-Inertia') && $request->ajax()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 422);
                }
                return redirect()->back()->withErrors(['amount' => $msg])->withInput();
            }
            // Business rule: Cash (Espece) payments cannot exceed the legal
            // ceiling. When the cash_split feature is on we split the payment
            // into receipts each within the cap (Moroccan CGI art. 193) rather
            // than rejecting it; otherwise the payment is refused as before.
            $cashMax = (float) config('client.cash_payment_max', 5000);
            $paymentMethodNormalized = strtolower($request->payment_method);
            $isCash = $paymentMethodNormalized === 'espece';
            $booking = Booking::find($id);

            if ($isCash && $numericAmount > $cashMax) {
                if (!feature('cash_split')) {
                    $msg = __('Cash payments over 5000 are not allowed. Please choose another method.');
                    if (!$request->hasHeader('X-Inertia') && $request->ajax()) {
                        return response()->json(['status' => 'error', 'message' => $msg], 422);
                    }
                    return redirect()->back()->withErrors(['amount' => $msg]);
                }

                // Split into several receipts, each <= cashMax, on distinct days
                // across the rental period, with the rental days apportioned.
                $count = $this->recordSplitCashPayment(
                    $booking,
                    $numericAmount,
                    $request->payment_method,
                    $request->date,
                    $request->notes,
                    ($request->has('quantity') && $request->quantity > 0) ? (int) $request->quantity : null
                );

                $msg = __('Split into :count receipts.', ['count' => $count]);
                if (!$request->hasHeader('X-Inertia') && $request->ajax()) {
                    return response()->json(['status' => 'success', 'message' => $msg]);
                }
                return redirect()->back()->with('success', $msg);
            }

            // Records the payment + facture and updates the booking status.
            // Shared with the bulk "mark as paid" action so the two stay identical.
            $this->recordBookingPayment(
                $booking,
                $numericAmount,
                $request->payment_method,
                $request->date,
                $request->notes,
                ($request->has('quantity') && $request->quantity > 0) ? (int) $request->quantity : null
            );

            if (!$request->hasHeader('X-Inertia') && $request->ajax()) {
                return response()->json(['status' => 'success', 'message' => __('Booking payment successfully created.')]);
            }
            return redirect()->back()->with('success', __('Booking payment successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    /**
     * Preview how a cash payment over the ceiling would be split into receipts.
     * Display-only for the payment dialog; paymentStore recomputes the same plan
     * (same service, same inputs) as the authoritative source when confirmed.
     */
    public function paymentSplitPreview(Request $request, $id)
    {
        if (!\Auth::user()->can('create booking payment')) {
            return response()->json(['message' => __('Permission Denied.')], 403);
        }

        $booking = Booking::where('parent_id', parentId())->find($id);
        if (!$booking) {
            return response()->json(['message' => __('Not found')], 404);
        }

        $cashMax = (float) config('client.cash_payment_max', 5000);
        $rawAmount = is_string($request->amount) ? str_replace(',', '.', $request->amount) : $request->amount;
        $amount = (float) $rawAmount;
        $method = strtolower((string) $request->payment_method);

        // Only cash over the ceiling with the feature on actually splits.
        if (!feature('cash_split') || $method !== 'espece' || $amount <= $cashMax) {
            return response()->json(['split' => false]);
        }

        $quantity  = ($request->has('quantity') && $request->quantity > 0) ? (int) $request->quantity : null;
        $totalDays = $this->deriveInvoiceDays($booking, $amount, $quantity);

        $plan = app(CashPaymentSplitter::class)->plan(
            $amount,
            Carbon::parse($booking->start_date),
            Carbon::parse($booking->end_date),
            $totalDays,
            $cashMax
        );

        return response()->json([
            'split'    => true,
            'count'    => count($plan),
            'total'    => round($amount, 2),
            'cash_max' => $cashMax,
            'receipts' => $plan,
        ]);
    }

    public function paymentDestroy($booking_id, $id)
    {
        if (\Auth::user()->can('delete booking payment')) {
            // Resolve + authorise the booking BEFORE any write, and constrain
            // the payment to that booking (it used to be found by id alone,
            // deleted, and only then was the booking looked up - unscoped).
            $bookinmg = $this->tenantBooking($booking_id);
            if (!$bookinmg) {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }
            $payment = BookingPayment::where('booking_id', $bookinmg->id)->find($id);
            if (!$payment) {
                return redirect()->back()->with('error', __('Permission Denied.'));
            }

            \DB::transaction(function () use ($payment) {
                // Delete linked TVA records created for this payment via idpaiment
                Tva::where('idpaiment', $payment->id)->delete();
                $payment->delete();
            });
            $bookinmg->unsetRelation('payments');

            if ($bookinmg->getTotalDueAmount() <= 0) {
                $status = 'paye';
            } elseif ($bookinmg->getTotalDueAmount() == $bookinmg->getTotalAmount()) {
                $status = 'impaye';
            } else {
                $status = 'partiellement_paye';
            }
            Booking::statusChange($bookinmg->id, $status);
            return redirect()->back()->with('success', __('Booking payment successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied!'));
        }
    }

    // public function planning()
    // {
    //     // Skip auth check for testing - replace with proper auth later
    //     // if (\Auth::user()->can('manage planning')) {

    //         // Temporarily hardcode parent_id to test (should use parentId() when authenticated properly)
    //         $parentId = 2;
    //         $bookings = Booking::where('parent_id', $parentId)->get();
    //         $vehicles = Vehicle::where('parent_id', $parentId)->get();

    //         // Simple vehicle data - one row per vehicle
    //         $vehicleData = [];
    //         foreach ($vehicles as $vehicle) {
    //             $vehicleArr = [
    //                 'id' => (string)$vehicle->id, // Ensure it's a string
    //                 'title' => $vehicle->name . ' - ' . $vehicle->license_plate,
    //             ];
    //             $vehicleData[] = $vehicleArr;
    //         }

    //         // Simple booking data - each booking on its vehicle's row
    //         $bookingData = [];
    //         foreach ($bookings as $booking) {
    //             $driver = !empty($booking->drivers) ? $booking->drivers->name : '';

    //             // Use hardcoded prefix instead of function for testing
    //             $prefix = 'BOK-'; // Replace with bookingPrefix() later

    //             $booked = [
    //                 'id' => $booking->id,
    //                 'resourceId' => (string)$booking->vehicle, // Ensure it's a string and matches vehicle ID
    //                 'title' => $prefix . sprintf('%04d', $booking->booking_id) . ' - ' . $driver,
    //                 'start' => $booking->start_date . 'T' . $booking->start_time,
    //                 'end'   => $booking->end_date . 'T' . $booking->end_time,
    //                 'url' => route('booking.show', Crypt::encrypt($booking->id)),
    //             ];
    //             $bookingData[] = $booked;
    //         }

    //         return view('booking.planning', compact('bookingData', 'vehicleData'));
    //     // } else {
    //     //     return redirect()->back()->with('error', __('Permission Denied.'));
    //     // }
    // }

    public function planning()
    {
        if (!\Auth::user()->can('manage booking')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $parentId = parentId();
        $bookings = Booking::where('parent_id', $parentId)->with('drivers')->get();
        $vehicles = Vehicle::where('parent_id', $parentId)->get();

        $vehicleData = [];
        foreach ($vehicles as $vehicle) {
            $vehicleData[] = [
                'id'    => (string) $vehicle->id,
                'title' => $vehicle->name . ' - ' . $vehicle->license_plate,
            ];
        }

        $bookingData = [];
        foreach ($bookings as $booking) {
            $driver       = !empty($booking->drivers) ? $booking->drivers->name : '';
            $bookingData[] = [
                'id'         => $booking->id,
                'resourceId' => (string) $booking->vehicle,
                'title'      => 'BOK-' . sprintf('%04d', $booking->booking_id) . ' - ' . $driver,
                'start'      => $booking->start_date . 'T' . $booking->start_time,
                'end'        => $booking->end_date . 'T' . $booking->end_time,
                'url'        => route('booking.show', Crypt::encrypt($booking->id)),
            ];
        }

        return Inertia::render('Booking/Planning', [
            'bookingData' => $bookingData,
            'vehicleData' => $vehicleData,
        ]);
    }
    /**
     * A booking the current user may act on: same tenant as parentId(),
     * super admin exempt (parentId() returns the SA's own id, never a
     * booking's parent_id). Mirrors paymentSplitPreview / show.
     */
    private function tenantBooking($id): ?Booking
    {
        $query = Booking::query();
        if (\Auth::user()->type !== 'super admin') {
            $query->where('parent_id', parentId());
        }

        return $query->find($id);
    }
}
