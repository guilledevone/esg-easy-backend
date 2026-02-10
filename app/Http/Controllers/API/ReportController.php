<?php
// app/Http/Controllers/API/ReportController.php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // GENERAR INFORME ESG VSME (30 CAMPOS)
    public function generate(Request $request)
    {
        $profile = $request->user();

        // LÍMITES POR PLAN
        $reportsThisMonth = Report::where('profile_id', $profile->id)
            ->whereMonth('created_at', now()->month)
            ->count();

        $limits = [
            'free' => 1,
            'starter' => 5,
            'pro' => 999
        ];
        
        $userPlan = $profile->plan ?? 'free';
        $limit = $limits[$userPlan] ?? 1;

        if ($reportsThisMonth >= $limit) {
            $nextPlan = $userPlan === 'free' ? 'Starter €29/mes' : 'Pro €59/mes';
            return response()->json([
                'error' => "Límite alcanzado ({$limit}/mes). Upgrade a {$nextPlan}",
                'current_plan' => $userPlan,
                'reports_used' => $reportsThisMonth,
                'limit' => $limit,
                'upgrade_url' => env('APP_URL') . '/pricing'
            ], 402);
        }

        // VALIDACIÓN 30 CAMPOS VSME
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            
            // ENVIRONMENTAL (13 campos)
            'energy_kwh' => 'required|numeric|min:0',
            'water_m3' => 'required|numeric|min:0',
            'water_source' => 'required|in:red,pozo,reciclada,mixta',
            'scope_1_co2' => 'required|numeric|min:0',
            'scope_2_co2' => 'required|numeric|min:0',
            'scope_3_co2' => 'required|numeric|min:0',
            'waste_kg' => 'required|numeric|min:0',
            'waste_hazardous_kg' => 'required|numeric|min:0',
            'waste_recycled_percent' => 'required|integer|min:0|max:100',
            'renewable_percent' => 'required|integer|min:0|max:100',
            'transport_km' => 'required|numeric|min:0',
            'fossil_fuels_liters' => 'required|numeric|min:0',
            
            // SOCIAL (12 campos)
            'employees_total' => 'required|integer|min:1',
            'employees_permanent' => 'required|integer|min:0',
            'employees_women' => 'required|integer|min:0',
            'training_hours' => 'required|numeric|min:0',
            'accidents' => 'required|integer|min:0',
            'discrimination_complaints' => 'required|integer|min:0',
            'local_suppliers_percent' => 'required|integer|min:0|max:100',
            'diversity_policy' => 'required|boolean',
            'salary_gap_percent' => 'required|numeric|min:0|max:100',
            'turnover_rate' => 'required|numeric|min:0|max:100',
            'supplier_audit_hr' => 'required|boolean',
            'child_labor_policy' => 'required|boolean',
            
            // GOVERNANCE (8 campos)
            'suppliers_total' => 'required|integer|min:0',
            'suppliers_audited' => 'required|integer|min:0',
            'anticorruption_policy' => 'required|boolean',
            'board_independent_percent' => 'required|integer|min:0|max:100',
            'board_diversity_age' => 'nullable|string|max:100',
            'ceo_median_pay_ratio' => 'nullable|numeric|min:0',
            'political_contributions' => 'required|numeric|min:0',
            'risks_identified' => 'required|integer|min:0',
        ]);

        // CALCULAR CO2 TOTAL (legacy compatibility)
        $co2_total = $validated['scope_1_co2'] + $validated['scope_2_co2'] + $validated['scope_3_co2'];

        // CREAR REPORT Y CALCULAR SCORES
        $report = Report::create([
            'profile_id' => $profile->id,
            'input_data' => $validated,
            // Guardar todos los 30 campos directamente
            'energy_kwh' => $validated['energy_kwh'],
            'water_m3' => $validated['water_m3'],
            'water_source' => $validated['water_source'],
            'scope_1_co2' => $validated['scope_1_co2'],
            'scope_2_co2' => $validated['scope_2_co2'],
            'scope_3_co2' => $validated['scope_3_co2'],
            'waste_kg' => $validated['waste_kg'],
            'waste_hazardous_kg' => $validated['waste_hazardous_kg'],
            'waste_recycled_percent' => $validated['waste_recycled_percent'],
            'renewable_percent' => $validated['renewable_percent'],
            'transport_km' => $validated['transport_km'],
            'fossil_fuels_liters' => $validated['fossil_fuels_liters'],
            'employees_total' => $validated['employees_total'],
            'employees_permanent' => $validated['employees_permanent'],
            'employees_women' => $validated['employees_women'],
            'training_hours' => $validated['training_hours'],
            'accidents' => $validated['accidents'],
            'discrimination_complaints' => $validated['discrimination_complaints'],
            'local_suppliers_percent' => $validated['local_suppliers_percent'],
            'diversity_policy' => $validated['diversity_policy'],
            'salary_gap_percent' => $validated['salary_gap_percent'],
            'turnover_rate' => $validated['turnover_rate'],
            'supplier_audit_hr' => $validated['supplier_audit_hr'],
            'child_labor_policy' => $validated['child_labor_policy'],
            'suppliers_total' => $validated['suppliers_total'],
            'suppliers_audited' => $validated['suppliers_audited'],
            'anticorruption_policy' => $validated['anticorruption_policy'],
            'board_independent_percent' => $validated['board_independent_percent'],
            'board_diversity_age' => $validated['board_diversity_age'] ?? null,
            'ceo_median_pay_ratio' => $validated['ceo_median_pay_ratio'] ?? null,
            'political_contributions' => $validated['political_contributions'],
            'risks_identified' => $validated['risks_identified'],
            'vsme_coverage_percent' => 21, // 30 de 140 campos
        ]);

        // CALCULAR SCORES (método del modelo)
        $report->calculateAllScores();

        // GENERAR REPORT CONTENT
        $report->report_content = $this->generateReportContent($report);
        $report->save();

        return response()->json([
            'message' => 'Informe VSME (21% cobertura) generado',
            'report' => $report,
            'score' => $report->esg_score,
            'breakdown' => [
                'environmental' => $report->score_environmental,
                'social' => $report->score_social,
                'governance' => $report->score_governance
            ],
            'recommendations' => $report->recommendations,
            'vsme_coverage' => $report->vsme_coverage_percent,
            'reports_remaining' => $limit - $reportsThisMonth - 1
        ], 201);
    }

    // GENERAR TEXTO INFORME MEJORADO
    private function generateReportContent(Report $report): string
    {
        $data = $report->input_data;
        $companyName = $data['company_name'] ?? 'Empresa';
        
        $content = "═════════════════════════════════════════════════\n";
        $content .= "   INFORME ESG VSME 2026 - {$companyName}\n";
        $content .= "═════════════════════════════════════════════════\n\n";
        
        $content .= "📊 SCORE ESG GLOBAL: {$report->esg_score}/10 ";
        $content .= $report->esg_score >= 7.5 ? "✅ Excelente\n" : ($report->esg_score >= 6 ? "⚠️ Bueno\n" : "🔴 Mejorable\n");
        $content .= "📈 Cobertura VSME: {$report->vsme_coverage_percent}% (30 de 140 campos)\n\n";

        // ENVIRONMENTAL
        $totalCO2 = $report->scope_1_co2 + $report->scope_2_co2 + $report->scope_3_co2;
        $content .= "🌍 ENVIRONMENTAL: {$report->score_environmental}/10\n";
        $content .= "   EMISIONES CO2:\n";
        $content .= "   • Scope 1 (directas): {$report->scope_1_co2}t/año\n";
        $content .= "   • Scope 2 (electricidad): {$report->scope_2_co2}t/año\n";
        $content .= "   • Scope 3 (cadena valor): {$report->scope_3_co2}t/año\n";
        $content .= "   • TOTAL: {$totalCO2}t/año\n\n";
        
        $content .= "   ENERGÍA & RECURSOS:\n";
        $content .= "   • Consumo energía: {$report->energy_kwh} kWh/mes\n";
        $content .= "   • Renovables: {$report->renewable_percent}%\n";
        $content .= "   • Combustibles fósiles: {$report->fossil_fuels_liters}L/año\n";
        $content .= "   • Agua ({$report->water_source}): {$report->water_m3} m³/mes\n\n";
        
        $content .= "   RESIDUOS:\n";
        $content .= "   • Totales: {$report->waste_kg} kg/mes\n";
        $content .= "   • Peligrosos: {$report->waste_hazardous_kg} kg/mes\n";
        $content .= "   • Reciclados: {$report->waste_recycled_percent}%\n\n";
        
        $content .= "   • Transporte: {$report->transport_km} km/año\n\n";

        // SOCIAL
        $womenPercent = round(($report->employees_women / $report->employees_total) * 100, 1);
        $permanentPercent = round(($report->employees_permanent / $report->employees_total) * 100, 1);
        $trainingPerEmp = round($report->training_hours / $report->employees_total, 1);
        
        $content .= "👥 SOCIAL: {$report->score_social}/10\n";
        $content .= "   PLANTILLA:\n";
        $content .= "   • Total empleados: {$report->employees_total}\n";
        $content .= "   • Indefinidos: {$report->employees_permanent} ({$permanentPercent}%)\n";
        $content .= "   • Mujeres: {$report->employees_women} ({$womenPercent}%)\n";
        $content .= "   • Brecha salarial género: {$report->salary_gap_percent}%\n";
        $content .= "   • Rotación anual: {$report->turnover_rate}%\n\n";
        
        $content .= "   CONDICIONES TRABAJO:\n";
        $content .= "   • Formación total: {$report->training_hours}h ({$trainingPerEmp}h/emp)\n";
        $content .= "   • Accidentes laborales: {$report->accidents}\n";
        $content .= "   • Quejas discriminación: {$report->discrimination_complaints}\n";
        $content .= "   • Política diversidad: " . ($report->diversity_policy ? 'Sí ✅' : 'No ❌') . "\n\n";
        
        $content .= "   CADENA VALOR:\n";
        $content .= "   • Proveedores locales: {$report->local_suppliers_percent}%\n";
        $content .= "   • Auditoría DDHH: " . ($report->supplier_audit_hr ? 'Sí ✅' : 'No ❌') . "\n";
        $content .= "   • Política trabajo infantil: " . ($report->child_labor_policy ? 'Sí ✅' : 'No ❌') . "\n\n";

        // GOVERNANCE
        $auditedPercent = $report->suppliers_total > 0 
            ? round(($report->suppliers_audited / $report->suppliers_total) * 100, 1) 
            : 0;
        
        $content .= "🏢 GOVERNANCE: {$report->score_governance}/10\n";
        $content .= "   PROVEEDORES:\n";
        $content .= "   • Total: {$report->suppliers_total}\n";
        $content .= "   • Auditados: {$report->suppliers_audited} ({$auditedPercent}%)\n\n";
        
        $content .= "   ÉTICA EMPRESARIAL:\n";
        $content .= "   • Política anticorrupción: " . ($report->anticorruption_policy ? 'Sí ✅' : 'No ❌ CRÍTICO CSRD') . "\n";
        $content .= "   • Contribuciones políticas: €{$report->political_contributions}\n\n";
        
        $content .= "   GOBIERNO CORPORATIVO:\n";
        $content .= "   • Consejo independiente: {$report->board_independent_percent}%\n";
        if ($report->board_diversity_age) {
            $content .= "   • Diversidad edad: {$report->board_diversity_age}\n";
        }
        if ($report->ceo_median_pay_ratio) {
            $content .= "   • Ratio CEO/empleado: {$report->ceo_median_pay_ratio}x\n";
        }
        $content .= "   • Riesgos identificados: {$report->risks_identified}\n\n";

        // RECOMENDACIONES
        $content .= "✅ TOP ACCIONES RECOMENDADAS (PRIORIDAD):\n";
        $content .= "─────────────────────────────────────────────────\n";
        foreach ($report->recommendations as $i => $rec) {
            $content .= sprintf(
                "%d. [%s] %s\n   📈 Impacto: %s\n   💰 Coste: %s\n\n",
                $i + 1,
                $rec['priority'],
                $rec['action'],
                $rec['impact'],
                $rec['cost']
            );
        }

        $content .= "═════════════════════════════════════════════════\n";
        $content .= "📋 INFORME VSME-READY (21% campos EFRAG)\n";
        $content .= "Generado: " . now()->format('d/m/Y H:i') . " con ESG Easy AI\n";
        $content .= "Válido para: bancos, inversores, licitaciones públicas\n";
        $content .= "⚠️  Para reporting CSRD oficial: completar 80 campos (Plan Pro)\n";
        $content .= "═════════════════════════════════════════════════\n";

        return $content;
    }

    // HISTORIAL INFORMES
    public function index(Request $request)
    {
        $reports = Report::where('profile_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'reports' => $reports,
            'total' => $reports->count()
        ]);
    }

    // VER INFORME ESPECÍFICO
    public function show(Request $request, $id)
    {
        $report = Report::where('profile_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['report' => $report]);
    }
}
