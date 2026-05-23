<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AppConfigController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'financial_container_label' => financialLabel(),
            ],
        ]);
    }
}
