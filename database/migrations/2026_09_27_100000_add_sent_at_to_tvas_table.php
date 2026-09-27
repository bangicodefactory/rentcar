<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mark an invoice as "sent" — handed to a client — so its number is locked.
 *
 * A sent invoice cannot be edited, deleted (directly or through its booking or
 * payment), re-issued by the monthly rebuild or moved by the renumber tool.
 *
 * Both columns are nullable with no default: every existing invoice starts
 * unsent and keeps exactly today's behaviour. Marking historical invoices as
 * sent is a separate, explicit data step, not part of this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tvas', function (Blueprint $table) {
            $table->timestamp('sent_at')->nullable()->after('status');
            $table->unsignedBigInteger('sent_by')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('tvas', function (Blueprint $table) {
            $table->dropColumn(['sent_at', 'sent_by']);
        });
    }
};
