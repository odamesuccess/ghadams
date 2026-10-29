<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('maintenance_requests', 'invoice_pdf_path')) {
                $table->string('invoice_pdf_path')->nullable()->after('receipt_path');
            }

            if (! Schema::hasColumn('maintenance_requests', 'invoice_original_name')) {
                $table->string('invoice_original_name')->nullable()->after('invoice_pdf_path');
            }

            if (! Schema::hasColumn('maintenance_requests', 'invoice_uploaded_by')) {
                $table->unsignedBigInteger('invoice_uploaded_by')->nullable()->after('invoice_original_name');
            }

            if (! Schema::hasColumn('maintenance_requests', 'invoice_uploaded_at')) {
                $table->timestamp('invoice_uploaded_at')->nullable()->after('invoice_uploaded_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table): void {
            $columns = [];

            foreach ([
                'invoice_pdf_path',
                'invoice_original_name',
                'invoice_uploaded_by',
                'invoice_uploaded_at',
            ] as $column) {
                if (Schema::hasColumn('maintenance_requests', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
