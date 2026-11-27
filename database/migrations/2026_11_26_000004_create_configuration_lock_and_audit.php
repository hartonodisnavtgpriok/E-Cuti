<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_configuration_lock', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
        });
        DB::table('workflow_configuration_lock')->insert(['id' => 1]);

        Schema::create('administration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administration_events');
        Schema::dropIfExists('workflow_configuration_lock');
    }
};
