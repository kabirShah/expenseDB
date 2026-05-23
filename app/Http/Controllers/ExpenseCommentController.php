<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseComment;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCommentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeExpenseAccess($request, $expense);

        return response()->json([
            'success' => true,
            'data' => $expense->comments()->with('user:id,name,email,phone')->latest()->paginate($request->integer('per_page', 30)),
        ]);
    }

    public function store(Request $request, Expense $expense): JsonResponse
    {
        $this->authorizeExpenseAccess($request, $expense);

        $data = $request->validate([
            'comment' => 'nullable|string|max:1000',
            'reaction' => 'nullable|string|max:50',
            'metadata' => 'nullable|array',
        ]);

        abort_if(empty($data['comment']) && empty($data['reaction']), 422, 'Comment or reaction is required.');

        $comment = ExpenseComment::create([
            'expense_id' => $expense->id,
            'user_id' => $request->user()->id,
            'comment' => $data['comment'] ?? null,
            'reaction' => $data['reaction'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $comment->load('user:id,name,email,phone')], 201);
    }

    private function authorizeExpenseAccess(Request $request, Expense $expense): void
    {
        if ((int) $expense->user_id === (int) $request->user()->id) {
            return;
        }

        if ($expense->group_id && GroupMember::query()
            ->where('group_id', $expense->group_id)
            ->where('user_id', $request->user()->id)
            ->exists()) {
            return;
        }

        abort(403, 'Unauthorized');
    }
}
