<?php

namespace App\LegacyImport\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class LegacyFixtureSchema
{
    public static function migrate(string $connection = 'legacy'): void
    {
        Schema::connection($connection)->dropAllTables();

        Schema::connection($connection)->create('users', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->timestamps();
            $table->string('timezone')->nullable();
            $table->string('role')->default('guest');
            $table->string('section')->default('adult');
            $table->string('phone')->nullable();
            $table->string('emergency_name')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->string('ical_token')->nullable();
            $table->string('accreditations')->default('');
        });

        Schema::connection($connection)->create('bookings', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->string('status')->default('tentative');
            $table->string('location');
            $table->string('group_name');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->string('activity')->default('Climbing');
            $table->string('lead_instructor_id', 26)->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->text('lead_instructor_notes')->nullable();
            $table->string('timezone')->nullable();
        });

        Schema::connection($connection)->create('booking_user', function (Blueprint $table): void {
            $table->string('booking_id', 26);
            $table->string('user_id', 26);
            $table->string('status')->default('needs-action');
            $table->timestamps();
            $table->text('comment')->nullable();
            $table->string('token', 64)->nullable();
            $table->unique(['booking_id', 'user_id']);
        });

        Schema::connection($connection)->create('bookables', function (Blueprint $table): void {
            $table->string('booking_id', 26);
            $table->string('bookable_type');
            $table->string('bookable_id', 26);
        });

        Schema::connection($connection)->create('scout_permits', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('activity');
            $table->string('category');
            $table->string('permit_type');
            $table->text('restrictions')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('mountain_training_qualifications', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('award');
            $table->timestamps();
        });

        Schema::connection($connection)->create('girlguiding_qualifications', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('scheme');
            $table->unsignedTinyInteger('level');
            $table->timestamps();
        });

        Schema::connection($connection)->create('qualifications', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('user_id', 26);
            $table->string('detail_type');
            $table->string('detail_id', 26);
            $table->date('expires_on')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('keys', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('name');
            $table->string('holder_id', 26)->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('news_posts', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('slug');
            $table->string('title');
            $table->string('author_id', 26);
            $table->text('body');
            $table->timestamps();
        });

        Schema::connection($connection)->create('documents', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('title');
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->integer('file_size');
            $table->string('file_type');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('notification_settings', function (Blueprint $table): void {
            $table->string('user_id', 26);
            $table->string('notifiable_type')->nullable();
            $table->string('notifiable_id', 26)->nullable();
            $table->string('comment_mail')->nullable();
            $table->boolean('invite_mail')->nullable();
            $table->boolean('change_mail')->nullable();
            $table->boolean('confirm_mail')->nullable();
            $table->boolean('cancel_mail')->nullable();
            $table->timestamps();
        });

        Schema::connection($connection)->create('changes', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->timestamps();
            $table->string('changeable_id', 26);
            $table->string('changeable_type');
            $table->string('author_id', 26);
        });

        Schema::connection($connection)->create('change_comments', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('change_id', 26);
            $table->text('body');
        });

        Schema::connection($connection)->create('change_fields', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('change_id', 26);
            $table->string('name');
            $table->text('value');
        });

        Schema::connection($connection)->create('change_attendees', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('change_id', 26);
            $table->string('attendee_id', 26);
            $table->string('attendee_status');
            $table->text('attendee_comment')->nullable();
        });

        Schema::connection($connection)->create('todos', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('summary');
            $table->timestamps();
        });

        Schema::connection($connection)->create('kit_checks', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->string('user_id', 26);
            $table->date('checked_on');
            $table->timestamps();
        });

        Schema::connection($connection)->create('mail_logs', function (Blueprint $table): void {
            $table->string('id', 26)->primary();
            $table->timestamps();
            $table->mediumText('body');
        });
    }
}
