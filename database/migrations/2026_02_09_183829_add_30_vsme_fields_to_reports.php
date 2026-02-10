<?php
// database/migrations/XXXX_XX_XX_XXXXXX_add_30_vsme_fields_to_reports.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // ENVIRONMENTAL (13 campos)
            $table->decimal('energy_kwh', 10, 2)->nullable();
            $table->decimal('water_m3', 10, 2)->nullable();
            $table->enum('water_source', ['red', 'pozo', 'reciclada', 'mixta'])->nullable();
            $table->decimal('scope_1_co2', 10, 2)->nullable();
            $table->decimal('scope_2_co2', 10, 2)->nullable();
            $table->decimal('scope_3_co2', 10, 2)->nullable();
            $table->decimal('waste_kg', 10, 2)->nullable();
            $table->decimal('waste_hazardous_kg', 10, 2)->nullable();
            $table->integer('waste_recycled_percent')->nullable();
            $table->integer('renewable_percent')->nullable();
            $table->decimal('transport_km', 10, 2)->nullable();
            $table->decimal('fossil_fuels_liters', 10, 2)->nullable();
            
            // SOCIAL (12 campos)
            $table->integer('employees_total')->nullable();
            $table->integer('employees_permanent')->nullable();
            $table->integer('employees_women')->nullable();
            $table->decimal('training_hours', 10, 2)->nullable();
            $table->integer('accidents')->default(0);
            $table->integer('discrimination_complaints')->default(0);
            $table->integer('local_suppliers_percent')->nullable();
            $table->boolean('diversity_policy')->default(false);
            $table->decimal('salary_gap_percent', 5, 2)->nullable();
            $table->decimal('turnover_rate', 5, 2)->nullable();
            $table->boolean('supplier_audit_hr')->default(false);
            $table->boolean('child_labor_policy')->default(false);
            
            // GOVERNANCE (8 campos)
            $table->integer('suppliers_total')->nullable();
            $table->integer('suppliers_audited')->nullable();
            $table->boolean('anticorruption_policy')->default(false);
            $table->integer('board_independent_percent')->nullable();
            $table->string('board_diversity_age')->nullable();
            $table->decimal('ceo_median_pay_ratio', 10, 2)->nullable();
            $table->decimal('political_contributions', 10, 2)->default(0);
            $table->integer('risks_identified')->default(0);
            
            // METADATA
            $table->integer('vsme_coverage_percent')->default(21);
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn([
                'energy_kwh', 'water_m3', 'water_source',
                'scope_1_co2', 'scope_2_co2', 'scope_3_co2',
                'waste_kg', 'waste_hazardous_kg', 'waste_recycled_percent',
                'renewable_percent', 'transport_km', 'fossil_fuels_liters',
                'employees_total', 'employees_permanent', 'employees_women',
                'training_hours', 'accidents', 'discrimination_complaints',
                'local_suppliers_percent', 'diversity_policy',
                'salary_gap_percent', 'turnover_rate',
                'supplier_audit_hr', 'child_labor_policy',
                'suppliers_total', 'suppliers_audited', 'anticorruption_policy',
                'board_independent_percent', 'board_diversity_age',
                'ceo_median_pay_ratio', 'political_contributions', 'risks_identified',
                'vsme_coverage_percent'
            ]);
        });
    }
};
