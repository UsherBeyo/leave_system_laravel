<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leave_cancellations')) {
            Schema::create('leave_cancellations', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('leave_request_id');
                $table->integer('employee_id');
                $table->integer('requested_by_user_id')->nullable();
                $table->text('reason');
                $table->string('status', 30)->default('pending');
                $table->integer('reviewed_by_user_id')->nullable();
                $table->text('personnel_comments')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['leave_request_id', 'status'], 'leave_cancellations_leave_status_index');
                $table->index('employee_id', 'leave_cancellations_employee_id_index');
                $table->index('requested_by_user_id', 'leave_cancellations_requested_by_index');
                $table->index('reviewed_by_user_id', 'leave_cancellations_reviewed_by_index');

                $table->foreign('leave_request_id', 'leave_cancellations_leave_request_fk')
                    ->references('id')->on('leave_requests')->cascadeOnDelete();
                $table->foreign('employee_id', 'leave_cancellations_employee_fk')
                    ->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('requested_by_user_id', 'leave_cancellations_requested_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
                $table->foreign('reviewed_by_user_id', 'leave_cancellations_reviewed_by_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('leave_cancellation_attachments')) {
            Schema::create('leave_cancellation_attachments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('leave_cancellation_id');
                $table->string('original_name');
                $table->string('stored_name');
                $table->string('file_path');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedInteger('file_size')->default(0);
                $table->integer('uploaded_by_user_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index('leave_cancellation_id', 'lca_leave_cancellation_id_index');
                $table->index('uploaded_by_user_id', 'lca_uploaded_by_user_id_index');

                $table->foreign('leave_cancellation_id', 'lca_leave_cancellation_fk')
                    ->references('id')->on('leave_cancellations')->cascadeOnDelete();
                $table->foreign('uploaded_by_user_id', 'lca_uploaded_by_user_fk')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_cancellation_attachments');
        Schema::dropIfExists('leave_cancellations');
    }
};
