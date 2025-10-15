<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invoice;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $invoices = Invoice::where('user_id', $request->user()->id)->get();
        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_url' => 'required|string',  // could be S3/local file path
            'amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;
        $data['invoice_id'] = Str::uuid();

        $invoice = Invoice::create($data);

        return response()->json(['success' => true, 'message' => 'Invoice saved', 'data' => $invoice], 201);
    }

    public function show(Request $request, $id)
    {
        $invoice = Invoice::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $invoice]);
    }

    public function update(Request $request, $id)
    {
        $invoice = Invoice::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        $validated = $request->validate([
            'file_url' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'date' => 'required|date',
        ]);

        $invoice->update($validated);

        return response()->json(['success' => true, 'message' => 'Invoice updated', 'data' => $invoice]);
    }

    public function destroy(Request $request, $id)
    {
        $invoice = Invoice::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$invoice) {
            return response()->json(['success' => false, 'message' => 'Invoice not found'], 404);
        }

        $invoice->delete();

        return response()->json(['success' => true, 'message' => 'Invoice deleted']);
    }
}
