<?php

namespace App\Http\Controllers;

use App\Models\Mt5PromoCode;
use Illuminate\View\View;

class AdminMt5PromoCodeController extends Controller
{
    public function index(): View
    {
        $promoCodes = Mt5PromoCode::query()
            ->with(['poolEntry', 'usedByUser', 'usedOrder'])
            ->orderBy('code')
            ->get();

        return view('admin.mt5-promo-codes.index', [
            'promoCodes' => $promoCodes,
        ]);
    }
}
