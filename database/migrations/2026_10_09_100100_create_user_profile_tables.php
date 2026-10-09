<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the student tells us in the onboarding questionnaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('gmail_app_password')->nullable()->after('password');
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained();
            $table->string('school');
            $table->date('internship_start');
            $table->date('internship_end');
            $table->string('address');
            $table->string('city')->nullable();
            $table->decimal('latitude', 9, 6);
            $table->decimal('longitude', 9, 6);
            $table->unsignedSmallInteger('radius_km')->default(35);
            $table->boolean('has_driving_licence')->default(false);
            $table->string('phone')->nullable();
            $table->string('github_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->string('cv_path')->nullable();
            $table->unsignedTinyInteger('daily_quota')->default(20);
            $table->string('validation_mode')->default('manual');
            $table->boolean('paused')->default(false);
            $table->timestamp('internship_found_at')->nullable();
            $table->timestamps();
        });

        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('organization')->nullable();
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->text('description');
            $table->string('role')->default('team');
            $table->boolean('in_production')->default(false);
            $table->string('technologies')->nullable();
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->default('language');
            $table->timestamps();
        });

        Schema::create('skill_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('level')->default('good');
            $table->primary(['user_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_user');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('experiences');
        Schema::dropIfExists('profiles');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('gmail_app_password'));
    }
};
