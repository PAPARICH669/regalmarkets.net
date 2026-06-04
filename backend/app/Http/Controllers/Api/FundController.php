<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FundService;
use Illuminate\Http\Request;

class FundController extends Controller
{
    public function __construct(protected FundService $fund) {}

    public function store(Request $request)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.00000001']]);
        $package = $this->fund->fund($request->user(), $data['amount']);
        return response()->json([
            'message' => 'Funded successfully. Your 200% package is now active.',
            'package' => $package,
        ], 201);
    }
}
