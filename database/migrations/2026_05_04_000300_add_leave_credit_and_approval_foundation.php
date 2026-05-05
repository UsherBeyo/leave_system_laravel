<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'can_approve_leave_requests')) {
                $table->boolean('can_approve_leave_requests')->default(false);
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            if (!Schema::hasColumn('employees', 'wellness_balance')) {
                $table->decimal('wellness_balance', 10, 3)->default(5.000);
            }
            if (!Schema::hasColumn('employees', 'spl_balance')) {
                $table->decimal('spl_balance', 10, 3)->default(3.000);
            }
            if (!Schema::hasColumn('employees', 'cto_balance')) {
                $table->decimal('cto_balance', 10, 3)->default(0.000);
            }
            if (!Schema::hasColumn('employees', 'cto_first_earned_at')) {
                $table->date('cto_first_earned_at')->nullable();
            }
        });

        Schema::table('holidays', function (Blueprint $table) {
            if (!Schema::hasColumn('holidays', 'is_recurring')) {
                $table->boolean('is_recurring')->default(false);
            }
        });

        if (Schema::hasTable('leave_types')) {
            DB::table('leave_types')->updateOrInsert(
                ['name' => 'Wellness Leave'],
                ['deduct_balance' => 1, 'requires_approval' => 1, 'max_days_per_year' => 5, 'auto_approve' => 0]
            );

            DB::table('leave_types')->updateOrInsert(
                ['name' => 'Special Privilege Leave'],
                ['deduct_balance' => 1, 'requires_approval' => 1, 'max_days_per_year' => 3, 'auto_approve' => 0]
            );
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'can_approve_leave_requests')) {
                $table->dropColumn('can_approve_leave_requests');
            }
        });

        Schema::table('employees', function (Blueprint $table) {
            foreach (['wellness_balance', 'spl_balance', 'cto_balance', 'cto_first_earned_at'] as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('holidays', function (Blueprint $table) {
            if (Schema::hasColumn('holidays', 'is_recurring')) {
                $table->dropColumn('is_recurring');
            }
        });
    }
};
