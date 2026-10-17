<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * users.first_name / users.last_name: written only by DriverController, read
 * through User::nameParts(), cleared whenever `name` changes without them.
 */
class UserNamePartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_renaming_through_name_alone_clears_the_stored_parts(): void
    {
        $user = User::factory()->create([
            'name'       => 'Mohamed Amine EL YOUSSFI',
            'first_name' => 'Mohamed Amine',
            'last_name'  => 'EL YOUSSFI',
        ]);

        // What the staff/profile form, the import and the super-admin fill do.
        $user->name = 'Carla Rossi';
        $user->save();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Carla Rossi', 'first_name' => null, 'last_name' => null]);
        $this->assertSame(['Carla', 'Rossi'], $user->fresh()->nameParts());
    }

    public function test_writing_name_together_with_the_parts_keeps_them(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $user->first_name = 'Mohamed Amine';
        $user->last_name = 'EL YOUSSFI';
        $user->name = 'Mohamed Amine EL YOUSSFI';
        $user->save();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'first_name' => 'Mohamed Amine', 'last_name' => 'EL YOUSSFI']);
        $this->assertSame(['Mohamed Amine', 'EL YOUSSFI'], $user->fresh()->nameParts());
    }

    public function test_saving_without_touching_name_leaves_the_parts_alone(): void
    {
        $user = User::factory()->create([
            'name'       => 'Mohamed Amine EL YOUSSFI',
            'first_name' => 'Mohamed Amine',
            'last_name'  => 'EL YOUSSFI',
        ]);

        $user->phone_number = '0600000000';
        $user->save();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'first_name' => 'Mohamed Amine', 'last_name' => 'EL YOUSSFI']);
    }

    public function test_parts_are_not_mass_assignable(): void
    {
        // UserController's super-admin branch does fill($request->all()).
        $user = (new User())->fill(['name' => 'Carla Rossi', 'first_name' => 'X', 'last_name' => 'Y']);

        $this->assertSame('Carla Rossi', $user->name);
        $this->assertNull($user->first_name);
        $this->assertNull($user->last_name);
    }

    public function test_name_parts_handles_unicode_whitespace_and_empty_names(): void
    {
        $this->assertSame(['SALMA', 'EL YOUSSFI'], (new User(['name' => "\u{00A0}SALMA\u{00A0}EL YOUSSFI\u{00A0}"]))->nameParts());
        $this->assertSame(['SALMA', 'EL YOUSSFI'], (new User(['name' => "  SALMA \t EL YOUSSFI  "]))->nameParts());
        $this->assertSame(['SALMA', null], (new User(['name' => 'SALMA']))->nameParts());
        $this->assertSame([null, null], (new User(['name' => '   ']))->nameParts());
        $this->assertSame([null, null], (new User(['name' => null]))->nameParts());
    }

    public function test_name_parts_degrades_to_a_byte_split_on_malformed_utf8(): void
    {
        // preg_* return false/null on invalid UTF-8; the old explode() never failed.
        $this->assertSame(["Salma\xFF", 'EL YOUSSFI'], (new User(['name' => "Salma\xFF EL YOUSSFI"]))->nameParts());
    }
}
