<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Receipt;
use Illuminate\Support\Facades\Validator;

class ReceiptController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $receipts = Receipt::where('user_id', $request->user()->id)->get();
        $receipts->transform(function ($receipt) {
            $receipt->image_url = str_replace('localhost', '10.218.131.180', $receipt->image_url);
            return $receipt;
        });
        return response()->json(['success' => true, 'data' => $receipts]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image_url' => 'required|string', // base64 encoded image
            'amount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $imageUrl = $request->input('image_url');
        $imagePath = null;

        // Check if it's a base64 image
        if (preg_match('/^data:image\/(\w+);base64,/', $imageUrl, $type)) {
            $imageUrl = substr($imageUrl, strpos($imageUrl, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif

            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
                return response()->json(['success' => false, 'message' => 'Invalid image type'], 422);
            }

            $imageUrl = str_replace(' ', '+', $imageUrl);
            $imageUrl = base64_decode($imageUrl);

            if ($imageUrl === false) {
                return response()->json(['success' => false, 'message' => 'Base64 decode failed'], 422);
            }

            $fileName = 'receipt_' . time() . '_' . uniqid() . '.' . $type;
            $imagePath = 'receipts/' . $fileName;

            \Storage::disk('public')->put($imagePath, $imageUrl);
        } else {
            // If not base64, assume it's a URL or path
            $imagePath = $imageUrl;
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;
        $data['image_url'] = $imagePath ? str_replace('localhost', '10.218.131.180', \Storage::disk('public')->url($imagePath)) : $imageUrl;

        $receipt = Receipt::create($data);

        return response()->json(['success' => true, 'message' => 'Receipt saved', 'data' => $receipt], 201);
    }

    public function show(Request $request, $id)
    {
        $receipt = Receipt::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$receipt) {
            return response()->json(['success' => false, 'message' => 'Receipt not found'], 404);
        }

        $receipt->image_url = str_replace('localhost', '10.218.131.180', $receipt->image_url);

        return response()->json(['success' => true, 'data' => $receipt]);
    }

    public function update(Request $request, $id)
    {
        $receipt = Receipt::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$receipt) {
            return response()->json(['success' => false, 'message' => 'Receipt not found'], 404);
        }

        $validated = $request->validate([
            'image_url' => 'required|string',
            'amount' => 'required|numeric|min:0',
        ]);

        $validated['image_url'] = str_replace('localhost', '10.218.131.180', $validated['image_url']);

        $receipt->update($validated);

        return response()->json(['success' => true, 'message' => 'Receipt updated', 'data' => $receipt]);
    }

    public function destroy(Request $request, $id)
    {
        $receipt = Receipt::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$receipt) {
            return response()->json(['success' => false, 'message' => 'Receipt not found'], 404);
        }

        $receipt->delete();

        return response()->json(['success' => true, 'message' => 'Receipt deleted']);
    }
}
