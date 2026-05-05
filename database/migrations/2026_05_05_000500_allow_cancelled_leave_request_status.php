<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_requests') && Schema::hasColumn('leave_requests', 'status')) {
            DB::statement("ALTER TABLE `leave_requests` MODIFY `status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_requests') && Schema::hasColumn('leave_requests', 'status')) {
            DB::table('leave_requests')
                ->where('status', 'cancelled')
                ->update(['status' => 'rejected']);

            DB::statement("ALTER TABLE `leave_requests` MODIFY `status` ENUM('pending','approved','rejected') DEFAULT 'pending'");
        }
    }
};
