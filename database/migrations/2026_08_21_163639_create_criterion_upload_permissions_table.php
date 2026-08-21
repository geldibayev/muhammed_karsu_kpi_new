<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNIQUE_INDEX = 'cup_user_criterion_active_unique';

    private const UNIQUE_COLUMNS = ['user_id', 'criterion_id', 'active_key'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('criterion_upload_permissions')) {
            Schema::whenTableDoesntHaveIndex(
                'criterion_upload_permissions',
                self::UNIQUE_COLUMNS,
                fn (Blueprint $table) => $table->unique(self::UNIQUE_COLUMNS, self::UNIQUE_INDEX),
                'unique',
            );

            return;
        }

        Schema::create('criterion_upload_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->boolean('active_key')->nullable()->default(true);
            $table->timestamp('used_at')->nullable();
            $table->foreignId('datum_id')->nullable()->constrained('data')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(self::UNIQUE_COLUMNS, self::UNIQUE_INDEX);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('criterion_upload_permissions');
    }
};
