<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Companies (shared catalog), job applications and everything that follows them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->string('siren', 9)->primary();
            $table->string('name');
            $table->string('naf_code', 6)->nullable()->index();
            $table->string('activity')->nullable();
            $table->string('headcount')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 5)->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 9, 6)->nullable();
            $table->decimal('longitude', 9, 6)->nullable();
            $table->string('website')->nullable();
            $table->string('website_proof')->nullable();
            $table->boolean('website_searched')->default(false);
            $table->string('email')->nullable();
            $table->string('email_note')->nullable();
            $table->boolean('email_searched')->default(false);
            $table->string('phone')->nullable();
            $table->boolean('blocked')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_siren', 9);
            $table->foreign('company_siren')->references('siren')->on('companies')->cascadeOnDelete();
            $table->string('status')->default('pending_review')->index();
            $table->char('priority', 1)->default('B');
            $table->decimal('distance_km', 6, 1)->nullable();
            $table->text('hook')->nullable();
            $table->text('proof_excerpt')->nullable();
            $table->string('proof_url')->nullable();
            $table->boolean('hook_generated')->default(false);
            $table->string('hook_error')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('followed_up_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'company_siren']);
        });

        Schema::create('sent_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('recipient');
            $table->string('message_id')->unique();
            $table->timestamp('sent_at');
            $table->timestamps();
        });

        Schema::create('replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('sender');
            $table->string('subject')->nullable();
            $table->string('message_id')->nullable()->unique();
            $table->timestamp('received_at');
            $table->boolean('draft_created')->default(false);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->text('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('replies');
        Schema::dropIfExists('sent_emails');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('companies');
    }
};
