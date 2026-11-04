<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('care_requests', function (Blueprint $table) {
            $table->string('laboratory', 150)->nullable();
            $table->string('schedule_reference', 100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('care_requests', fn (Blueprint $table) => $table->dropColumn(['laboratory', 'schedule_reference']));
    }
};
