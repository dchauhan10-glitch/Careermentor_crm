<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Core CRM tables: teams, sources, pick-lists, students, enquiries,
 * call entries (insert-only), history tables, imports, duplicates,
 * audit/export logs, settings and authorized devices (future use).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('lead_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
        });

        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            // website | physical_upload | manual | sheet_import | other
            $table->string('type', 30)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('website_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources');
            $table->string('name');
            $table->string('key_prefix', 12)->unique(); // shown to admin to identify a key
            $table->string('key_hash', 64);            // sha256 of the full key; key itself is never stored
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('per_minute_limit')->default(30);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lead_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_closed')->default(false); // Converted / Closed / Not Interested
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        foreach (['courses', 'exams', 'colleges', 'states'] as $list) {
            Schema::create($list, function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['state_id', 'name']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('sources');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->json('column_map')->nullable();
            // uploaded | mapped | processing | completed | failed
            $table->string('status', 20)->default('uploaded')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('merged_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('message');
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('student_code', 20)->nullable()->unique(); // e.g. CM-000123
            $table->string('name');
            $table->string('mobile', 15)->unique();       // standardized: 10 digits for India
            $table->string('parent_mobile', 15)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('city_text')->nullable();      // free text when not in the pick-list
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('college_id')->nullable()->constrained('colleges')->nullOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained('exams')->nullOnDelete();
            $table->unsignedInteger('exam_rank')->nullable();
            $table->unsignedSmallInteger('admission_year')->nullable()->index();
            $table->foreignId('first_source_id')->nullable()->constrained('sources')->nullOnDelete();
            $table->foreignId('lead_status_id')->constrained('lead_statuses');
            $table->string('priority', 10)->default('warm')->index(); // hot | warm | cold
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->dateTime('next_follow_up_at')->nullable()->index();
            $table->dateTime('last_contacted_at')->nullable();
            $table->unsignedSmallInteger('enquiry_count')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'assigned_user_id']);
            $table->index(['assigned_user_id', 'next_follow_up_at']);
            $table->index(['lead_status_id', 'priority']);
            $table->index('name');
            $table->index('updated_at');
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('source_id')->constrained('sources');
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->foreignId('website_connection_id')->nullable()->constrained('website_connections')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20)->index(); // website | import | manual
            $table->string('source_page')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('parent_mobile', 15)->nullable();
            $table->string('course_text')->nullable();
            $table->string('exam_text')->nullable();
            $table->string('college_text')->nullable();
            $table->string('city_state_text')->nullable();
            $table->unsignedInteger('exam_rank')->nullable();
            $table->unsignedSmallInteger('admission_year')->nullable();
            $table->boolean('consent_recorded')->default(true);
            $table->date('collected_on');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'created_at']);
            $table->index(['source_id', 'created_at']);
        });

        // Insert-only: no updated_at, no soft delete. Changes are blocked in the model
        // and (where the database allows) by triggers in a later migration.
        Schema::create('call_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('called_at');
            $table->foreignId('lead_status_id')->nullable()->constrained('lead_statuses');
            $table->string('outcome');      // student's response
            $table->text('advice')->nullable();   // suggestion or advice given
            $table->text('remarks')->nullable();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->foreignId('corrects_call_entry_id')->nullable()->constrained('call_entries');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['student_id', 'called_at']);
            $table->index(['user_id', 'called_at']);
        });

        Schema::create('status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_status_id')->nullable()->constrained('lead_statuses');
            $table->foreignId('to_status_id')->constrained('lead_statuses');
            $table->string('from_priority', 10)->nullable();
            $table->string('to_priority', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['student_id', 'created_at']);
        });

        Schema::create('assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['student_id', 'created_at']);
        });

        Schema::create('duplicate_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');          // existing record
            $table->foreignId('candidate_student_id')->nullable()->constrained('students'); // possible duplicate
            $table->foreignId('enquiry_id')->nullable()->constrained('enquiries');
            $table->string('reason');
            // open | merged | kept_separate
            $table->string('status', 20)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60)->index();
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('details')->nullable(); // never holds mobile numbers or emails
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('export_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20); // backup | restore_test
            $table->string('status', 20); // success | failed
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->text('message')->nullable();
            $table->timestamps();
        });

        // Prepared for the future offline desktop viewer; unused in Phase 2.
        Schema::create('authorized_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'authorized_devices', 'backup_runs', 'settings', 'export_logs', 'audit_logs',
            'duplicate_reviews', 'assignment_history', 'status_history', 'call_entries',
            'enquiries', 'students', 'import_errors', 'import_batches', 'cities',
            'states', 'colleges', 'exams', 'courses', 'lead_statuses',
            'website_connections', 'sources',
        ] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['team_id']));
        Schema::dropIfExists('teams');
    }
};
