<?php

namespace App\Http\Controllers;
use App\Models\UserPreference;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'budget_mode' => 'nullable|string',
            'monthly_budget' => 'nullable|numeric',
            'category_budget' => 'nullable|array',
            'warning_threshold' => 'nullable|integer',
            'saving_goal' => 'nullable|string',
            'saving_target' => 'nullable|numeric',
            'tips_enabled' => 'boolean',
            'tips_types' => 'nullable|array',
            'notification_frequency' => 'nullable|string',
            'notify_time' => 'nullable',
            'onboarding_completed' => 'boolean'
        ]);

        $prefs = UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        return response()->json([
            'success' => true,
            'data' => $prefs
        ]);
    }
    public function show()
    {
        return response()->json(
            auth()->user()->preferences
        );
    }

}
