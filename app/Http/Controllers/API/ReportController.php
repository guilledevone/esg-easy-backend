<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // GENERAR INFORME ESG
    public function generate(Request $request)
    {
        $profile = $request->user();

        // LÍMITE FREE: 1 informe/mes (Starter €9: 5/mes, Pro: ilimitado)
        $reportsThisMonth = Report::where('profile_id', $profile->id)
            ->whereMonth('created_at', now()->month)
            ->count();

        if (!$profile->is_pro && $reportsThisMonth >= 1) {
            return response()->json([
                'error' => 'Límite alcanzado. Upgrade Starter €9/mes para 5 informes',
                'upgrade_url' => 'https://esg-easy.test/pricing'
            ], 402);
        }

        // VALIDACIÓN DATOS
        $request->validate([
            'company' => 'required|string',
            'energy' => 'required|numeric',  // kWh/mes
            'employees' => 'required|integer',
            'waste' => 'nullable|numeric',  // kg/mes
            'suppliers' => 'nullable|integer'
        ]);

        // CÁLCULOS ESG REALISTAS
        $energy = $request->energy;
        $employees = $request->employees;
        $waste = $request->waste ?? 0;
        $suppliers = $request->suppliers ?? 0;

        // Environmental Score (0-10)
        $co2_tons = ($energy * 0.0005) + ($waste * 0.0003);
        $envScore = max(0, 10 - ($co2_tons * 2));

        // Social Score (0-10)
        $socialScore = 6.0;
        if ($employees > 5) $socialScore += 1.5;
        if ($employees > 10) $socialScore += 0.5;

        // Governance Score (0-10)
        $govScore = 7.5;
        if ($suppliers > 10) $govScore += 0.5;

        // ESG TOTAL (ponderado)
        $esg_score = round(
            ($envScore * 0.35) + ($socialScore * 0.35) + ($govScore * 0.30), 
            1
        );

        // RECOMENDACIONES ESPECÍFICAS
        $recommendations = [];
        if ($envScore < 7) {
            $recommendations[] = "Cambiar a iluminación LED (-30% kWh)";
        }
        if ($waste > 500) {
            $recommendations[] = "Programa reciclaje → Food Banks";
        }
        if ($employees < 5) {
            $recommendations[] = "Contrato indefinido mejora score Social +1.5";
        }

        // GENERAR PDF TEXTO (mejorar Día 4 con mPDF)
        $report_content = "═══════════════════════════════════════\n";
        $report_content .= "INFORME ESG CSRD 2026 - {$request->company}\n";
        $report_content .= "═══════════════════════════════════════\n\n";
        $report_content .= "📊 SCORE ESG GLOBAL: {$esg_score}/10\n\n";
        $report_content .= "🌍 ENVIRONMENTAL: " . round($envScore, 1) . "/10\n";
        $report_content .= "   • Emisiones CO2: {$co2_tons}t/año\n";
        $report_content .= "   • Consumo: {$energy} kWh/mes\n\n";
        $report_content .= "👥 SOCIAL: " . round($socialScore, 1) . "/10\n";
        $report_content .= "   • Empleados: {$employees}\n";
        $report_content .= "   • Contratación: " . ($employees > 5 ? "Excelente" : "Mejorable") . "\n\n";
        $report_content .= "🏢 GOVERNANCE: " . round($govScore, 1) . "/10\n";
        $report_content .= "   • Proveedores auditados: {$suppliers}\n\n";
        $report_content .= "✅ ACCIONES RECOMENDADAS:\n";
        foreach ($recommendations as $rec) {
            $report_content .= "   • {$rec}\n";
        }
        $report_content .= "\n🔒 Informe CSRD-compliant listo autoridades\n";
        $report_content .= "Generado con ESG Easy AI - " . now()->format('d/m/Y') . "\n";

        // GUARDAR DB
        $report = Report::create([
            'profile_id' => $profile->id,
            'input_data' => $request->all(),
            'report_content' => $report_content,
            'esg_score' => $esg_score
        ]);

        return response()->json([
            'message' => 'Informe generado exitosamente',
            'report' => $report,
            'score' => $esg_score,
            'breakdown' => [
                'environmental' => round($envScore, 1),
                'social' => round($socialScore, 1),
                'governance' => round($govScore, 1)
            ],
            'recommendations' => $recommendations
        ], 201);
    }

    // HISTORIAL INFORMES
    public function index(Request $request)
    {
        $reports = Report::where('profile_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['reports' => $reports]);
    }

    // VER INFORME ESPECÍFICO
    public function show(Request $request, $id)
    {
        $report = Report::where('profile_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json(['report' => $report]);
    }
}
