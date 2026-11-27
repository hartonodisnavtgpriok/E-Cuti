<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annual_balance_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('n2');
            $table->unsignedTinyInteger('n1');
            $table->unsignedTinyInteger('n');
            $table->timestamps();
        });

        Schema::create('annual_balance_baseline_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamps();
        });

        Schema::create('annual_leave_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('origin_year');
            $table->unsignedSmallInteger('days');
            $table->unique(['leave_request_id', 'origin_year']);
        });

        Schema::create('leave_documents', function (Blueprint $table) {
            $table->foreignId('leave_request_id')->primary()->constrained()->restrictOnDelete();
            $table->longText('pdf_base64');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_documents');
        Schema::dropIfExists('annual_leave_allocations');
        Schema::dropIfExists('annual_balance_baseline_events');
        Schema::dropIfExists('annual_balance_baselines');
    }
};
