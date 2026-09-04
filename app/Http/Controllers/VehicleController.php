<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Option;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Psy\Readline\Hoa\Console;

class VehicleController extends Controller
{

    public function index(Request $request)
    {
        if (! \Auth::user()->can('manage vehicle')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $search = trim((string) $request->get('search', ''));

        $vehicles = Vehicle::where('parent_id', '=', parentId())
            ->with('types')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('model', 'like', "%{$search}%")
                        ->orWhere('license_plate', 'like', "%{$search}%")
                        ->orWhere('engine_type', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $payload = $vehicles->through(function ($vehicle) {
            $data = $vehicle->toArray();
            $data['daily_rate_formatted'] = priceFormat($vehicle->daily_rate);
            $data['vehicle_id_display'] = vehiclePrefix() . $vehicle->vehicle_id;
            $data['type_label'] = !empty($vehicle->types) ? $vehicle->types->type : null;
            $data['registration_expiry_date_display'] = !empty($vehicle->registration_expiry_date) ? dateFormat($vehicle->registration_expiry_date) : null;
            return $data;
        });

        return Inertia::render('Vehicle/Index', [
            'vehicles' => $payload,
            'filters' => ['search' => $search],
        ]);
    }


    public function create()
    {
        $types = VehicleType::where('parent_id', parentId())->get()->pluck('type', 'id');
        $types->prepend(__('Select Type'), '');
        $gearbox = Vehicle::$gearbox;
        $fuelType = Vehicle::$fuelType;
        $option = Option::where('parent_id', parentId())->get()->pluck('name', 'id');

        return Inertia::render('Vehicle/Create', compact('types', 'fuelType', 'gearbox', 'option'));
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create vehicle')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'type' => 'required',
                    'name' => 'required',
                    'model' => 'required',
                    'engine_type' => 'required',
                    'engine_no' => 'required',
                    'license_plate' => 'required',
                    'registration_expiry_date' => 'required',
                    // 'document' => 'required',
                    'picture'=>'mimes:jpeg,png,jpg,gif,svg|max:2048',
                    'daily_rate' => 'required',
                    'year_of_ﬁrst_immatriculation' => 'required',
                    'gearbox' => 'required',
                    'fuel_type' => 'required',
                    'number_of_seats' => 'required',
                    'kilometers' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            // Reject a plate that already exists for this tenant (case/whitespace
            // insensitive). Guards against the duplicate-vehicle data the picker
            // surfaced (IST-229); the field error wires into the form.
            if ($this->licensePlateExists($request->license_plate)) {
                return redirect()->back()
                    ->withErrors(['license_plate' => __('A vehicle with this license plate already exists.')])
                    ->withInput();
            }

            $vehicle = new Vehicle();
            $vehicle->vehicle_id = $this->vehicleNumber();
            $vehicle->type = $request->type;
            $vehicle->name = $request->name;
            $vehicle->model = $request->model;
            $vehicle->engine_type = $request->engine_type;
            $vehicle->engine_no = !empty($request->engine_no) ? $request->engine_no : null;
            $vehicle->registration_expiry_date = !empty($request->registration_expiry_date) ? $request->registration_expiry_date : null;
            $vehicle->license_plate = Vehicle::normalizePlate($request->license_plate);
            $vehicle->document = $request->document;
            if (!empty($request->picture)) {
                $pictureFilenameWithExt = $request->file('picture')->getClientOriginalName();
                $pictureFilename = pathinfo($pictureFilenameWithExt, PATHINFO_FILENAME);
                $pictureExtension = $request->file('picture')->getClientOriginalExtension();
                $pictureFileName = $pictureFilename . '_' . time() . '.' . $pictureExtension;
                // 'public' disk → storage/app/public/upload/picture, served at /storage/upload/picture
                $request->file('picture')->storeAs('upload/picture/', $pictureFileName, 'public');
                $vehicle->picture = $pictureFileName;
            }
            $vehicle->daily_rate = $request->daily_rate;
            $vehicle->year_of_ﬁrst_immatriculation = !empty($request->year_of_ﬁrst_immatriculation) ? $request->year_of_ﬁrst_immatriculation : 0;
            $vehicle->gearbox = $request->gearbox;
            $vehicle->fuel_type = $request->fuel_type;
            $vehicle->number_of_seats = $request->number_of_seats;
            $vehicle->kilometers = $request->kilometers;
            $vehicle->option = !empty($request->option) ? implode(',', $request->option) : null;
            $vehicle->notes = !empty($request->notes) ? $request->notes : null;
            $vehicle->parent_id = parentId();
            if (!empty($request->document)) {
                $documentFilenameWithExt = $request->file('document')->getClientOriginalName();
                $documentFilename = pathinfo($documentFilenameWithExt, PATHINFO_FILENAME);
                $documentExtension = $request->file('document')->getClientOriginalExtension();
                $documentFileName = $documentFilename . '_' . time() . '.' . $documentExtension;
                // 'public' disk → storage/app/public/upload/document, served at /storage/upload/document
                $request->file('document')->storeAs('upload/document/', $documentFileName, 'public');
                $vehicle->document = $documentFileName;
            }
            $vehicle->save();


            return redirect()->route('vehicle.index')->with('success', __('Vehicle successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show(Vehicle $vehicle)
    {
        $year = $vehicle->year_of_ﬁrst_immatriculation;
        $options = $vehicle->options();

        $payload = array_merge($vehicle->toArray(), [
            'vehicle_id_display' => vehiclePrefix() . $vehicle->vehicle_id,
            'type_label' => !empty($vehicle->types) ? $vehicle->types->type : null,
            'registration_expiry_date_display' => !empty($vehicle->registration_expiry_date) ? dateFormat($vehicle->registration_expiry_date) : null,
            'year_of_first_immatriculation_display' => (!empty($year) && $year != 0) ? $year : null,
            'gearbox_label' => Vehicle::$gearbox[$vehicle->gearbox] ?? null,
            'fuel_type_label' => Vehicle::$fuelType[$vehicle->fuel_type] ?? null,
            'option_names' => !empty($options) ? $options->pluck('name')->toArray() : [],
            'daily_rate_formatted' => priceFormat($vehicle->daily_rate),
        ]);
        return Inertia::render('Vehicle/Show', ['vehicle' => $payload]);
    }


    public function edit(Vehicle $vehicle)
    {
        $gearbox = Vehicle::$gearbox;
        $fuelType = Vehicle::$fuelType;
        $types = VehicleType::where('parent_id', parentId())->get()->pluck('type', 'id');
        $types->prepend(__('Select Type'), '');
        $option = Option::where('parent_id', parentId())->get()->pluck('name', 'id');

        return Inertia::render('Vehicle/Edit', compact('types', 'vehicle', 'gearbox', 'fuelType', 'option'));
    }


    public function update(Request $request, Vehicle $vehicle)
    {
        if (\Auth::user()->can('edit vehicle')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'type' => 'required',
                    'name' => 'required',
                    'model' => 'required',
                    'engine_type' => 'required',
                    'license_plate' => 'required',
                    'daily_rate' => 'required',
                    'gearbox' => 'required',
                    'fuel_type' => 'required',
                    'number_of_seats' => 'required',
                    'kilometers' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            // Reject a plate already used by ANOTHER vehicle for this tenant
            // (case/whitespace insensitive); keeping this vehicle's own plate is fine.
            if ($this->licensePlateExists($request->license_plate, $vehicle->id)) {
                return redirect()->back()
                    ->withErrors(['license_plate' => __('A vehicle with this license plate already exists.')])
                    ->withInput();
            }

            $vehicle->type = $request->type;
            $vehicle->name = $request->name;
            $vehicle->model = $request->model;
            $vehicle->engine_type = $request->engine_type;
            $vehicle->engine_no = !empty($request->engine_no) ? $request->engine_no : null;
            $vehicle->registration_expiry_date = !empty($request->registration_expiry_date) ? $request->registration_expiry_date : null;
            $vehicle->license_plate = Vehicle::normalizePlate($request->license_plate);
            $vehicle->daily_rate = $request->daily_rate;
            $vehicle->year_of_ﬁrst_immatriculation = !empty($request->year_of_ﬁrst_immatriculation) ? $request->year_of_ﬁrst_immatriculation : 0;
            $vehicle->gearbox = $request->gearbox;
            $vehicle->fuel_type = $request->fuel_type;
            $vehicle->number_of_seats = $request->number_of_seats;
            $vehicle->kilometers = $request->kilometers;
            $vehicle->option = !empty($request->option) ? implode(',', $request->option) : null;
            $vehicle->notes = $request->notes;
            if (!empty($request->document)) {
                $documentFilenameWithExt = $request->file('document')->getClientOriginalName();
                $documentFilename = pathinfo($documentFilenameWithExt, PATHINFO_FILENAME);
                $documentExtension = $request->file('document')->getClientOriginalExtension();
                $documentFileName = $documentFilename . '_' . time() . '.' . $documentExtension;
                // 'public' disk → storage/app/public/upload/document, served at /storage/upload/document
                $request->file('document')->storeAs('upload/document/', $documentFileName, 'public');
                $vehicle->document = $documentFileName;
            }
            if (!empty($request->picture)) {
                $pictureFilenameWithExt = $request->file('picture')->getClientOriginalName();
                $pictureFilename = pathinfo($pictureFilenameWithExt, PATHINFO_FILENAME);
                $pictureExtension = $request->file('picture')->getClientOriginalExtension();
                $pictureFileName = $pictureFilename . '_' . time() . '.' . $pictureExtension;
                // 'public' disk → storage/app/public/upload/picture, served at /storage/upload/picture
                $request->file('picture')->storeAs('upload/picture/', $pictureFileName, 'public');
                $vehicle->picture = $pictureFileName;
            }

            $vehicle->save();

            return redirect()->route('vehicle.index')->with('success', __('Vehicle successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(Vehicle $vehicle)
    {
        if (\Auth::user()->can('delete vehicle')) {
            $vehicle->delete();
            return redirect()->route('vehicle.index')->with('success', __('Vehicle successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function vehicleNumber()
    {
        $max = Vehicle::where('parent_id', parentId())->max('vehicle_id');
        return ($max ?? 0) + 1;
    }

    /**
     * Does another vehicle in this tenant already use this license plate?
     * Comparison is case- and whitespace-insensitive so "73738/A/44" and
     * " 73738/a/44 " collide. Pass $ignoreId on edit to exclude the row itself.
     */
    private function licensePlateExists(?string $plate, ?int $ignoreId = null): bool
    {
        $key = Vehicle::plateKey($plate);
        if ($key === '') {
            return false;
        }

        // Compare on the normalized key in PHP rather than SQL: a plain
        // LOWER(TRIM()) can't strip the non-breaking spaces that slip in via
        // imports, so it would miss visually-identical plates. Vehicle counts
        // per tenant are small, so loading them to compare is cheap.
        return Vehicle::where('parent_id', parentId())
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get(['id', 'license_plate'])
            ->contains(fn ($v) => Vehicle::plateKey($v->license_plate) === $key);
    }

    public function getVehicleRateCalculation(Request $request)
    {
        $request->validate([
            'pickup_place'   => ['nullable', tenantPlaceRule()],
            'drop_off_place' => ['nullable', tenantPlaceRule()],
        ]);

        $vehicle = Vehicle::find($request->vahicle_id);
        $start_date_time = $request->start_date_time;
        $end_date_time = $request->end_date_time;
        $addons = $request->addons;

        $pickup_place = $request->pickup_place;
        $drop_off_place = $request->drop_off_place;
        $daily_price = $request->daily_price;


        if (!empty($vehicle) && !empty($start_date_time) && !empty($end_date_time)) {
            // vehicleRateCalculation() runs new DateTime() on these dates, which
            // throws on unparseable input. Bail gracefully instead of letting it
            // surface as an unhandled 500 (JAVASCRIPT-4 sibling path).
            try {
                new \DateTime($start_date_time);
                new \DateTime($end_date_time);
            } catch (\Exception $e) {
                return json_encode([]);
            }

            $daily_rate = !empty($vehicle->daily_rate) && ($vehicle->daily_rate > 0) ? $vehicle->daily_rate : 0;
            $data = vehicleRateCalculation($daily_rate, $start_date_time, $end_date_time);

            $addonAmount = 0;
            if (!empty($addons)) {
                $addonAmount = addonsRateCalculation($request->addons, $data['considerDays']);
                $specificAddonCalculation = specificAddonCalculation($request->addons, $data['considerDays']);
                $specificAddonString = '';
                foreach ($specificAddonCalculation as $key => $value) {
                    $specificAddonString .= "<tr><td>" . $value['addon'] . "</td><td>" . $value['final_price'] . "</td></tr>";
                }
                $data['specificAddonCalculation'] = $specificAddonString;
            }else {
                $data['specificAddonCalculation'] = '';
            }

            $data['addonAmount'] = $addonAmount;




            if ($request->daychange != 1) {
                $data['duration'] = $data['considerDays'] . ' * ' . $daily_rate . ' = ' . priceFormat($data['totalRate']);
            } else {
                // $data = "" ;
                $newdaily_rate = !empty($daily_price) && ($daily_price > 0) ? $daily_price : 0;
                $data = vehicleRateCalculation($newdaily_rate, $start_date_time, $end_date_time);

                $data['duration'] = $data['considerDays'] . ' * ' . $newdaily_rate . ' = ' . priceFormat($data['totalRate']);

                $addonAmount = 0;
                if (!empty($addons)) {
                    $addonAmount = addonsRateCalculation($request->addons, $data['considerDays']);
                    $specificAddonCalculation = specificAddonCalculation($request->addons, $data['considerDays']);
                    $specificAddonString = '';
                    foreach ($specificAddonCalculation as $key => $value) {
                        $specificAddonString .= "<tr><td>" . $value['addon'] . "</td><td>" . $value['final_price'] . "</td></tr>";
                    }
                    $data['specificAddonCalculation'] = $specificAddonString;
                }
                $data['addonAmount'] = $addonAmount;

            }

            if (!empty($pickup_place)) {
                $pickupPlaceAmount = placesRateCalculation($pickup_place);
            } else {
                $pickupPlaceAmount = 0;
            }

            if (!empty($drop_off_place)) {
                $dropPlaceAmount = placesRateCalculation($drop_off_place);
            } else {
                $dropPlaceAmount = 0;
            }
            $placeAmount = $pickupPlaceAmount + $dropPlaceAmount;

            $data['placeAmount'] = $placeAmount;


            // Add daily price to view
            $data['daily_price'] = $daily_rate;

            return json_encode($data);
        }
    }

    public function getAvailableVehicle(Request $request)
    {
        $start_date_time = $request->start_date_time;
        $end_date_time = $request->end_date_time;
        if (!empty($start_date_time) && !empty($end_date_time)) {
            try {
                $startDateTime = Carbon::createFromFormat('Y/m/d H:i', $start_date_time);
                $endDateTime = Carbon::createFromFormat('Y/m/d H:i', $end_date_time);
            } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                // Malformed date input (JAVASCRIPT-4): degrade gracefully to an
                // empty result instead of throwing an unhandled 500.
                return json_encode([]);
            }

            $startDateTimeStr = $startDateTime->format('Y-m-d H:i:s');
            $endDateTimeStr = $endDateTime->format('Y-m-d H:i:s');

            $booking = Booking::whereNotIn('status', ['completed', 'cancelled']);
            if (isset($request->booking_id) && !empty($request->booking_id)) {
                $booking->where('id', '!=', $request->booking_id);
            }
            $booking = $booking->where(function ($query) use ($startDateTimeStr, $endDateTimeStr) {
                $query->where(function ($query) use ($startDateTimeStr, $endDateTimeStr) {
                    $query->where(DB::raw('CONCAT(start_date, " ", start_time)'), '>=', $startDateTimeStr)->where(DB::raw('CONCAT(start_date, " ", start_time)'), '<=', $endDateTimeStr);
                })->orWhere(function ($query) use ($startDateTimeStr, $endDateTimeStr) {
                    $query->where(DB::raw('CONCAT(end_date, " ", end_time)'), '>=', $startDateTimeStr)->where(DB::raw('CONCAT(end_date, " ", end_time)'), '<=', $endDateTimeStr);
                })->orWhere(function ($query) use ($startDateTimeStr, $endDateTimeStr) {
                    $query->where(DB::raw('CONCAT(start_date, " ", start_time)'), '<=', $startDateTimeStr)->where(DB::raw('CONCAT(end_date, " ", end_time)'), '>=', $endDateTimeStr);
                });
            })->distinct()->pluck('vehicle')->toArray();

            $vehicles = Vehicle::where('parent_id', parentId())->whereNotIn('id', $booking)->get();
            $data = [];


            foreach ($vehicles as $vehicle) {
                $data[$vehicle->id] = $vehicle->name . ' - ' . $vehicle->license_plate;
            }

            return json_encode($data);
        }
    }
}
