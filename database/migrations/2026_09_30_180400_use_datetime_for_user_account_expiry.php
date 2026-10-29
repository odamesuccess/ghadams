<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('account_expires_at')->nullable()->change();
        });
    }
    public function down(): void
    {
        // Keep datetime: converting back to timestamp may lose post-2038 dates.
    }
};
