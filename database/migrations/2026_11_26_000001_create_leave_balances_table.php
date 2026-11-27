<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type');
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('initial_days')->nullable();
            $table->unsignedSmallInteger('used_days')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'leave_type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
    }
};
