<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('first_name', 100)->nullable();
                $table->string('middle_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('department', 100)->nullable();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->unsignedBigInteger('manager_id')->nullable();
                $table->decimal('leave_balance', 6, 3)->default(20.000);
                $table->timestamp('created_at')->useCurrent();
                $table->decimal('annual_balance', 6, 3)->default(0.000);
                $table->decimal('sick_balance', 6, 3)->default(0.000);
                $table->decimal('force_balance', 6, 3)->default(0.000);
                $table->string('profile_pic')->nullable();
                $table->string('position', 128)->nullable();
                $table->string('status', 64)->nullable();
                $table->string('civil_status', 64)->nullable();
                $table->date('entrance_to_duty')->nullable();
                $table->string('unit', 128)->nullable();
                $table->string('gsis_policy_no', 128)->nullable();
                $table->string('national_reference_card_no', 128)->nullable();
                $table->decimal('salary', 12, 2)->nullable();
            });
        }

        if (!Schema::hasTable('leave_types')) {
            Schema::create('leave_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->boolean('deduct_balance')->default(true);
                $table->boolean('requires_approval')->default(true);
                $table->decimal('max_days_per_year', 6, 3)->nullable();
                $table->boolean('auto_approve')->default(false);
            });
        }

        if (!Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
                $table->string('leave_type', 100)->nullable();
                $table->foreignId('leave_type_id')->nullable()->constrained('leave_types')->nullOnDelete();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('total_days', 6, 3)->nullable();
                $table->text('reason')->nullable();
                $table->string('status', 20)->default('pending');
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->text('manager_comments')->nullable();
                $table->text('department_head_comments')->nullable();
                $table->text('personnel_comments')->nullable();
                $table->decimal('snapshot_annual_balance', 6, 3)->nullable();
                $table->decimal('snapshot_sick_balance', 6, 3)->nullable();
                $table->decimal('snapshot_force_balance', 6, 3)->nullable();
                $table->string('workflow_status', 50)->nullable();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->unsignedBigInteger('department_head_user_id')->nullable();
                $table->timestamp('department_head_approved_at')->nullable();
                $table->timestamp('personnel_checked_at')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->string('print_status', 50)->nullable();
            });
        }

        if (!Schema::hasTable('budget_history')) {
            Schema::create('budget_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('trans_date')->nullable();
                $table->string('leave_type', 50);
                $table->decimal('old_balance', 6, 3);
                $table->decimal('new_balance', 6, 3);
                $table->string('action', 50);
                $table->unsignedBigInteger('leave_request_id')->nullable();
                $table->text('notes')->nullable();
                $table->dateTime('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('department_head_assignments')) {
            Schema::create('department_head_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('leave_request_forms')) {
            Schema::create('leave_request_forms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('leave_request_id')->unique();
                $table->string('personnel_signatory_name_a')->nullable();
                $table->string('personnel_signatory_position_a')->nullable();
                $table->string('personnel_signatory_name_c')->nullable();
                $table->string('personnel_signatory_position_c')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('system_signatories')) {
            Schema::create('system_signatories', function (Blueprint $table) {
                $table->id();
                $table->string('signatory_key')->unique();
                $table->string('name')->nullable();
                $table->string('position')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('holidays')) {
            Schema::create('holidays', function (Blueprint $table) {
                $table->id();
                $table->date('holiday_date')->unique();
                $table->string('description')->nullable();
                $table->string('type', 50)->default('Other');
            });
        }

        if (!Schema::hasTable('leave_balance_logs')) {
            Schema::create('leave_balance_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->decimal('change_amount', 6, 3);
                $table->string('reason', 50);
                $table->unsignedBigInteger('leave_id')->nullable();
                $table->dateTime('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('accrual_history')) {
            Schema::create('accrual_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->decimal('amount', 6, 3);
                $table->date('date_accrued');
                $table->string('month_reference', 7);
                $table->dateTime('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('accruals')) {
            Schema::create('accruals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->decimal('amount', 6, 3);
                $table->dateTime('created_at')->useCurrent();
            });
        }

        if (Schema::hasTable('leave_types') && DB::table('leave_types')->count() === 0) {
            DB::table('leave_types')->insert([
                ['name' => 'Vacational', 'deduct_balance' => 1, 'requires_approval' => 1, 'max_days_per_year' => null, 'auto_approve' => 0],
                ['name' => 'Sick', 'deduct_balance' => 1, 'requires_approval' => 1, 'max_days_per_year' => null, 'auto_approve' => 0],
                ['name' => 'Emergency', 'deduct_balance' => 0, 'requires_approval' => 1, 'max_days_per_year' => null, 'auto_approve' => 1],
                ['name' => 'Special', 'deduct_balance' => 0, 'requires_approval' => 0, 'max_days_per_year' => null, 'auto_approve' => 1],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('accruals');
        Schema::dropIfExists('accrual_history');
        Schema::dropIfExists('leave_balance_logs');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('system_signatories');
        Schema::dropIfExists('leave_request_forms');
        Schema::dropIfExists('department_head_assignments');
        Schema::dropIfExists('budget_history');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('departments');
    }
};
