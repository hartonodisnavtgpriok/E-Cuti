<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('position_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('active')->default(true);
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_system_admin')->default(false);
            $table->boolean('is_correspondence')->default(false);
        });

        Schema::create('approval_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_position_id')->unique()->constrained('positions')->restrictOnDelete();
            $table->foreignId('first_position_id')->constrained('positions')->restrictOnDelete();
            $table->foreignId('second_position_id')->nullable()->constrained('positions')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->date('date')->primary();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('leave_type');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('working_days');
            $table->text('reason');
            $table->text('address');
            $table->string('phone', 30)->nullable();
            $table->string('status', 30);
            $table->foreignId('first_position_id')->constrained('positions')->restrictOnDelete();
            $table->foreignId('second_position_id')->nullable()->constrained('positions')->restrictOnDelete();
            $table->string('letter_number')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('leave_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 30);
            $table->text('note')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_events');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('approval_routes');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
            $table->dropColumn(['active', 'is_admin', 'is_system_admin', 'is_correspondence']);
        });
        Schema::dropIfExists('positions');
    }
};
