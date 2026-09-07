<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record the pickup / return location charge on the invoice itself.
 *
 * The invoice is a single-line document today: the PDF loops over an items
 * collection that is built from this row's own designation / quantity /
 * montant_ttc, so a location charge folded into the total has nowhere to
 * appear. These two columns let the charge be shown on its own line.
 *
 * Both are nullable with no default, so every existing invoice keeps
 * exactly the behaviour it has now: a null amount means no location line
 * and the rental line carries the full montant_ttc. No backfill is needed
 * for production to stay correct.
 *
 * The amount is stored TTC because that is what the invoice's line column
 * shows, and it is a snapshot: a later change to the place's price must not
 * rewrite an invoice that has already been issued.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tvas', function (Blueprint $table) {
            $table->string('place_label')->nullable()->after('designation');
            $table->decimal('place_amount_ttc', 10, 2)->nullable()->after('place_label');
        });
    }

    public function down(): void
    {
        Schema::table('tvas', function (Blueprint $table) {
            $table->dropColumn(['place_label', 'place_amount_ttc']);
        });
    }
};
