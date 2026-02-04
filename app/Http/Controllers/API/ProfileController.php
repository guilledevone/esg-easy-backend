<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // VER PERFIL
    public function show(Request $request)
    {
        $profile = $request->user();
        $reportsCount = $profile->reports()->count();
        $reportsThisMonth = $profile->reports()
            ->whereMonth('created_at', now()->month)
            ->count();

        return response()->json([
            'profile' => $profile,
            'stats' => [
                'total_reports' => $reportsCount,
                'reports_this_month' => $reportsThisMonth,
                'plan' => $profile->is_pro ? 'Pro €29/mes' : 'Free (1/mes)'
            ]
        ]);
    }

    // UPGRADE MANUAL (PayPal webhook mejora Día 5)
    public function upgrade(Request $request)
    {
        $profile = $request->user();
        
        // Validar PayPal transaction ID (manual ahora)
        $request->validate([
            'paypal_tx_id' => 'required|string'
        ]);

        $profile->update(['is_pro' => true]);

        return response()->json([
            'message' => 'Upgrade exitoso! Pro activo',
            'profile' => $profile
        ]);
    }
}
