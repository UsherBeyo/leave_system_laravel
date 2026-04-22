<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            try { DB::statement("ALTER TABLE users MODIFY role VARCHAR(50) NOT NULL DEFAULT 'employee'"); } catch (\Throwable $e) {}
        }
        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (!Schema::hasColumn('employees', 'middle_name')) $table->string('middle_name', 100)->nullable()->after('first_name');
                if (!Schema::hasColumn('employees', 'department_id')) $table->unsignedBigInteger('department_id')->nullable()->after('department');
                if (!Schema::hasColumn('employees', 'salary')) $table->decimal('salary', 12, 2)->nullable()->after('position');
            });
        }
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id(); $table->string('name')->unique(); $table->boolean('is_active')->default(true); $table->timestamps();
            });
        }
        if (!Schema::hasTable('department_head_assignments')) {
            Schema::create('department_head_assignments', function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('department_id'); $table->unsignedBigInteger('employee_id'); $table->boolean('is_active')->default(true); $table->timestamps(); $table->unique(['department_id','employee_id'], 'dept_head_unique_pair');
            });
        }
        if (!Schema::hasTable('system_signatories')) {
            Schema::create('system_signatories', function (Blueprint $table) {
                $table->id(); $table->string('key_name')->unique(); $table->string('name')->nullable(); $table->string('position')->nullable(); $table->timestamps();
            });
        }
        if (!Schema::hasTable('leave_request_forms')) {
            Schema::create('leave_request_forms', function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('leave_request_id')->unique(); $table->decimal('cert_vacation_total_earned',10,3)->nullable(); $table->decimal('cert_vacation_less_this_application',10,3)->nullable(); $table->decimal('cert_vacation_balance',10,3)->nullable(); $table->decimal('cert_sick_total_earned',10,3)->nullable(); $table->decimal('cert_sick_less_this_application',10,3)->nullable(); $table->decimal('cert_sick_balance',10,3)->nullable(); $table->decimal('approved_for_days_with_pay',10,3)->nullable(); $table->decimal('approved_for_days_without_pay',10,3)->nullable(); $table->string('personnel_signatory_name_a')->nullable(); $table->string('personnel_signatory_position_a')->nullable(); $table->string('personnel_signatory_name_c')->nullable(); $table->string('personnel_signatory_position_c')->nullable(); $table->timestamps();
            });
        }
        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('leave_requests', 'department_id')) $table->unsignedBigInteger('department_id')->nullable()->after('employee_id');
                if (!Schema::hasColumn('leave_requests', 'leave_subtype')) $table->string('leave_subtype', 100)->nullable()->after('leave_type_id');
                if (!Schema::hasColumn('leave_requests', 'details_json')) $table->longText('details_json')->nullable()->after('leave_subtype');
                if (!Schema::hasColumn('leave_requests', 'filing_date')) $table->date('filing_date')->nullable()->after('details_json');
                if (!Schema::hasColumn('leave_requests', 'workflow_status')) $table->string('workflow_status', 100)->nullable()->after('status');
                if (!Schema::hasColumn('leave_requests', 'department_head_user_id')) $table->unsignedBigInteger('department_head_user_id')->nullable()->after('workflow_status');
                if (!Schema::hasColumn('leave_requests', 'personnel_user_id')) $table->unsignedBigInteger('personnel_user_id')->nullable()->after('department_head_user_id');
                if (!Schema::hasColumn('leave_requests', 'department_head_approved_at')) $table->timestamp('department_head_approved_at')->nullable()->after('personnel_user_id');
                if (!Schema::hasColumn('leave_requests', 'personnel_checked_at')) $table->timestamp('personnel_checked_at')->nullable()->after('department_head_approved_at');
                if (!Schema::hasColumn('leave_requests', 'finalized_at')) $table->timestamp('finalized_at')->nullable()->after('personnel_checked_at');
                if (!Schema::hasColumn('leave_requests', 'department_head_comments')) $table->text('department_head_comments')->nullable()->after('manager_comments');
                if (!Schema::hasColumn('leave_requests', 'personnel_comments')) $table->text('personnel_comments')->nullable()->after('department_head_comments');
                if (!Schema::hasColumn('leave_requests', 'print_status')) $table->string('print_status', 50)->nullable()->after('personnel_comments');
                if (!Schema::hasColumn('leave_requests', 'commutation')) $table->string('commutation', 50)->nullable()->after('snapshot_force_balance');
                if (!Schema::hasColumn('leave_requests', 'supporting_documents_json')) $table->longText('supporting_documents_json')->nullable()->after('commutation');
                if (!Schema::hasColumn('leave_requests', 'medical_certificate_attached')) $table->boolean('medical_certificate_attached')->default(false)->after('supporting_documents_json');
                if (!Schema::hasColumn('leave_requests', 'affidavit_attached')) $table->boolean('affidavit_attached')->default(false)->after('medical_certificate_attached');
                if (!Schema::hasColumn('leave_requests', 'emergency_case')) $table->boolean('emergency_case')->default(false)->after('affidavit_attached');
            });
        }
        if (!Schema::hasTable('leave_attachments')) {
            Schema::create('leave_attachments', function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('leave_request_id'); $table->string('original_name'); $table->string('stored_name'); $table->string('file_path'); $table->string('mime_type', 150)->nullable(); $table->unsignedBigInteger('file_size')->default(0); $table->string('document_type', 80)->nullable(); $table->unsignedBigInteger('uploaded_by_user_id')->nullable(); $table->timestamp('created_at')->useCurrent();
            });
        }
        if (Schema::hasTable('budget_history') && !Schema::hasColumn('budget_history', 'trans_date')) {
            Schema::table('budget_history', function (Blueprint $table) { $table->date('trans_date')->nullable()->after('leave_id'); });
        }
    }
    public function down(): void {}
};
