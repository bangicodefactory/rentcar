<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Driver;
use App\Models\Notification;
use App\Models\Place;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Tva;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Role;

class BookingController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage booking')) {
            $bookings = Booking::where('parent_id', '=', parentId())->orderBy('created_at', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('booking.index', compact('bookings'));
    }


    public function create()
    {
        if (\Auth::user()->can('create booking')) {
            $vehicles = Vehicle::where('parent_id', parentId())->get();

            $drivers = User::where('parent_id', parentId())
                ->where('type', 'driver')
                ->orderBy('created_at', 'desc')
                ->get();
            $driversDropdown = ['' => __('Select Driver')] + $drivers->pluck('name', 'id')->toArray();


            $status = Booking::$status;
            $paymentStatus = Booking::$paymentStatus;

            $places = Place::where('parent_id', parentId())->get();
            $addon = Addon::where('parent_id', parentId())->get()->pluck('name', 'id');

            return view('booking.create', compact('vehicles', 'driversDropdown', 'status', 'paymentStatus', 'places', 'addon'));
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
            return view('booking.show', compact('booking', 'settings'));
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

            $drivers = User::where('parent_id', parentId())->where('type', 'driver')->get()->pluck('name', 'id');
            $drivers->prepend(__('Select Driver'), '');

            $status = Booking::$status;
            $paymentStatus = Booking::$paymentStatus;
            $places = Place::where('parent_id', parentId())->get();

            $addon = Addon::where('parent_id', parentId())->get()->pluck('name', 'id');

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

            $vehicles = Vehicle::where('parent_id', parentId())->whereNotIn('id', $booked)->get();

            return view('booking.edit', compact('vehicles', 'drivers', 'status', 'booking', 'paymentStatus', 'places', 'addon'));
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

        Tva::whereIn('booking_id', $ids)->delete();
        Booking::whereIn('id', $ids)->delete();

        return redirect()->route('booking.index')->with('success', __('Selected bookings successfully deleted.'));
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
            ['METHOD',             'Mode de paiement  ex: espèce, virement, chèque, carte (laisser vide si inconnu)'],
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
        $vehiclesCache = Vehicle::where('parent_id', $pid)->get()->keyBy(fn($v) => strtolower(trim($v->license_plate)));

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
                    $errors[] = "NOM & PRENOM est vide";
                }
                if (empty($licensePlate)) {
                    $errors[] = "IMMATRICULATION est vide";
                }

                $startDateParsed = $this->parseExcelDate($startDate);
                $endDateParsed   = $this->parseExcelDate($endDate);

                if (!$startDateParsed) {
                    $errors[] = "date début invalide '{$startDate}'";
                }
                if (!$endDateParsed) {
                    $errors[] = "date retour invalide '{$endDate}'";
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
                    while (User::where('email', $email)->exists()) {
                        $email = $emailBase . $suffix . '@import.local';
                        $suffix++;
                    }

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
                    $latestDriver = Driver::where('parent_id', $pid)->latest()->first();
                    $newDriver           = new Driver();
                    $newDriver->driver_id = $latestDriver ? $latestDriver->driver_id + 1 : 1;
                    $newDriver->user_id  = $newUser->id;
                    $newDriver->parent_id = $pid;
                    $newDriver->save();

                    $driversCache[$driverKey] = $newUser;
                }
                $driver = $driversCache[$driverKey];

                // Auto-create vehicle if not found
                $plateKey = strtolower($licensePlate);
                if (!isset($vehiclesCache[$plateKey])) {
                    $latestVehicle = Vehicle::where('parent_id', $pid)->latest()->first();

                    $newVehicle               = new Vehicle();
                    $newVehicle->vehicle_id   = $latestVehicle ? $latestVehicle->vehicle_id + 1 : 1;
                    $newVehicle->name         = $marque ?: $licensePlate;
                    $newVehicle->model        = $marque ?: null;
                    $newVehicle->license_plate = $licensePlate;
                    $newVehicle->parent_id    = $pid;
                    $newVehicle->save();

                    $vehiclesCache[$plateKey] = $newVehicle;
                }
                $vehicle = $vehiclesCache[$plateKey];

                $startTimeFmt  = $this->parseExcelTime($startTime) ?? '00:00:00';
                $endTimeFmt    = $this->parseExcelTime($endTime) ?? '00:00:00';
                $amount        = (is_numeric($prix) && $prix >= 0) ? (int) $prix : 0;
                $paymentMethod = !empty($method) ? trim((string) $method) : null;

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
            ? "{$imported} réservation(s) importée(s) avec succès."
            : "Aucune réservation importée.";

        return redirect()->route('booking.index')->with('success', $msg);
    }

    private function parseExcelDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Numeric: Excel serial date
        if (is_numeric($value)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        $value = trim((string) $value);

        // Try common date formats
        foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'] as $fmt) {
            $parsed = \DateTime::createFromFormat($fmt, $value);
            if ($parsed && $parsed->format($fmt) === $value) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }

    private function parseExcelTime($value): ?string
    {
        if (empty($value) && $value !== '0') {
            return null;
        }

        // Numeric: Excel fractional day (e.g. 0.375 = 09:00)
        if (is_numeric($value) && $value >= 0 && $value < 1) {
            $seconds = round((float) $value * 86400);
            return gmdate('H:i:s', $seconds);
        }

        $value = trim((string) $value);

        // HH:MM or HH:MM:SS
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $value)) {
            return strlen($value) === 5 ? $value . ':00' : $value;
        }

        return null;
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
        $booking = Booking::find($id);
        $paymentMethod = BookingPayment::$paymentMethod;
        
        // Calculate default quantity (total days adjusted by payment amount)
        $startDate = Carbon::parse($booking->start_date);
        $endDate = Carbon::parse($booking->end_date);
        $totalDays = max(1, $startDate->diffInDays($endDate));
        $dueAmount = $booking->getTotalDueAmount();
        $totalDaysAmount = ($dueAmount * $totalDays) / $booking->amount;
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
                if ($request->ajax()) {
                    return response()->json(['status' => 'error', 'message' => $messages->first()], 422);
                }
                return redirect()->back()->with('error', $messages->first());
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
                if ($request->ajax()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 422);
                }
                return redirect()->back()->with('error', $msg)->withInput();
            }
            // Business rule: Cash (Espece) payments cannot exceed 5000
            $paymentMethodNormalized = strtolower($request->payment_method);
            if ($paymentMethodNormalized === 'espece' && $request->amount > 5000) {
                $msg = __('Cash payments over 5000 are not allowed. Please choose another method.');
                if ($request->ajax()) {
                    return response()->json(['status' => 'error', 'message' => $msg], 422);
                }
                return redirect()->back()->with('error', $msg);
            }
            $payment = new BookingPayment();
            $payment->booking_id = $id;
            $payment->amount = $numericAmount;
            $payment->date = $request->date;
            $payment->payment_method = $request->payment_method;
            $payment->notes = $request->notes;
            $payment->parent_id = parentId();
            $payment->save();
            $booking = Booking::find($id);
            if ($booking->getTotalDueAmount() <= 0) {
                $status = 'paye';
            } else {
                $status = 'partiellement_paye';
            }

            // 🔹 Call Settings table
            $setting = settings();
            // 🔹 User & driver
            $user = User::find($booking->driver);
            $driver1 = Driver::where('user_id', $booking->driver)->first();
            // 🔹 TVA Calculation. 🔹
            
            // Use quantity from request if provided, otherwise calculate
            if ($request->has('quantity') && $request->quantity > 0) {
                $totalDays = (int)$request->quantity;
            } else {
                $startDate = Carbon::parse($booking->start_date);
                $endDate = Carbon::parse($booking->end_date);
                $totalDays = max(1, $startDate->diffInDays($endDate));
                // Calcul totaldays by numericAmount
                $totalDaysAmount = ($numericAmount * $totalDays ) / $booking->amount;
                $totalDays = max(1, round($totalDaysAmount));
            }


            // vehicle_details is cast to object in Booking model; cast to array for safe key access
            $vehicleDetailsObj = $booking->vehicleDetails();
            $vehicle_name = $vehicleDetailsObj->name ?? '';
            $vehicle_license_plate = $vehicleDetailsObj->license_plate ?? '';

            // $totalHT = round($numericAmount * 0.8, 2);
            // $tvaAmount = round($numericAmount * 0.2, 2);
            $totalHT = round($numericAmount / 1.2, 2);
            $tvaAmount = round($numericAmount - $totalHT, 2);


            // Global last facture number (ignoring tenant scoping per new requirement)
            $lastFacture = Tva::orderByDesc('id')->first();
            $lastNumber = 0;
            if ($lastFacture && preg_match('/\d+$/', $lastFacture->facture_number, $matches)) {
                $lastNumber = (int)$matches[0];
            }
            $factureCounter = $lastNumber;
            $factureCounter++;
            $factureNumber = $factureCounter;


            $tva = new Tva();
            $tva->facture_number = $factureNumber;
            $tva->facture_date = $request->date;
            $tva->idpaiment = $payment->id;
            $tva->client_name = $user->name;
            $tva->client_address = $driver1 ? $driver1->address : '';
            $tva->company_name = $setting['company_name'];
            $tva->company_address = $setting['company_address'];
            $tva->designation = $vehicle_name . '-' . $vehicle_license_plate;
            $tva->quantity = (float)$totalDays;
            $tva->total_ht = number_format($totalHT, 2, '.', '');
            $tva->tva = number_format($tvaAmount, 2, '.', '');
            $tva->unit_price_ht = number_format($totalDays > 0 ? round($totalHT / $totalDays, 2) : 0, 2, '.', '');
            $tva->montant_ttc = number_format($numericAmount, 2, '.', '');
            $tva->ice_number = $setting['ice'] ?? null;
            $tva->rc_number = $setting['rc'] ?? null;
            $tva->nif_number = $setting['if'] ?? null;
            $tva->parent_id = parentId();
            $tva->booking_id = $booking->id;
            $tva->generated_date = now()->toDateString();
            $tva->total_amount = number_format($booking->amount, 2, '.', '');
            $tva->tva_amount = number_format($tvaAmount, 2, '.', '');
            $tva->payment_method = $request->payment_method;
            // DB column is required (no default); keep consistent with seeder usage.
            $tva->status = 1;
            $tva->save();



            Booking::statusChange($booking->id, $status);
            if ($request->ajax()) {
                return response()->json(['status' => 'success', 'message' => __('Booking payment successfully created.')]);
            }
            return redirect()->back()->with('success', __('Booking payment successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function paymentDestroy($booking_id, $id)
    {
        if (\Auth::user()->can('delete booking payment')) {
            $payment = BookingPayment::find($id);
            if ($payment) {
                // Delete linked TVA records created for this payment via idpaiment
                Tva::where('idpaiment', $payment->id)->delete();
                
                $payment->delete();
            }

            $bookinmg = Booking::find($booking_id);
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
        // Use the authenticated tenant/owner id so production loads correct data
        // You can re-enable the permission check if needed
        // if (\Auth::user()->can('manage planning')) {
        $parentId = parentId();
        $bookings = Booking::where('parent_id', $parentId)->get();
        $vehicles = Vehicle::where('parent_id', $parentId)->get();

        $vehicleData = [];
        foreach ($vehicles as $vehicle) {
            $vehicleArr = [
                'id' => (string)$vehicle->id, // Ensure string type
                'title' => $vehicle->name . ' - ' . $vehicle->license_plate,
            ];
            $vehicleData[] = $vehicleArr;
        }

        $bookingData = [];
        foreach ($bookings as $booking) {
            $driver = !empty($booking->drivers) ? $booking->drivers->name : '';
            $booked = [
                'id' => $booking->id,
                'resourceId' => (string)$booking->vehicle, // Ensure string type to match vehicle ID
                'title' => 'BOK-' . sprintf('%04d', $booking->booking_id) . ' - ' . $driver,
                'start' => $booking->start_date . 'T' . $booking->start_time,
                'end'   => $booking->end_date . 'T' . $booking->end_time,
                'url' => route('booking.show', Crypt::encrypt($booking->id)),
            ];
            $bookingData[] = $booked;
        }

        return view('booking.planning', compact('bookingData', 'vehicleData'));
        // } else {
        //     return redirect()->back()->with('error', __('Permission Denied.'));
        // }
    }
    public function testPlanning()
    {
        try {
            // Test planning method without forcing authentication
            // Accept ?parent=ID for quick debugging, otherwise use logged-in parent if available, else fallback to 2
            $parentId = request('parent');
            if (!$parentId) {
                $parentId = Auth::check() ? parentId() : 2;
            }
            $bookings = Booking::where('parent_id', $parentId)->get();
            $vehicles = Vehicle::where('parent_id', $parentId)->get();

            // Debug: Check what we got
            $debug = [
                'bookings_count' => $bookings->count(),
                'vehicles_count' => $vehicles->count(),
                'bookings_sample' => $bookings->take(2)->map(function ($b) {
                    return [
                        'id' => $b->id,
                        'booking_id' => $b->booking_id,
                        'vehicle' => $b->vehicle,
                        'start_date' => $b->start_date,
                        'end_date' => $b->end_date
                    ];
                }),
                'vehicles_sample' => $vehicles->take(2)->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'name' => $v->name,
                        'license_plate' => $v->license_plate
                    ];
                })
            ];

            return response()->json($debug);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
