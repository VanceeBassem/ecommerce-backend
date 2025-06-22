<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $cacheKey = 'products_' . md5(json_encode($request->all()));

        $products = Cache::remember($cacheKey, 60, function () use ($request) {
            return Product::query()
                ->when($request->name, fn($q) => $q->where('name', 'like', "%{$request->name}%"))
                ->when($request->min_price, fn($q) => $q->where('price', '>=', $request->min_price))
                ->when($request->max_price, fn($q) => $q->where('price', '<=', $request->max_price))
                ->paginate(10);
        });

        return response()->json($products);
    }
    public function store(Request $request)
{
    $validator = Validator::make($request->all(), [
        'name' => 'required|string|max:255',
        'price' => 'required|numeric|min:0',
        'stock' => 'required|integer|min:0',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    $product = Product::create([
        'name' => $request->name,
        'price' => $request->price,
        'stock' => $request->stock,
    ]);

    return response()->json([
        'message' => 'Product created successfully.',
        'product' => $product,
    ], 201);
  }
}

