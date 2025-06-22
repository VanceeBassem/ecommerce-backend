<?php

namespace App\Http\Controllers\API;

use App\Events\OrderPlaced;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
 public function store(Request $request)
{
    $validated = $request->validate([
        'products' => 'required|array|min:1',
        'products.*.id' => 'required|exists:products,id',
        'products.*.quantity' => 'required|integer|min:1',
    ]);

    $user = $request->user();

    DB::beginTransaction();

    try {
        $total = 0;

        // Create order with placeholder total
        $order = Order::create([
            'user_id' => $user->id,
            'total' => 0,
        ]);

        foreach ($validated['products'] as $item) {
            $product = Product::findOrFail($item['id']);

            if ($product->stock < $item['quantity']) {
                throw new \Exception("Not enough stock for {$product->name}");
            }

            $product->decrement('stock', $item['quantity']);

            // Insert into pivot table (order_product)
            $order->products()->attach($product->id, [
                'quantity' => $item['quantity']
            ]);

            $total += $product->price * $item['quantity'];
        }

        $order->update(['total' => $total]);

        DB::commit();

        return response()->json([
            'message' => 'Order placed successfully.',
            'order_id' => $order->id
        ], 201);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['error' => $e->getMessage()], 400);
    }
}

    public function show($id)
    {
        $order = Order::with(['products'])->findOrFail($id);

        $orderData = [
            'id' => $order->id,
            'total' => $order->total,
            'products' => $order->products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $product->pivot->quantity,
                ];
            })
        ];

        return response()->json($orderData);
    }
}
