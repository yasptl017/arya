<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('CalendarAppointments', function (Blueprint $table) {
            $table->boolean('NotAvailable')->default(false)->after('AllDay');
            $table->string('Color', 50)->nullable()->after('NotAvailable');
        });
    }

    public function down(): void
    {
        Schema::table('CalendarAppointments', function (Blueprint $table) {
            $table->dropColumn(['NotAvailable', 'Color']);
        });
    }
};
