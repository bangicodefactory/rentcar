<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\WithClient;
use Tests\TestCase;

class BookingExcelTest extends TestCase
{
    use RefreshDatabase;
    use WithClient;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asClient('directonderweg');

        Permission::firstOrCreate(['name' => 'create booking', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'delete booking', 'guard_name' => 'web']);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->owner = User::factory()->create(['type' => 'owner', 'parent_id' => 0]);
        $this->owner->givePermissionTo(['create booking', 'delete booking']);

        // downloadTemplate writes a temp file here; create the directory if absent.
        $storageApp = storage_path('app');
        if (!is_dir($storageApp)) {
            mkdir($storageApp, 0755, true);
        }
    }

    // ── BookingController::downloadTemplate ───────────────────────────────────

    public function test_download_template_requires_auth(): void
    {
        $this->get(route('booking.template'))->assertRedirect(route('login'));
    }

    public function test_download_template_returns_xlsx_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->owner)->get(route('booking.template'));

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type')
        );
    }

    // ── BookingController::importExcel ────────────────────────────────────────

    public function test_import_requires_auth(): void
    {
        $this->post(route('booking.import'))->assertRedirect(route('login'));
    }

    public function test_import_denied_without_create_booking_permission(): void
    {
        $noPerms = User::factory()->create(['type' => 'employee', 'parent_id' => $this->owner->id]);

        $this->actingAs($noPerms)
            ->post(route('booking.import'), ['file' => $this->makeXlsx()])
            ->assertSessionHas('error');
    }

    public function test_import_flashes_error_when_no_file_provided(): void
    {
        $this->actingAs($this->owner)
            ->post(route('booking.import'), [])
            ->assertSessionHasErrors('file');
    }

    public function test_import_flashes_error_for_empty_spreadsheet(): void
    {
        $file = $this->makeXlsx([]);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_import_creates_bookings_from_valid_xlsx(): void
    {
        Vehicle::factory()->create([
            'parent_id'     => $this->owner->id,
            'license_plate' => 'AB-9999-CD',
        ]);

        $rows = [
            ['HASSAN SALEM', '01/06/2026', '09:00', 'CLIO V', 'AB-9999-CD', '03/06/2026', '11:00', 2, 300, 'Carte'],
        ];
        $file = $this->makeXlsx($rows);

        $before = Booking::where('parent_id', $this->owner->id)->count();

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect(route('booking.index'))
            ->assertSessionHas('success');

        $after = Booking::where('parent_id', $this->owner->id)->count();
        $this->assertGreaterThan($before, $after);
    }

    public function test_import_skips_rows_with_invalid_dates_but_still_succeeds(): void
    {
        $rows = [
            ['INVALID ROW', 'not-a-date', '09:00', 'CLIO V', 'ZZ-0000-ZZ', 'also-bad', '11:00', 2, 300, 'Carte'],
        ];
        $file = $this->makeXlsx($rows);

        $this->actingAs($this->owner)
            ->post(route('booking.import'), ['file' => $file])
            ->assertRedirect(route('booking.index'));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeXlsx(?array $dataRows = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $headers = [
            'NOM & PRENOM', 'DATE DEBUT (JJ/MM/AAAA)', 'HEURE DEBUT (HH:MM)',
            'LA MARQUE', 'IMMATRICULATION', 'DATE RETOUR (JJ/MM/AAAA)',
            'HEURE RETOUR (HH:MM)', 'PERIODE', 'PRIX', 'METHOD',
        ];
        $sheet->fromArray([$headers], null, 'A1');

        if ($dataRows !== null) {
            foreach ($dataRows as $i => $row) {
                $sheet->fromArray([$row], null, 'A' . ($i + 2));
            }
        }

        $tempPath = sys_get_temp_dir() . '/booking_' . uniqid() . '.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        return new UploadedFile(
            $tempPath,
            'bookings.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
