<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NetworkService;
use Illuminate\Http\Request;

class NetworkController extends Controller
{
    public function __construct(protected NetworkService $network) {}

    public function tree(Request $request)
    {
        $depth = (int) $request->query('depth', 5);
        return response()->json($this->network->tree($request->user(), max(1, min($depth, 10))));
    }

    public function stats(Request $request)
    {
        return response()->json($this->network->stats($request->user()));
    }
}
