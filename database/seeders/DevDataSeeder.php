<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Models\Credit;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Inspection;
use App\Models\InspectionType;
use App\Models\Option;
use App\Models\Place;
use App\Models\Reminder;
use App\Models\ReminderType;
use App\Models\RentalAgreement;
use App\Models\Tva;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds realistic test data for every business table, scoped to the owner.
 * Safe to re-run: guards with exists() checks on unique columns.
 *
 * Usage:
 *   php artisan db:seed --class=DevDataSeeder
 */
class DevDataSeeder extends Seeder
{
    private int $ownerId;

    public function run(): void
    {
        // Hard stop: this seeder attaches fake vehicles/bookings/payments to the
        // real owner account and generates TVA factures for every un-invoiced
        // payment — on a live DB that pollutes real invoice numbering (ran on
        // directonderweg prod 2026-07-06, 490 rows cleaned up by hand).
        if (app()->isProduction()) {
            throw new \RuntimeException('DevDataSeeder seeds fake business data and must never run in production.');
        }

        $owner = User::where('type', 'owner')->firstOrFail();
        $this->ownerId = $owner->id;

        $this->command->info("Seeding dev data for owner #{$this->ownerId} ({$owner->email})");

        // Clean up any stale test data seeded with parent_id=1
        $this->cleanStaleData();

        $vehicleTypeIds = $this->seedVehicleTypes();
        $placeIds       = $this->seedPlaces();
        $vehicleIds     = $this->seedVehicles($vehicleTypeIds);
        $driverUserIds  = $this->resolveDriverUserIds();
        $expenseTypeIds = $this->seedExpenseTypes();
        $inspTypeIds    = $this->seedInspectionTypes();
        $remTypeIds     = $this->seedReminderTypes();
        $addonIds       = $this->seedAddons();
                          $this->seedOptions();
        $bookingIds     = $this->seedBookings($vehicleIds, $driverUserIds, $placeIds, $addonIds);
                          $this->seedBookingPayments($bookingIds);
                          $this->seedExpenses($vehicleIds, $expenseTypeIds);
                          $this->seedInspections($vehicleIds, $inspTypeIds);
                          $this->seedReminders($vehicleIds, $remTypeIds);
                          $this->seedRentalAgreements($vehicleIds, $driverUserIds);
                          $this->seedCredits($driverUserIds);
                          $this->seedTva();

        $this->command->info('Dev data seeding complete.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cleanup
    // ─────────────────────────────────────────────────────────────────────────

    private function cleanStaleData(): void
    {
        // Only clean up stale test data in non-production environments and only
        // when the real owner isn't ID 1 (avoids wiping data on fresh installs
        // where the first owner is assigned ID 1).
        if (app()->isProduction()) {
            $this->command->warn('  cleanStaleData() skipped on production.');
            return;
        }

        if ($this->ownerId !== 1) {
            foreach (['vehicles', 'vehicle_types', 'places', 'options'] as $table) {
                DB::table($table)->where('parent_id', 1)->delete();
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Vehicle Types
    // ─────────────────────────────────────────────────────────────────────────

    private function seedVehicleTypes(): array
    {
        $types = ['SUV', 'Berline', 'Hatchback', 'Minivan', 'Cabriolet'];
        $ids = [];
        foreach ($types as $type) {
            $vt = VehicleType::firstOrCreate(
                ['type' => $type, 'parent_id' => $this->ownerId]
            );
            $ids[] = $vt->id;
        }
        $this->command->info('  Vehicle types: ' . count($ids));
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Places
    // ─────────────────────────────────────────────────────────────────────────

    private function seedPlaces(): array
    {
        $places = [
            ['name' => 'Casablanca Airport', 'city' => 'Casablanca', 'price' => 150],
            ['name' => 'Marrakech Centre',   'city' => 'Marrakech',  'price' => 0],
            ['name' => 'Rabat Gare',          'city' => 'Rabat',      'price' => 80],
            ['name' => 'Agadir Airport',      'city' => 'Agadir',     'price' => 200],
            ['name' => 'Fès Médina',          'city' => 'Fès',        'price' => 50],
        ];
        $ids = [];
        foreach ($places as $data) {
            $p = Place::firstOrCreate(
                ['name' => $data['name'], 'parent_id' => $this->ownerId],
                ['city' => $data['city'], 'price' => $data['price']]
            );
            $ids[] = $p->id;
        }
        $this->command->info('  Places: ' . count($ids));
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Vehicles
    // ─────────────────────────────────────────────────────────────────────────

    private function seedVehicles(array $vtIds): array
    {
        // gear/fuel use the keys from Vehicle::$gearbox and Vehicle::$fuelType
        // (automatic|manual, essence|diesel|petrol|hybrid|electric|gas) so the
        // show page and edit form resolve their labels correctly.
        $vehicles = [
            ['name' => 'Toyota RAV4',    'model' => '2023', 'type' => 0, 'engine' => 'Hybrid',  'plate' => 'A-1234-B', 'daily' => 350, 'seats' => 5, 'gear' => 'automatic', 'fuel' => 'hybrid', 'km' => 12000],
            ['name' => 'Dacia Duster',   'model' => '2022', 'type' => 0, 'engine' => '1.5 dCi', 'plate' => 'B-5678-C', 'daily' => 220, 'seats' => 5, 'gear' => 'manual',    'fuel' => 'diesel', 'km' => 45000],
            ['name' => 'Renault Clio',   'model' => '2023', 'type' => 2, 'engine' => '1.0 TCe', 'plate' => 'C-9012-D', 'daily' => 180, 'seats' => 5, 'gear' => 'manual',    'fuel' => 'petrol', 'km' => 8000],
            ['name' => 'Mercedes GLE',   'model' => '2024', 'type' => 0, 'engine' => '3.0 V6',  'plate' => 'D-3456-E', 'daily' => 700, 'seats' => 5, 'gear' => 'automatic', 'fuel' => 'diesel', 'km' => 5000],
            ['name' => 'Peugeot 208',    'model' => '2022', 'type' => 2, 'engine' => '1.2 PureTech', 'plate' => 'E-7890-F', 'daily' => 160, 'seats' => 5, 'gear' => 'manual',    'fuel' => 'petrol', 'km' => 30000],
            ['name' => 'Volkswagen T-Roc','model' => '2023', 'type' => 0, 'engine' => '1.5 TSI','plate' => 'F-2345-G', 'daily' => 380, 'seats' => 5, 'gear' => 'automatic', 'fuel' => 'petrol', 'km' => 18000],
            ['name' => 'Ford Transit',   'model' => '2021', 'type' => 3, 'engine' => '2.0 EcoBlue', 'plate' => 'G-6789-H', 'daily' => 450, 'seats' => 9, 'gear' => 'manual',    'fuel' => 'diesel', 'km' => 60000],
        ];

        $yearCol = 'year_of_ﬁrst_immatriculation'; // Unicode ligature — matches DB column exactly
        $nextVid = (Vehicle::where('parent_id', $this->ownerId)->max('vehicle_id') ?? 0) + 1;

        $ids = [];
        foreach ($vehicles as $i => $v) {
            $vtId = $vtIds[$v['type']] ?? $vtIds[0];
            $existing = Vehicle::where('license_plate', $v['plate'])
                ->where('parent_id', $this->ownerId)->first();
            if (!$existing) {
                $existing = Vehicle::create([
                    'vehicle_id'               => $nextVid++,
                    'type'                     => $vtId,
                    'name'                     => $v['name'],
                    'model'                    => $v['model'],
                    'engine_type'              => $v['engine'],
                    'license_plate'            => $v['plate'],
                    'daily_rate'               => $v['daily'],
                    'number_of_seats'          => $v['seats'],
                    'gearbox'                  => $v['gear'],
                    'fuel_type'                => $v['fuel'],
                    'kilometers'               => $v['km'],
                    $yearCol                   => $v['model'],
                    'registration_expiry_date' => now()->addYears(2)->toDateString(),
                    'parent_id'                => $this->ownerId,
                ]);
            }
            $ids[] = $existing->id;
        }
        $this->command->info('  Vehicles: ' . count($ids));
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Resolve existing driver user IDs
    // ─────────────────────────────────────────────────────────────────────────

    private function resolveDriverUserIds(): array
    {
        $ids = Driver::where('parent_id', $this->ownerId)
            ->pluck('user_id')
            ->toArray();

        if (empty($ids)) {
            $this->command->warn('  No drivers found for owner. Run DatabaseSeeder first.');
        } else {
            $this->command->info('  Drivers resolved: ' . count($ids));
        }
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Expense Types
    // ─────────────────────────────────────────────────────────────────────────

    private function seedExpenseTypes(): array
    {
        $types = ['Carburant', 'Entretien', 'Assurance', 'Réparation', 'Nettoyage', 'Péage'];
        $ids = [];
        foreach ($types as $t) {
            $et = ExpenseType::firstOrCreate(
                ['title' => $t, 'parent_id' => $this->ownerId]
            );
            $ids[] = $et->id;
        }
        $this->command->info('  Expense types: ' . count($ids));
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inspection Types
    // ─────────────────────────────────────────────────────────────────────────

    private function seedInspectionTypes(): array
    {
        $types = ['Contrôle technique', 'Révision générale', 'Contrôle freins', 'Vidange'];
        $ids = [];
        foreach ($types as $t) {
            $it = InspectionType::firstOrCreate(
                ['type' => $t, 'parent_id' => $this->ownerId]
            );
            $ids[] = $it->id;
        }
        $this->command->info('  Inspection types: ' . count($ids));
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reminder Types
    // ─────────────────────────────────────────────────────────────────────────

    private function seedReminderTypes(): array
    {
        $types = ['Renouvellement assurance', 'Vidange', 'Contrôle technique', 'Révision'];
        $ids = [];
        foreach ($types as $t) {
            $rt = ReminderType::firstOrCreate(
                ['type' => $t, 'parent_id' => $this->ownerId]
            );
            $ids[] = $rt->id;
        }
        $this->command->info('  Reminder types: ' . count($ids));
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Addons
    // ─────────────────────────────────────────────────────────────────────────

    private function seedAddons(): array
    {
        $addons = [
            ['name' => 'GPS Navigation',     'price' => 50,  'billing_type' => 'per_day'],
            ['name' => 'Siège bébé',         'price' => 30,  'billing_type' => 'per_day'],
            ['name' => 'Conducteur additionnel', 'price' => 100, 'billing_type' => 'fixed'],
            ['name' => 'Assurance Premium',  'price' => 80,  'billing_type' => 'per_day'],
            ['name' => 'Wi-Fi portable',     'price' => 40,  'billing_type' => 'per_day'],
        ];
        $ids = [];
        foreach ($addons as $data) {
            $addon = Addon::firstOrCreate(
                ['name' => $data['name'], 'parent_id' => $this->ownerId],
                ['price' => $data['price'], 'billing_type' => $data['billing_type']]
            );
            $ids[] = $addon->id;
        }
        $this->command->info('  Addons: ' . count($ids));
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Options
    // ─────────────────────────────────────────────────────────────────────────

    private function seedOptions(): void
    {
        $options = ['Climatisation', 'Bluetooth', 'Caméra de recul', 'Toit ouvrant', 'Apple CarPlay'];
        foreach ($options as $name) {
            Option::firstOrCreate(
                ['name' => $name, 'parent_id' => $this->ownerId]
            );
        }
        $this->command->info('  Options: ' . count($options));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bookings
    // ─────────────────────────────────────────────────────────────────────────

    private function seedBookings(array $vehicleIds, array $driverUserIds, array $placeIds, array $addonIds): array
    {
        if (empty($vehicleIds) || empty($driverUserIds) || empty($placeIds)) {
            $this->command->warn('  Skipping bookings — missing vehicles, drivers, or places.');
            return [];
        }

        // Status/payment values mirror Booking::$status and Booking::$paymentStatus
        // (the app's canonical vocabulary) so the index badges resolve to real labels.
        $statuses = ['yet_to_start', 'on_going', 'completed', 'cancelled'];
        $payStatuses = ['paye', 'impaye', 'partiellement_paye'];
        $payMethods  = ['cash', 'stripe', 'paypal'];

        $bookingsData = [
            ['start' => '-30 days', 'end' => '-25 days', 'status' => 'completed',   'pay' => 'paye',              'method' => 'cash',   'v' => 0, 'd' => 0, 'pu' => 0, 'do' => 1],
            ['start' => '-20 days', 'end' => '-15 days', 'status' => 'completed',   'pay' => 'paye',              'method' => 'stripe', 'v' => 1, 'd' => 1, 'pu' => 1, 'do' => 2],
            ['start' => '-10 days', 'end' => '-5 days',  'status' => 'completed',   'pay' => 'partiellement_paye','method' => 'cash',   'v' => 2, 'd' => 2, 'pu' => 0, 'do' => 0],
            ['start' => '-3 days',  'end' => '+2 days',  'status' => 'on_going',    'pay' => 'paye',              'method' => 'cash',   'v' => 3, 'd' => 0, 'pu' => 1, 'do' => 3],
            ['start' => '+5 days',  'end' => '+10 days', 'status' => 'yet_to_start','pay' => 'impaye',            'method' => 'cash',   'v' => 4, 'd' => 1, 'pu' => 2, 'do' => 4],
            ['start' => '+15 days', 'end' => '+20 days', 'status' => 'yet_to_start','pay' => 'impaye',            'method' => 'paypal', 'v' => 0, 'd' => 2, 'pu' => 3, 'do' => 1],
            ['start' => '-50 days', 'end' => '-45 days', 'status' => 'completed',   'pay' => 'paye',              'method' => 'stripe', 'v' => 5, 'd' => 0, 'pu' => 0, 'do' => 2],
            ['start' => '-60 days', 'end' => '-58 days', 'status' => 'cancelled',   'pay' => 'impaye',            'method' => 'cash',   'v' => 1, 'd' => 1, 'pu' => 2, 'do' => 3],
        ];

        $ids = [];
        $nextBookingId = (Booking::where('parent_id', $this->ownerId)->max('booking_id') ?? 0) + 1;

        foreach ($bookingsData as $data) {
            $vehicleId = $vehicleIds[$data['v'] % count($vehicleIds)];
            $driverUid = $driverUserIds[$data['d'] % count($driverUserIds)];
            $puId      = $placeIds[$data['pu'] % count($placeIds)];
            $doId      = $placeIds[$data['do'] % count($placeIds)];
            $vehicle   = Vehicle::find($vehicleId);
            $dailyRate = $vehicle ? $vehicle->daily_rate : 200;

            $start = Carbon::parse($data['start']);
            $end   = Carbon::parse($data['end']);
            $days  = max(1, $start->diffInDays($end));
            $amount = $dailyRate * $days;

            $addonStr = count($addonIds) >= 2
                ? implode(',', array_slice($addonIds, 0, 2))
                : ($addonIds[0] ?? null);

            // Idempotent: each seed row has a unique (vehicle, driver, status)
            // signature. Dates use relative offsets (now-anchored) so they can't
            // be part of a stable key — match on the signature instead so re-runs
            // don't duplicate bookings.
            $booking = Booking::where('parent_id', $this->ownerId)
                ->where('vehicle', $vehicleId)
                ->where('driver', $driverUid)
                ->where('status', $data['status'])
                ->first();

            if (!$booking) {
                $booking = Booking::create([
                    'booking_id'      => $nextBookingId++,
                    'vehicle'         => $vehicleId,
                    // Snapshot the vehicle as the app does on real bookings, so the
                    // index Vehicle column and vehicle search work on seeded data.
                    'vehicle_details' => $vehicle ? [
                        'id'            => $vehicle->id,
                        'name'          => $vehicle->name,
                        'license_plate' => $vehicle->license_plate,
                    ] : null,
                    'driver'          => $driverUid,
                    'start_date'      => $start->toDateString(),
                    'start_time'      => '09:00:00',
                    'end_date'        => $end->toDateString(),
                    'end_time'        => '18:00:00',
                    'pickup_address'  => $puId,
                    'drop_off_address'=> $doId,
                    'status'          => $data['status'],
                    'amount'          => $amount,
                    'payment_status'  => $data['pay'],
                    'payment_method'  => $data['method'],
                    'addon'           => $addonStr,
                    'daily_price_final'=> $dailyRate,
                    'parent_id'       => $this->ownerId,
                ]);
            }
            $ids[] = $booking->id;
        }
        $this->command->info('  Bookings: ' . count($ids));
        return $ids;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Booking Payments
    // ─────────────────────────────────────────────────────────────────────────

    private function seedBookingPayments(array $bookingIds): void
    {
        if (empty($bookingIds)) return;

        $methods = ['cash', 'stripe', 'paypal', 'bank_transfer'];

        // Payments spread across all 12 months of the current year, each tied to
        // a real booking (so the derived TVA invoices carry the booking's driver
        // and vehicle). Dates are explicit so the TVA report has month coverage.
        // [booking index, month, day, amount (TTC), method idx]
        $year = now()->year;
        $payments = [
            [0, 1, 12, 2100, 0], [1, 2, 8, 2496, 1], [2, 2, 22, 648, 2],
            [3, 3, 5, 3360, 0],  [4, 4, 15, 1152, 3], [0, 5, 3, 4200, 1],
            [5, 5, 27, 912, 0],  [1, 6, 11, 1320, 2], [6, 7, 9, 4320, 3],
            [2, 8, 1, 648, 0],   [3, 9, 19, 3360, 1], [4, 10, 6, 1152, 2],
            [0, 10, 24, 2100, 0],[5, 11, 14, 3192, 1],[6, 12, 2, 5400, 2],
            [3, 12, 20, 1320, 3],
        ];

        $count = 0;
        foreach ($payments as $p) {
            [$bi, $m, $d, $amount, $mi] = $p;
            $bookingId = $bookingIds[$bi % count($bookingIds)];
            $date = Carbon::create($year, $m, $d)->toDateString();

            BookingPayment::firstOrCreate(
                ['booking_id' => $bookingId, 'date' => $date, 'parent_id' => $this->ownerId],
                [
                    'amount'         => $amount,
                    'payment_method' => $methods[$mi],
                    'notes'          => 'Paiement enregistré lors de la prise en charge',
                ]
            );
            $count++;
        }
        $this->command->info('  Booking payments: ' . $count);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Expenses
    // ─────────────────────────────────────────────────────────────────────────

    private function seedExpenses(array $vehicleIds, array $typeIds): void
    {
        if (empty($vehicleIds) || empty($typeIds)) return;

        $expenses = [
            ['title' => 'Plein carburant — Toyota RAV4',  'amount' => 450,  'v' => 0, 't' => 0, 'offset' => '-5 days'],
            ['title' => 'Révision 60 000 km — Duster',    'amount' => 1200, 'v' => 1, 't' => 1, 'offset' => '-15 days'],
            ['title' => 'Renouvellement assurance flotte', 'amount' => 8500, 'v' => 2, 't' => 2, 'offset' => '-30 days'],
            ['title' => 'Changement pneus — Clio',         'amount' => 1800, 'v' => 2, 't' => 3, 'offset' => '-20 days'],
            ['title' => 'Nettoyage intérieur complet',     'amount' => 250,  'v' => 3, 't' => 4, 'offset' => '-2 days'],
            ['title' => 'Péage autoroute — Rabat-Tanger',  'amount' => 85,   'v' => 4, 't' => 5, 'offset' => '-7 days'],
            ['title' => 'Réparation pare-brise — GLE',     'amount' => 2400, 'v' => 3, 't' => 3, 'offset' => '-45 days'],
        ];

        foreach ($expenses as $data) {
            $vid = $vehicleIds[$data['v'] % count($vehicleIds)];
            $tid = $typeIds[$data['t'] % count($typeIds)];
            Expense::firstOrCreate(
                ['title' => $data['title'], 'parent_id' => $this->ownerId],
                [
                    'vehicle'    => $vid,
                    'type'       => $tid,
                    'date'       => Carbon::parse($data['offset'])->toDateString(),
                    'amount'     => $data['amount'],
                ]
            );
        }
        $this->command->info('  Expenses: ' . count($expenses));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Inspections
    // ─────────────────────────────────────────────────────────────────────────

    private function seedInspections(array $vehicleIds, array $typeIds): void
    {
        if (empty($vehicleIds) || empty($typeIds)) return;

        // Status/repair values mirror Inspection::$status and Inspection::$repairStatus
        // (the app's canonical vocabulary) so the index badges resolve to real labels.
        $inspections = [
            ['v' => 0, 't' => 0, 'offset' => '-60 days', 'status' => 'completed', 'repair' => 'completed',   'meter' => 10000, 'amount' => 350],
            ['v' => 1, 't' => 1, 'offset' => '-30 days', 'status' => 'completed', 'repair' => 'completed',   'meter' => 44000, 'amount' => 800],
            ['v' => 2, 't' => 2, 'offset' => '-15 days', 'status' => 'reject',    'repair' => 'in_progress', 'meter' => 7500,  'amount' => 1500],
            ['v' => 3, 't' => 3, 'offset' => '-7 days',  'status' => 'completed', 'repair' => 'completed',   'meter' => 4800,  'amount' => 600],
            ['v' => 4, 't' => 0, 'offset' => '-45 days', 'status' => 'completed', 'repair' => 'completed',   'meter' => 28000, 'amount' => 400],
        ];

        foreach ($inspections as $data) {
            $vid = $vehicleIds[$data['v'] % count($vehicleIds)];
            $idate = Carbon::parse($data['offset'])->toDateString();

            $exists = Inspection::where('vehicle', $vid)
                ->where('inspection_date', $idate)
                ->where('parent_id', $this->ownerId)
                ->exists();
            if ($exists) continue;

            Inspection::create([
                'vehicle'              => $vid,
                'inspector'            => 'Équipe technique',
                'inspection_date'      => $idate,
                'incoming_date'        => $idate,
                'meter_reading_incoming' => $data['meter'],
                'status'               => $data['status'],
                'repair_status'        => $data['repair'],
                'amount'               => $data['amount'],
                'notes'                => 'Inspection de routine — tout conforme.',
                'parent_id'            => $this->ownerId,
            ]);
        }
        $this->command->info('  Inspections: ' . count($inspections));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reminders
    // ─────────────────────────────────────────────────────────────────────────

    private function seedReminders(array $vehicleIds, array $remTypeIds): void
    {
        if (empty($vehicleIds) || empty($remTypeIds)) return;

        $reminders = [
            ['v' => 0, 't' => 0, 'offset' => '+30 days',  'status' => 'upcoming', 'name' => 'Renouvellement assurance RAV4'],
            ['v' => 1, 't' => 1, 'offset' => '+5 days',   'status' => 'urgent',   'name' => 'Vidange Duster'],
            ['v' => 2, 't' => 2, 'offset' => '-5 days',   'status' => 'overdue',  'name' => 'Contrôle technique Clio'],
            ['v' => 3, 't' => 3, 'offset' => '+60 days',  'status' => 'upcoming', 'name' => 'Révision GLE 10 000 km'],
            ['v' => 4, 't' => 0, 'offset' => '+90 days',  'status' => 'upcoming', 'name' => 'Renouvellement assurance Peugeot'],
            ['v' => 0, 't' => 1, 'offset' => '+10 days',  'status' => 'urgent',   'name' => 'Vidange RAV4'],
        ];

        foreach ($reminders as $data) {
            $vid = $vehicleIds[$data['v'] % count($vehicleIds)];
            $rtId = $remTypeIds[$data['t'] % count($remTypeIds)];
            Reminder::firstOrCreate(
                ['name' => $data['name'], 'parent_id' => $this->ownerId],
                [
                    'id_vehicle'      => $vid,
                    'reminder_type_id'=> $rtId,
                    'reminder_date'   => Carbon::parse($data['offset'])->toDateString(),
                    'status'          => $data['status'],
                    'note'            => 'Rappel automatique — à traiter avant l\'échéance.',
                ]
            );
        }
        $this->command->info('  Reminders: ' . count($reminders));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Rental Agreements
    // ─────────────────────────────────────────────────────────────────────────

    private function seedRentalAgreements(array $vehicleIds, array $driverUserIds): void
    {
        if (empty($vehicleIds) || empty($driverUserIds)) return;

        $agreements = [
            ['v' => 0, 'd' => 0, 'start' => '-30 days', 'end' => '-25 days', 'status' => 'completed'],
            ['v' => 1, 'd' => 1, 'start' => '-20 days', 'end' => '-15 days', 'status' => 'completed'],
            ['v' => 2, 'd' => 2, 'start' => '-3 days',  'end' => '+2 days',  'status' => 'active'],
            ['v' => 3, 'd' => 0, 'start' => '+5 days',  'end' => '+10 days', 'status' => 'pending'],
        ];

        $nextAgreementId = (RentalAgreement::where('parent_id', $this->ownerId)->max('agreement_id') ?? 0) + 1;

        foreach ($agreements as $data) {
            $vid  = $vehicleIds[$data['v'] % count($vehicleIds)];
            $duid = $driverUserIds[$data['d'] % count($driverUserIds)];
            $start = Carbon::parse($data['start']);
            $end   = Carbon::parse($data['end']);

            $exists = RentalAgreement::where('vehicle', $vid)
                ->where('rental_start_date', $start->toDateString())
                ->where('parent_id', $this->ownerId)
                ->exists();
            if ($exists) continue;

            RentalAgreement::create([
                'agreement_id'     => $nextAgreementId++,
                'date'             => $start->toDateString(),
                'rental_start_date'=> $start->toDateString(),
                'rental_end_date'  => $end->toDateString(),
                'rental_duration'  => $start->diffInDays($end),
                'vehicle'          => $vid,
                'driver'           => $duid,
                'status'           => $data['status'],
                'description'      => 'Contrat de location standard. Le véhicule est remis en bon état.',
                'terms_condition'  => "Le locataire s'engage à restituer le véhicule dans l'état où il l'a reçu.",
                'parent_id'        => $this->ownerId,
            ]);
        }
        $this->command->info('  Rental agreements: ' . count($agreements));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Credits
    // ─────────────────────────────────────────────────────────────────────────

    private function seedCredits(array $driverUserIds): void
    {
        if (empty($driverUserIds)) return;

        $credits = [
            ['d' => 0, 'amount' => 500,  'status' => Credit::STATUS_PAYE,     'offset' => '-10 days'],
            ['d' => 1, 'amount' => 1200, 'status' => Credit::STATUS_NON_PAYE, 'offset' => '-5 days'],
            ['d' => 2, 'amount' => 300,  'status' => Credit::STATUS_NON_PAYE, 'offset' => '-20 days'],
        ];

        foreach ($credits as $data) {
            $duid = $driverUserIds[$data['d'] % count($driverUserIds)];
            Credit::firstOrCreate(
                ['driver_id' => $duid, 'parent_id' => $this->ownerId],
                [
                    'amount'      => $data['amount'],
                    'status'      => $data['status'],
                    'credit_date' => Carbon::parse($data['offset'])->toDateString(),
                ]
            );
        }
        $this->command->info('  Credits: ' . count($credits));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TVA invoices (powers the TVA Report page)
    // ─────────────────────────────────────────────────────────────────────────

    private function seedTva(): void
    {
        // Mirror the real flow: TVA invoices are derived from booking payments
        // (see BookingController payment store / TvaController::generateMonthlyTva).
        // One invoice per payment, with the booking's driver + vehicle and the
        // payment amount as TTC (20% VAT back-calculated). Idempotent on idpaiment.
        $payments = BookingPayment::where('parent_id', $this->ownerId)
            ->orderBy('date')->get();

        if ($payments->isEmpty()) {
            $this->command->warn('  Skipping TVA — no booking payments to derive from.');
            return;
        }

        $setting = function_exists('settings') ? settings() : [];

        // Continue the global facture numbering like the controller does.
        $last = Tva::orderByDesc('id')->first();
        $counter = 0;
        if ($last && preg_match('/\d+$/', (string) $last->facture_number, $m)) {
            $counter = (int) $m[0];
        }

        $count = 0;
        foreach ($payments as $payment) {
            if (Tva::where('idpaiment', $payment->id)->exists()) {
                continue; // already generated for this payment
            }

            $booking = Booking::with('drivers')->find($payment->booking_id);
            if (!$booking) continue;

            $driverName    = $booking->drivers->name ?? 'N/A';
            $driverProfile = Driver::where('user_id', $booking->driver)->first();
            $driverAddress = $driverProfile->address ?? '';

            $vd = $booking->vehicle_details;
            if (is_string($vd)) {
                $decoded = json_decode($vd, true);
                $vd = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($vd)) $vd = [];
            $vehicleName  = $vd['name'] ?? '';
            $vehiclePlate = $vd['license_plate'] ?? '';

            $totalDays = max(1, Carbon::parse($booking->start_date)->diffInDays(Carbon::parse($booking->end_date)));

            $ttc = (float) $payment->amount;
            $ht  = round($ttc / 1.2, 2);
            $tvaAmount = round($ttc - $ht, 2);
            $unitHt = $totalDays > 0 ? round($ttc / $totalDays, 2) : $ttc;

            $counter++;
            $date = $payment->date ?? now()->toDateString();

            $tva = new Tva([
                'facture_number' => $counter,
                'facture_date'   => $date,
                'generated_date' => $date,
                'month'          => Carbon::parse($date)->month,
                'year'           => Carbon::parse($date)->year,
                'client_name'    => $driverName,
                'client_address' => $driverAddress,
                'company_name'   => $setting['company_name'] ?? 'Directonderweg',
                'company_address'=> $setting['company_address'] ?? '',
                'designation'    => trim($vehicleName . (($vehicleName && $vehiclePlate) ? ' - ' : '') . $vehiclePlate),
                'idpaiment'      => $payment->id,
                'booking_id'     => $booking->id,
                'quantity'       => $totalDays,
                'unit_price_ht'  => $unitHt,
                'total_ht'       => $ht,
                'tva'            => $tvaAmount,
                'tva_amount'     => $tvaAmount,
                'montant_ttc'    => $ttc,
                'total_amount'   => $ttc,
                'payment_method' => $payment->payment_method ?? 'cash',
                'status'         => 1,
            ]);
            $tva->parent_id = $this->ownerId; // not fillable — set explicitly
            $tva->save();
            $count++;
        }
        $this->command->info('  TVA invoices: ' . $count . ' new (from ' . $payments->count() . ' payments)');
    }
}
