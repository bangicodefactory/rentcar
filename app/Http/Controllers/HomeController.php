<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Contact;
use App\Models\Custom;
use App\Models\Expense;
use App\Models\Fuel;
use App\Models\NoticeBoard;
use App\Models\Service;
use App\Models\Support;
use App\Models\User;
use App\Models\Place;
use App\Models\Reminder;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function index()
    {
        if (\Auth::check()) {
            if (\Auth::user()->type == 'super admin') {
                $result['totalOrganization'] = User::where('type', 'owner')->count();

                $result['organizationByMonth'] = $this->organizationByMonth();

                return Inertia::render('Dashboard', [
                    'stats' => [
                        'totalOrganization' => $result['totalOrganization'],
                    ],
                    'organizationByMonth' => $result['organizationByMonth'],
                ]);
            } else {
                $result['totalUser'] = User::where('parent_id', parentId())->count();
                $result['totalDriver'] = User::where('type', 'driver')->where('parent_id', parentId())->count();
                $result['totalBooking'] = Booking::where('parent_id', parentId())->count();
                $result['totalIncome'] = Booking::where('parent_id', parentId())->sum('amount');
                $totalExpense = Expense::where('parent_id', parentId())->sum('amount');
                $result['totalExpense'] = $totalExpense;
                $result['incomeExpenseByMonth'] = $this->incomeExpenseByMonth();
                $result['settings'] = settings();

                $extras = $this->ownerDashboardExtras();

                // Upcoming reminders are surfaced through immediateActions /
                // fleetAvailability now (see ownerDashboardExtras), so the old
                // standalone `reminders` prop is no longer sent.
                return Inertia::render('Dashboard', [
                    'stats' => [
                        'totalUser'    => $result['totalUser'],
                        'totalDriver'  => $result['totalDriver'],
                        'totalBooking' => $result['totalBooking'],
                        'totalIncome'  => $result['totalIncome'],
                        'totalExpense' => $result['totalExpense'],
                    ],
                    'incomeExpenseByMonth' => $result['incomeExpenseByMonth'],
                    'operational'          => $extras['operational'],
                    'immediateActions'     => $extras['immediateActions'],
                    'fleetAvailability'    => $extras['fleetAvailability'],
                ]);
            }
        } else {
            // App not yet installed → hand off to the rachidlaasri installer.
            // Was `header('location:install'); die;` — a hard die() that tore
            // down the PHP process before Laravel could emit a response (and, in
            // tests under coverage, killed the run before the coverage report was
            // written — see #145). A framework redirect to the same target
            // (/install) is behaviourally identical without killing the process.
            if (!file_exists(setup())) {
                return redirect('install');
            }
            // The app is internal-only: an anonymous visitor at / goes to login.
            return redirect()->route('login');
        }
    }

    /**
     * Operational dashboard data for the owner view (Stitch-aligned).
     * Every figure is derived from existing data — bookings, reminders,
     * vehicles and booking payments. No new schema, no invented features.
     */
    private function ownerDashboardExtras(): array
    {
        $parentId = parentId();
        $today     = Carbon::today();
        $closed    = ['cancelled', 'completed']; // not "out" / not pending return

        // Row-level lists expose individual bookings/reminders, so they honour
        // the same permissions as their modules. Aggregate counts stay visible
        // (consistent with the existing totalBooking/totalIncome cards).
        $user        = \Auth::user();
        $canBooking  = $user->can('manage booking');
        $canReminder = $user->can('manage reminder');

        // ── Operational metric cards ──────────────────────────────────────────
        $carsOut = Booking::where('parent_id', $parentId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->whereNotIn('status', $closed)
            ->count();

        $totalVehicles = Vehicle::where('parent_id', $parentId)->count();

        $returnsDueToday = Booking::where('parent_id', $parentId)
            ->whereDate('end_date', $today)
            ->whereNotIn('status', $closed)
            ->count();

        $overdueBookings = Booking::where('parent_id', $parentId)
            ->whereDate('end_date', '<', $today)
            ->whereNotIn('status', $closed)
            ->orderBy('end_date')
            ->get();

        $maintenanceDue = Reminder::where('parent_id', $parentId)
            ->whereIn('status', ['upcoming', 'urgent', 'overdue'])
            ->count();

        $revenueToday = (float) BookingPayment::where('parent_id', $parentId)
            ->whereDate('date', $today)->sum('amount');
        $revenueMonth = (float) BookingPayment::where('parent_id', $parentId)
            ->whereYear('date', $today->year)->whereMonth('date', $today->month)
            ->sum('amount');

        // ── Lookups shared by the actions + fleet widgets ─────────────────────
        $vehiclesById = Vehicle::where('parent_id', $parentId)->get()->keyBy('id');
        $driverNames  = User::where('parent_id', $parentId)->where('type', 'driver')->pluck('name', 'id');

        // ── Immediate actions: overdue returns + urgent/overdue reminders ─────
        $actions = [];
        if ($canBooking) {
            foreach ($overdueBookings->take(5) as $b) {
                $vehicle = $vehiclesById->get($b->vehicle);
                $actions[] = [
                    'type'     => 'return',
                    'title'    => $vehicle?->name ?? ('#' . $b->booking_id),
                    'subtitle' => $driverNames[$b->driver] ?? null,
                    'status'   => 'overdue',
                    'href'     => route('booking.show', $b->id),
                ];
            }
        }
        if ($canReminder) {
            $urgentReminders = Reminder::with('vehicles')
                ->where('parent_id', $parentId)
                ->whereIn('status', ['urgent', 'overdue'])
                ->orderBy('reminder_date')
                ->take(5)
                ->get();
            foreach ($urgentReminders as $r) {
                $actions[] = [
                    'type'     => 'maintenance',
                    'title'    => $r->vehicles?->name ?? $r->name,
                    'subtitle' => $r->note,
                    'status'   => $r->status,
                    'href'     => route('reminder.index'),
                ];
            }
        }
        $actions = array_slice($actions, 0, 6);

        // ── Fleet availability: bookings over the next 7 days ─────────────────
        $rangeStart = $today->copy();
        $rangeEnd   = $today->copy()->addDays(6);

        $days = [];
        for ($d = $rangeStart->copy(); $d->lte($rangeEnd); $d->addDay()) {
            $days[] = $d->toDateString();
        }

        // The timeline is a booking view, so it is gated behind 'manage booking'.
        $fleetVehicles = [];
        if ($canBooking) {
            $bookingsInRange = Booking::where('parent_id', $parentId)
                ->where('status', '!=', 'cancelled')
                ->whereDate('start_date', '<=', $rangeEnd)
                ->whereDate('end_date', '>=', $rangeStart)
                ->get();

            $fleetVehicles = $vehiclesById->take(8)->map(function ($v) use ($bookingsInRange, $driverNames) {
                $bookings = $bookingsInRange->where('vehicle', $v->id)->map(fn ($b) => [
                    'booking_id' => $b->booking_id,
                    'start'      => optional($b->start_date)->toDateString(),
                    'end'        => optional($b->end_date)->toDateString(),
                    'status'     => $b->status,
                    'driver'     => $driverNames[$b->driver] ?? null,
                ])->values()->all();

                return [
                    'id'            => $v->id,
                    'name'          => $v->name,
                    'license_plate' => $v->license_plate,
                    'bookings'      => $bookings,
                ];
            })->values()->all();
        }

        return [
            'operational' => [
                'carsOut'         => $carsOut,
                'totalVehicles'   => $totalVehicles,
                'returnsDueToday' => $returnsDueToday,
                'overdue'         => $overdueBookings->count(),
                'maintenanceDue'  => $maintenanceDue,
                'revenueToday'    => $revenueToday,
                'revenueMonth'    => $revenueMonth,
            ],
            'immediateActions'  => $actions,
            'fleetAvailability' => [
                'days'     => $days,
                'vehicles' => $fleetVehicles,
                // full count so the widget can show "Showing N of M" (the list
                // above is capped at 8 vehicles).
                'total'    => $canBooking ? $vehiclesById->count() : 0,
            ],
        ];
    }

    public function organizationByMonth(): array
    {
        $year = (int) date('Y');

        $counts = DB::table('users')
            ->selectRaw('MONTH(created_at) as mo, COUNT(*) as cnt')
            ->where('type', 'owner')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('cnt', 'mo');

        $organization = ['label' => [], 'data' => []];
        for ($m = 1; $m <= 12; $m++) {
            $organization['label'][] = date('M-Y', mktime(0, 0, 0, $m, 1, $year));
            $organization['data'][]  = (int) ($counts[$m] ?? 0);
        }

        return $organization;
    }

    public function incomeExpenseByMonth(): array
    {
        $year = (int) date('Y');
        $pid  = parentId();

        $income = DB::table('bookings')
            ->selectRaw('MONTH(start_date) as mo, SUM(amount) as total')
            ->where('parent_id', $pid)
            ->whereYear('start_date', $year)
            ->groupByRaw('MONTH(start_date)')
            ->pluck('total', 'mo');

        $expense = DB::table('expenses')
            ->selectRaw('MONTH(date) as mo, SUM(amount) as total')
            ->where('parent_id', $pid)
            ->whereYear('date', $year)
            ->groupByRaw('MONTH(date)')
            ->pluck('total', 'mo');

        $payment = ['label' => [], 'income' => [], 'expense' => []];
        for ($m = 1; $m <= 12; $m++) {
            $payment['label'][]   = date('M-Y', mktime(0, 0, 0, $m, 1, $year));
            $payment['income'][]  = (float) ($income[$m] ?? 0);
            $payment['expense'][] = (float) ($expense[$m] ?? 0);
        }

        return $payment;
    }

    public function landing()
    {
        return Inertia::render('Public/Landing', $this->landingProps());
    }

    private function landingProps(): array
    {
        $s = settings();

        $heroImages = [];
        foreach (['image_home_1', 'image_home_2'] as $key) {
            $path = 'upload/home/' . ($s[$key] ?? '');
            $heroImages[] = Storage::exists($path) ? Storage::url($path) : null;
        }

        return [
            'vehicles'     => Vehicle::select('id', 'name', 'model', 'daily_rate', 'number_of_seats', 'gearbox', 'fuel_type', 'picture')->get(),
            'vehicleTypes' => VehicleType::select('id', 'type')->get(),
            'places'       => Place::select('id', 'name')->get(),
            'heroImages'   => $heroImages,
        ];
    }
}
