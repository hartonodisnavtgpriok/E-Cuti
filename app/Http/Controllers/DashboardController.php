<?php

namespace App\Http\Controllers;

use App\Domain\Leave\AnnualBalanceService;
use App\Domain\Leave\LeaveTypes;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AnnualBalanceService $annualBalances): View
    {
        $balances = DB::table('leave_balances')
            ->where('user_id', $request->user()->id)
            ->whereIn('leave_type', array_slice(LeaveTypes::ALL, 1))
            ->orderBy('leave_type')
            ->orderByDesc('year')
            ->get();

        $annual = null;
        $annualError = null;
        DB::transaction(function () use ($request, $annualBalances, &$annual, &$annualError) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $baseline = DB::table('annual_balance_baselines')->where('user_id', $request->user()->id)->first();
            if ($baseline) {
                try {
                    $annual = $annualBalances->project($request->user()->id, $baseline)['years'][now()->year] ?? null;
                } catch (InvalidArgumentException $exception) {
                    Log::error('Perhitungan saldo cuti tahunan gagal.', [
                        'user_id' => $request->user()->id, 'error' => $exception->getMessage(),
                    ]);
                    $annualError = 'Data saldo tidak dapat dihitung. Hubungi admin.';
                }
            }
        });

        $user = $request->user();
        $isApprover = $user->position_id !== null
            && DB::table('approval_routes')
                ->where(fn ($q) => $q->where('first_position_id', $user->position_id)
                    ->orWhere('second_position_id', $user->position_id))
                ->exists()
            && User::where('position_id', $user->position_id)->where('active', true)->count() === 1;

        return view('dashboard', [
            'canApprove' => $isApprover,
            'balances' => $balances, 'annual' => $annual, 'annualError' => $annualError,
            'otherTypes' => array_slice(LeaveTypes::ALL, 1),
            'socialLinks' => DB::table('social_links')->orderBy('id')->get(),
        ]);
    }
}
