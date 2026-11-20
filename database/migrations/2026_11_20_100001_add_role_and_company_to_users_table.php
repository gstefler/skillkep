<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('learner')->index()->after('email');
            $table->foreignId('company_id')->nullable()->after('role')->constrained('companies')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
