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
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('estudiante')->index();
            $table->boolean('active')->default(true);
        });
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('document', 30)->unique();
            $table->date('birth_date')->nullable();
            $table->string('classroom', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->text('allergies')->nullable();
            $table->text('background')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('care_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('teacher_id')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->text('reason');
            $table->string('priority', 20)->default('normal')->index();
            $table->string('status', 30)->default('pendiente')->index();
            $table->boolean('emergency')->default(false);
            $table->text('authorization_note')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->unique()->constrained('care_requests');
            $table->foreignId('patient_id')->constrained();
            $table->foreignId('clinician_id')->constrained('users');
            $table->text('symptoms');
            $table->text('diagnosis');
            $table->text('indications');
            $table->string('outcome', 30);
            $table->string('referral_destination')->nullable();
            $table->text('referral_reason')->nullable();
            $table->timestamps();
        });
        Schema::create('access_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity', 100);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('access_tokens');
        Schema::dropIfExists('encounters');
        Schema::dropIfExists('care_requests');
        Schema::dropIfExists('patients');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['role', 'active']));
    }
};
