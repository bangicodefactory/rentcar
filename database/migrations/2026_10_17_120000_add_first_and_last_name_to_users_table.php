<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store a driver's first and last name separately.
 *
 * `users.name` has always been written as "first_name last_name" and split
 * back on a space for the driver edit/show forms, which guesses wrong for
 * multi-word names ("Mohamed Amine EL YOUSSFI"). DriverController@store and
 * @update now also persist the two parts; `name` is still written and remains
 * what every list, PDF and email displays.
 *
 * Both columns are nullable with no default: existing rows (and users created
 * by the booking import or the staff/profile forms) keep NULL and fall back to
 * the split in User::nameParts(). No backfill is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
