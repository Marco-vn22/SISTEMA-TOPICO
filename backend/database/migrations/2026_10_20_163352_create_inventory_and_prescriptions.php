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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('kind', 20);
            $table->string('unit', 50);
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('batch', 80);
            $table->date('expires_at');
            $table->unsignedInteger('quantity')->default(0);
            $table->unique(['product_id', 'batch']);
            $table->timestamps();
        });
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('encounter_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->text('instructions');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('dispensed')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_lot_id')->constrained();
            $table->foreignId('prescription_id')->nullable()->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('kind', 20);
            $table->integer('quantity');
            $table->string('reason', 255);
            $table->timestamps();
        });
        Schema::table('encounters', function (Blueprint $table) {
            $table->string('followup_status', 30)->default('pendiente');
            $table->text('followup_notes')->nullable();
            $table->string('referral_contact')->nullable();
            $table->foreignId('followup_by')->nullable()->constrained('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('followup_by');
            $table->dropColumn(['followup_status', 'followup_notes', 'referral_contact']);
        });
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('products');
    }
};
