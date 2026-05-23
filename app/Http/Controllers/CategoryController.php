<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Get categories (default + user)
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $categories = Category::where(function ($q) use ($userId) {
                $q->whereNull('user_id') // default categories
                  ->orWhere('user_id', $userId);
            })
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
            'meta' => [
                'allow_custom_category' => true,
                'custom_trigger_option' => 'Other',
            ],
            'modules' => [
                'single' => $this->moduleOptions($categories),
                'multi' => $this->moduleOptions($categories),
                'scan' => $this->moduleOptions($categories),
                'sms' => $this->moduleOptions($categories),
                'voice' => $this->moduleOptions($categories),
            ],
        ]);
    }

    /**
     * Create new category
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $userId = $request->user()->id;
        $name = trim($request->name);
        $slug = Str::slug($name);

        // 🔥 Prevent duplicate categories
        $existing = Category::where('user_id', $userId)
            ->where('slug', $slug)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'data' => $existing,
                'message' => 'Category already exists'
            ]);
        }

        $category = Category::create([
            'user_id' => $userId,
            'name' => $name,
            'slug' => $slug,
        ]);

        return response()->json([
            'success' => true,
            'data' => $category,
        ], 201);
    }

    /**
     * Delete category
     */
    public function destroy(int $id): JsonResponse
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found'
            ], 404);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted',
        ]);
    }

    private function moduleOptions($categories)
    {
        $preferred = [
            'Food & Dining',
            'Grocery',
            'Transport',
            'Shopping',
            'Bills & Utilities',
            'Health',
            'Entertainment',
            'Other',
        ];

        $available = $categories->keyBy('name');

        return collect($preferred)
            ->map(function (string $name) use ($available) {
                $category = $available->get($name);

                return [
                    'id' => $category?->id,
                    'name' => $category?->name ?? $name,
                ];
            })
            ->values();
    }
}
