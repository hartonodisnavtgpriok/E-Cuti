<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
        Schema::table('positions', function (Blueprint $table) {
            $table->boolean('is_group')->default(false);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('employee_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('created_count');
            $table->unsignedInteger('existing_count');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_imports');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });
        Schema::table('positions', function (Blueprint $table) {
            $table->dropColumn('is_group');
        });
        Schema::dropIfExists('units');
    }
};
