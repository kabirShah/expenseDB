<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ParserEvent;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ParserEventController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    // Get all parser events for logged-in user
    public function index(Request $request)
    {
        $parserEvents = ParserEvent::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $parserEvents]);
    }

    // Store new parser event
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'file_name' => 'required|string|max:255',
            'file_path' => 'required|string|max:255',
            'file_type' => 'nullable|string|in:pdf,csv,excel',
            'status' => 'nullable|string|in:pending,processing,completed,failed',
            'total_transactions' => 'nullable|integer|min:0',
            'parsed_transactions' => 'nullable|integer|min:0',
            'failed_transactions' => 'nullable|integer|min:0',
            'metadata' => 'nullable|json',
            'error_message' => 'nullable|string',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;
        $data['event_id'] = Str::uuid();
        $data['status'] = $data['status'] ?? 'pending';
        $data['file_type'] = $data['file_type'] ?? 'pdf';

        $parserEvent = ParserEvent::create($data);

        return response()->json(['success' => true, 'message' => 'Parser event created', 'data' => $parserEvent], 201);
    }

    // Show single parser event
    public function show(Request $request, $id)
    {
        $parserEvent = ParserEvent::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$parserEvent) {
            return response()->json(['success' => false, 'message' => 'Parser event not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $parserEvent]);
    }

    // Update parser event
    public function update(Request $request, $id)
    {
        $parserEvent = ParserEvent::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$parserEvent) {
            return response()->json(['success' => false, 'message' => 'Parser event not found'], 404);
        }

        $validated = $request->validate([
            'bank_name' => 'sometimes|required|string|max:255',
            'file_name' => 'sometimes|required|string|max:255',
            'file_path' => 'sometimes|required|string|max:255',
            'file_type' => 'nullable|string|in:pdf,csv,excel',
            'status' => 'nullable|string|in:pending,processing,completed,failed',
            'total_transactions' => 'nullable|integer|min:0',
            'parsed_transactions' => 'nullable|integer|min:0',
            'failed_transactions' => 'nullable|integer|min:0',
            'metadata' => 'nullable|json',
            'error_message' => 'nullable|string',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
        ]);

        $parserEvent->update($validated);

        return response()->json(['success' => true, 'message' => 'Parser event updated', 'data' => $parserEvent]);
    }

    // Delete parser event
    public function destroy(Request $request, $id)
    {
        $parserEvent = ParserEvent::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$parserEvent) {
            return response()->json(['success' => false, 'message' => 'Parser event not found'], 404);
        }

        $parserEvent->delete();
        return response()->json(['success' => true, 'message' => 'Parser event deleted']);
    }
}
