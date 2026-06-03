<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReinvestService;
use Illuminate\Http\Request;

class ReinvestController extends Controller
{
    public function __construct(protected ReinvestService $reinvest) {}

    public function store(Request $request)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.00000001']]);
        $package = $this->reinvest->reinvest($request->user(), $data['amount']);
        return response()->json(['message' => 'Reinvest successful. New package activated.', 'package' => $package], 201);
    }
}
