<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'role')) $table->string('role', 32)->default('employee')->after('password');
                if (!Schema::hasColumn('users', 'is_active')) $table->boolean('is_active')->default(true)->after('role');
                if (!Schema::hasColumn('users', 'activation_token')) $table->string('activation_token')->nullable()->after('is_active');
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                foreach ([
                    'middle_name' => fn() => $table->string('middle_name', 100)->nullable()->after('first_name'),
                    'department_id' => fn() => $table->unsignedBigInteger('department_id')->nullable()->after('department'),
                    'annual_balance' => fn() => $table->decimal('annual_balance', 6, 3)->default(0.000),
                    'sick_balance' => fn() => $table->decimal('sick_balance', 6, 3)->default(0.000),
                    'force_balance' => fn() => $table->decimal('force_balance', 6, 3)->default(0.000),
                    'profile_pic' => fn() => $table->string('profile_pic')->nullable(),
                    'position' => fn() => $table->string('position', 128)->nullable(),
                    'status' => fn() => $table->string('status', 64)->nullable(),
                    'civil_status' => fn() => $table->string('civil_status', 64)->nullable(),
                    'entrance_to_duty' => fn() => $table->date('entrance_to_duty')->nullable(),
                    'unit' => fn() => $table->string('unit', 128)->nullable(),
                    'gsis_policy_no' => fn() => $table->string('gsis_policy_no', 128)->nullable(),
                    'national_reference_card_no' => fn() => $table->string('national_reference_card_no', 128)->nullable(),
                    'salary' => fn() => $table->decimal('salary', 12, 2)->nullable(),
                ] as $column => $callback) {
                    if (!Schema::hasColumn('employees', $column)) $callback();
                }
            });
        }

        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                foreach ([
                    'leave_type_id' => fn() => $table->unsignedBigInteger('leave_type_id')->nullable()->after('leave_type'),
                    'manager_comments' => fn() => $table->text('manager_comments')->nullable(),
                    'department_head_comments' => fn() => $table->text('department_head_comments')->nullable(),
                    'personnel_comments' => fn() => $table->text('personnel_comments')->nullable(),
                    'snapshot_annual_balance' => fn() => $table->decimal('snapshot_annual_balance', 6, 3)->nullable(),
                    'snapshot_sick_balance' => fn() => $table->decimal('snapshot_sick_balance', 6, 3)->nullable(),
                    'snapshot_force_balance' => fn() => $table->decimal('snapshot_force_balance', 6, 3)->nullable(),
                    'workflow_status' => fn() => $table->string('workflow_status', 50)->nullable(),
                    'department_id' => fn() => $table->unsignedBigInteger('department_id')->nullable(),
                    'department_head_user_id' => fn() => $table->unsignedBigInteger('department_head_user_id')->nullable(),
                    'department_head_approved_at' => fn() => $table->timestamp('department_head_approved_at')->nullable(),
                    'personnel_checked_at' => fn() => $table->timestamp('personnel_checked_at')->nullable(),
                    'finalized_at' => fn() => $table->timestamp('finalized_at')->nullable(),
                    'print_status' => fn() => $table->string('print_status', 50)->nullable(),
                ] as $column => $callback) {
                    if (!Schema::hasColumn('leave_requests', $column)) $callback();
                }
            });
        }
    }

    public function down(): void
    {
        // Keep compatibility columns.
    }
};
