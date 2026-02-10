<?php
// app/Models/Report.php - ACTUALIZADO

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'profile_id', 'input_data', 'report_content', 'esg_score',
        'score_environmental', 'score_social', 'score_governance',
        'recommendations', 'vsme_coverage_percent',
        // 30 campos VSME
        'energy_kwh', 'water_m3', 'water_source', 'co2_scope_1_3',
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
        'ceo_median_pay_ratio', 'political_contributions', 'risks_identified'
    ];

    protected $casts = [
        'diversity_policy' => 'boolean',
        'anticorruption_policy' => 'boolean',
        'supplier_audit_hr' => 'boolean',
        'child_labor_policy' => 'boolean',
        'recommendations' => 'array',
        'input_data' => 'array',
        'created_at' => 'datetime',
    ];

    // SCORING MEJORADO CON 30 CAMPOS
    
    public function calculateEnvironmental(array $data): float
    {
        $score = 10.0;
        
        // Scope 1+2+3 desglosado (más preciso)
        $totalCO2 = ($data['scope_1_co2'] ?? 0) + 
                    ($data['scope_2_co2'] ?? 0) + 
                    ($data['scope_3_co2'] ?? 0);
        
        $co2PerEmp = $data['employees_total'] > 0 ? $totalCO2 / $data['employees_total'] : $totalCO2;
        
        if ($co2PerEmp > 3) $score -= 3.0;
        elseif ($co2PerEmp > 1.5) $score -= 1.5;
        elseif ($co2PerEmp > 0.8) $score -= 0.5;
        
        // Penaliza combustibles fósiles
        if (($data['fossil_fuels_liters'] ?? 0) > 500) $score -= 1.5;
        
        // Energía renovable (más peso)
        $renewableBonus = ($data['renewable_percent'] ?? 0) / 15; // +0.66 por cada 10%
        $score += $renewableBonus;
        
        // Residuos peligrosos (crítico)
        $wasteHazardous = $data['waste_hazardous_kg'] ?? 0;
        if ($wasteHazardous > 100) $score -= 2.0;
        elseif ($wasteHazardous > 50) $score -= 1.0;
        
        // Reciclaje (bonus)
        $recycledPercent = $data['waste_recycled_percent'] ?? 0;
        if ($recycledPercent >= 50) $score += 1.5;
        elseif ($recycledPercent >= 30) $score += 0.75;
        
        // Fuente agua (bonus sostenible)
        if (($data['water_source'] ?? '') === 'reciclada') $score += 0.5;
        
        return max(0, min(10, round($score, 1)));
    }

    public function calculateSocial(array $data): float
    {
        $score = 5.0;
        
        // Estabilidad laboral
        $permanentRatio = $data['employees_total'] > 0 
            ? ($data['employees_permanent'] / $data['employees_total']) * 100 
            : 0;
        if ($permanentRatio >= 90) $score += 2.5;
        elseif ($permanentRatio >= 80) $score += 2.0;
        elseif ($permanentRatio >= 60) $score += 1.0;
        else $score -= 0.5;
        
        // Brecha salarial género (CRÍTICO)
        $salaryGap = $data['salary_gap_percent'] ?? 0;
        if ($salaryGap <= 5) $score += 2.0; // Excelente
        elseif ($salaryGap <= 10) $score += 1.0; // Bueno
        elseif ($salaryGap > 20) $score -= 2.0; // Malo
        
        // Rotación empleados
        $turnover = $data['turnover_rate'] ?? 0;
        if ($turnover <= 10) $score += 1.0;
        elseif ($turnover > 30) $score -= 1.5;
        
        // Discriminación (crítico)
        $complaints = $data['discrimination_complaints'] ?? 0;
        if ($complaints > 0) $score -= ($complaints * 1.5); // -1.5 por queja
        
        // Diversidad género
        $womenRatio = $data['employees_total'] > 0 
            ? ($data['employees_women'] / $data['employees_total']) * 100 
            : 0;
        if ($womenRatio >= 40 && $womenRatio <= 60) $score += 1.5;
        elseif ($womenRatio >= 30 || $womenRatio >= 70) $score += 0.5;
        
        // Formación
        $trainingPerEmp = $data['employees_total'] > 0 
            ? $data['training_hours'] / $data['employees_total'] 
            : 0;
        if ($trainingPerEmp >= 30) $score += 1.5;
        elseif ($trainingPerEmp >= 20) $score += 1.0;
        
        // Accidentes
        if ($data['accidents'] == 0) $score += 1.0;
        elseif ($data['accidents'] > 2) $score -= 2.0;
        
        // Auditoría DDHH proveedores
        if ($data['supplier_audit_hr']) $score += 1.0;
        
        // Política trabajo infantil
        if ($data['child_labor_policy']) $score += 0.5;
        
        // Política diversidad
        if ($data['diversity_policy']) $score += 0.5;
        
        return max(0, min(10, round($score, 1)));
    }

    public function calculateGovernance(array $data): float
    {
        $score = 6.0;
        
        // Auditoría proveedores
        $auditedRatio = $data['suppliers_total'] > 0 
            ? ($data['suppliers_audited'] / $data['suppliers_total']) * 100 
            : 0;
        if ($auditedRatio >= 50) $score += 2.0;
        elseif ($auditedRatio >= 30) $score += 1.5;
        elseif ($auditedRatio >= 10) $score += 0.5;
        else $score -= 1.0;
        
        // Política anticorrupción (OBLIGATORIO CSRD)
        if ($data['anticorruption_policy']) $score += 1.5;
        else $score -= 2.0; // Penalización severa
        
        // Diversidad consejo (edad)
        if (!empty($data['board_diversity_age'])) {
            $score += 0.5;
        }
        
        // Ratio CEO/empleado (equidad)
        $ceoRatio = $data['ceo_median_pay_ratio'] ?? 0;
        if ($ceoRatio > 0 && $ceoRatio <= 20) $score += 1.5; // Excelente equidad
        elseif ($ceoRatio <= 50) $score += 0.5;
        elseif ($ceoRatio > 100) $score -= 1.0; // Brecha excesiva
        
        // Contribuciones políticas (transparencia)
        $political = $data['political_contributions'] ?? 0;
        if ($political == 0) $score += 0.5; // Neutral positivo
        elseif ($political > 10000) $score -= 1.0; // Influencia excesiva
        
        // Independencia consejo
        if ($data['board_independent_percent'] >= 30) $score += 1.0;
        
        // Gestión riesgos
        if ($data['risks_identified'] >= 5) $score += 1.0;
        elseif ($data['risks_identified'] >= 3) $score += 0.5;
        
        return max(0, min(10, round($score, 1)));
    }

    // RECOMENDACIONES MEJORADAS (30 campos)
    public function generateRecommendations(array $data, float $envScore, float $socialScore, float $govScore): array
    {
        $recommendations = [];
        
        // ENVIRONMENTAL
        $totalCO2 = ($data['scope_1_co2'] ?? 0) + ($data['scope_2_co2'] ?? 0) + ($data['scope_3_co2'] ?? 0);
        
        if ($totalCO2 > 2) {
            $recommendations[] = [
                'category' => 'Environmental',
                'priority' => 'Alta',
                'action' => 'Plan reducción CO2: Objetivo -30% en 3 años',
                'impact' => '+2.0 puntos E + acceso financiación verde',
                'cost' => '€1,000-3,000 consultoría'
            ];
        }
        
        if (($data['scope_3_co2'] ?? 0) > ($totalCO2 * 0.7)) {
            $recommendations[] = [
                'category' => 'Environmental',
                'priority' => 'Alta',
                'action' => 'Auditar proveedores alto impacto Scope 3',
                'impact' => 'Identificar 20-40% reducción cadena valor',
                'cost' => '€500-1,500'
            ];
        }
        
        if (($data['renewable_percent'] ?? 0) < 50) {
            $recommendations[] = [
                'category' => 'Environmental',
                'priority' => 'Media',
                'action' => 'Contratar tarifa 100% renovable',
                'impact' => '+1.5 puntos E + 0 emisiones Scope 2',
                'cost' => 'Sin sobrecoste'
            ];
        }
        
        if (($data['waste_recycled_percent'] ?? 0) < 40) {
            $recommendations[] = [
                'category' => 'Environmental',
                'priority' => 'Media',
                'action' => 'Programa reciclaje: Objetivo 50% residuos',
                'impact' => '+1.0 puntos E + ahorro €50-200/mes',
                'cost' => '€200-500 setup'
            ];
        }
        
        if (($data['waste_hazardous_kg'] ?? 0) > 50) {
            $recommendations[] = [
                'category' => 'Environmental',
                'priority' => '🔴 Crítica',
                'action' => 'Gestor autorizado residuos peligrosos',
                'impact' => 'Compliance legal + evitar multas €3,000+',
                'cost' => '€300-800/año'
            ];
        }
        
        // SOCIAL
        $salaryGap = $data['salary_gap_percent'] ?? 0;
        if ($salaryGap > 15) {
            $recommendations[] = [
                'category' => 'Social',
                'priority' => '🔴 Crítica CSRD',
                'action' => 'Auditoría salarial + plan equidad género',
                'impact' => '+2.0 puntos S + obligatorio CSRD',
                'cost' => '€800-2,000'
            ];
        }
        
        $turnover = $data['turnover_rate'] ?? 0;
        if ($turnover > 25) {
            $recommendations[] = [
                'category' => 'Social',
                'priority' => 'Alta',
                'action' => 'Plan retención: Encuestas + mejoras clima laboral',
                'impact' => '+1.5 puntos S + ahorro costes rotación',
                'cost' => '€500-1,500'
            ];
        }
        
        $trainingPerEmp = $data['employees_total'] > 0 ? $data['training_hours'] / $data['employees_total'] : 0;
        if ($trainingPerEmp < 20) {
            $recommendations[] = [
                'category' => 'Social',
                'priority' => 'Media',
                'action' => 'Plan formación 20h/empleado/año',
                'impact' => '+1.0 puntos S + productividad',
                'cost' => '€200-500/empleado'
            ];
        }
        
        if (!$data['supplier_audit_hr']) {
            $recommendations[] = [
                'category' => 'Social',
                'priority' => 'Alta',
                'action' => 'Auditoría DDHH top 5 proveedores',
                'impact' => '+1.0 puntos S + compliance CSRD S2',
                'cost' => '€400-1,000'
            ];
        }
        
        if (!$data['child_labor_policy']) {
            $recommendations[] = [
                'category' => 'Social',
                'priority' => 'Crítica',
                'action' => 'Implementar política trabajo infantil cadena valor',
                'impact' => '+0.5 puntos S + CSRD mandatory',
                'cost' => 'Gratis (plantilla)'
            ];
        }
        
        // GOVERNANCE
        $auditedRatio = $data['suppliers_total'] > 0 
            ? ($data['suppliers_audited'] / $data['suppliers_total']) * 100 
            : 0;
        
        if ($auditedRatio < 25) {
            $recommendations[] = [
                'category' => 'Governance',
                'priority' => 'Alta',
                'action' => 'Auditar 25% proveedores críticos',
                'impact' => '+1.5 puntos G + reduce riesgos cadena',
                'cost' => '€300-800'
            ];
        }
        
        if (!$data['anticorruption_policy']) {
            $recommendations[] = [
                'category' => 'Governance',
                'priority' => '🔴 CRÍTICA CSRD',
                'action' => 'Implementar política anticorrupción escrita + formación',
                'impact' => '+1.5 puntos G + CSRD mandatory',
                'cost' => 'Gratis (plantilla) + €200 formación'
            ];
        }
        
        $ceoRatio = $data['ceo_median_pay_ratio'] ?? 0;
        if ($ceoRatio > 50) {
            $recommendations[] = [
                'category' => 'Governance',
                'priority' => 'Media',
                'action' => 'Revisar equidad salarial dirección vs empleados',
                'impact' => '+0.5 puntos G + reputación',
                'cost' => 'Interno (revisión política)'
            ];
        }
        
        if ($data['board_independent_percent'] < 20) {
            $recommendations[] = [
                'category' => 'Governance',
                'priority' => 'Media',
                'action' => 'Incorporar consejero independiente',
                'impact' => '+1.0 puntos G + gobierno corporativo',
                'cost' => 'Variable (honorarios)'
            ];
        }
        
        // Ordenar por prioridad
        usort($recommendations, function($a, $b) {
            $priorities = ['🔴 CRÍTICA CSRD' => 0, '🔴 Crítica' => 1, 'Crítica' => 2, 'Alta' => 3, 'Media' => 4];
            return ($priorities[$a['priority']] ?? 5) <=> ($priorities[$b['priority']] ?? 5);
        });
        
        return array_slice($recommendations, 0, 8); // Top 8 recomendaciones
    }

    // Calcular cobertura VSME (de 140 campos totales)
    public function calculateVSMECoverage(): int
    {
        $totalFields = 140; // VSME completo
        $filledFields = 30; // MES 1-2
        
        return round(($filledFields / $totalFields) * 100);
    }

    // Método principal cálculo
    public function calculateAllScores(): void
    {
        $data = array_merge($this->toArray(), $this->input_data ?? []);
        
        $this->score_environmental = $this->calculateEnvironmental($data);
        $this->score_social = $this->calculateSocial($data);
        $this->score_governance = $this->calculateGovernance($data);
        
        $this->esg_score = round(
            ($this->score_environmental * 0.4) + 
            ($this->score_social * 0.3) + 
            ($this->score_governance * 0.3),
            1
        );
        
        $this->recommendations = $this->generateRecommendations(
            $data,
            $this->score_environmental,
            $this->score_social,
            $this->score_governance
        );
        
        $this->vsme_coverage_percent = $this->calculateVSMECoverage();
        
        $this->save();
    }
}
