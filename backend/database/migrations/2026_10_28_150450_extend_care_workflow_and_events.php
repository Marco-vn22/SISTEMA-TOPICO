<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->string('status', 20)->default('cerrada')->index();
            $table->string('care_type', 20)->default('urgencia');
            $table->text('no_medication_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('coordinator_reviewed_at')->nullable();
            $table->foreignId('coordinator_reviewed_by')->nullable()->constrained('users');
        });
        DB::table('encounters')->update(['closed_at' => DB::raw('created_at')]);
        DB::table('encounters')->where('outcome', 'derivada')->update(['care_type' => 'emergencia']);
        Schema::create('care_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('care_request_id')->constrained();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('user_role', 20);
            $table->string('action', 60);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('care_events');
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coordinator_reviewed_by');
            $table->dropColumn(['status', 'care_type', 'no_medication_reason', 'closed_at', 'coordinator_reviewed_at']);
        });
    }
};
