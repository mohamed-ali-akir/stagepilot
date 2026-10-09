<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reference data: study programs, NAF activity codes, and which codes each program targets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('naf_codes', function (Blueprint $table) {
            $table->string('code', 6)->primary();
            $table->string('label');
            $table->timestamps();
        });

        Schema::create('naf_code_program', function (Blueprint $table) {
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('naf_code', 6);
            $table->foreign('naf_code')->references('code')->on('naf_codes')->cascadeOnDelete();
            $table->char('priority', 1)->default('B');
            $table->primary(['program_id', 'naf_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('naf_code_program');
        Schema::dropIfExists('naf_codes');
        Schema::dropIfExists('programs');
    }
};
