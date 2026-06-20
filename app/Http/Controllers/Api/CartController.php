<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\ServiceLifecycleManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CartService;


class CartController extends Controller
{
public function sync(Request $request)
{
    $data = $request->validate([
        'cart_token' => 'required|uuid',
        'items' => 'required|array|min:1',

        'items.*.id' => 'required|string',
        'items.*.type' => 'required|in:domain,server',
        'items.*.name' => 'required|string',
        'items.*.price' => 'required|numeric|min:0',
        'items.*.quantity' => 'required|integer|min:1',
        'items.*.period' => 'required|integer|min:1',
        'items.*.periodLabel' => 'nullable|string',
        'items.*.meta' => 'nullable|array',
    ]);

    Log::info('🛒 Cart sync started', $data);

    DB::transaction(function () use ($data) {

        $cart = Cart::firstOrCreate(
            ['cart_token' => $data['cart_token']],
            ['status' => 'open']
        );

        Log::info('🧾 Cart loaded', ['cart_id' => $cart->id]);

        // Clear old items
        $cart->items()->delete();
        Log::info('🧹 Old cart items deleted', ['cart_id' => $cart->id]);

        foreach ($data['items'] as $item) {
            $created = $cart->items()->create([
                'item_uid' => $item['id'],
                'type' => $item['type'],
                'name' => $item['name'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'period' => $item['period'],
                'period_label' => $item['periodLabel'] ?? null,
                'meta' => $item['meta'] ?? null,
            ]);

            Log::info('✅ Cart item inserted', $created->toArray());
        }
    });

    return response()->json(['success' => true]);
}public function checkout(Request $request, ServiceLifecycleManager $lifecycle)
{
    \Log::info('🛒 Checkout called', [
        'cart_token' => $request->header('X-Cart-Token'),
        'auth_user' => auth()->id()
    ]);

    // 🔒 1. USER MUST BE LOGGED IN
    if (!auth()->check()) {
        \Log::warning('❌ Checkout blocked: guest user');
        return response()->json([
            'message' => 'Login required before checkout'
        ], 401);
    }

    $cartToken = $request->header('X-Cart-Token');

    if (!$cartToken) {
        return response()->json(['message' => 'Missing cart token'], 422);
    }

    try {
        return DB::transaction(function () use ($cartToken, $lifecycle) {

            // 🔒 2. LOCK CART INSIDE TRANSACTION
            $cart = Cart::where('cart_token', $cartToken)
                ->where('status', 'locked')
                ->with('items')
                ->lockForUpdate()
                ->first();

            if (!$cart) {
                \Log::warning('❌ Checkout failed: No active locked cart', [
                    'cart_token' => $cartToken
                ]);
                return response()->json(['message' => 'No active cart'], 422);
            }

            \Log::info('🧾 Cart loaded', [
                'cart_id' => $cart->id,
                'items_count' => $cart->items->count()
            ]);

            // 🔗 3. ATTACH CART TO LOGGED USER (CRITICAL)
            if (!$cart->user_id) {
                $cart->user_id = auth()->id();
                $cart->save();
                \Log::info('🔗 Cart assigned to user', [
                    'cart_id' => $cart->id,
                    'user_id' => $cart->user_id
                ]);
            }

            // 💵 4. CREATE INVOICE (ALWAYS REAL USER)
            $calculatedTotal = $cart->items->sum(
                
                fn($i) =>  $i->price
          
            );
            \Log::info("price is {$calculatedTotal}");

            // Log items and calculated total for debugging if total is unexpectedly zero
            \Log::info('Invoice total calculation', [
                'cart_id' => $cart->id,
                'items_count' => $cart->items->count(),
                'items' => $cart->items->map(fn($it) => [
                    'id' => $it->id,
                    'item_uid' => $it->item_uid,
                    'price' => $it->price,
                    'quantity' => $it->quantity,
                    'meta' => $it->meta,
                ])->toArray(),
                'calculated_total' => $calculatedTotal,
            ]);

            $invoice = Invoice::create([
                'user_id' => $cart->user_id,
                'status' => 'unpaid',
                'amount' => $calculatedTotal,
                'currency' => 'USD',
            ]);

            \Log::info('✅ Invoice created', ['invoice_id' => $invoice->id, 'total' => $invoice->total]);

            // 📦 5. CREATE INVOICE ITEMS + SERVICES
            foreach ($cart->items as $item) {

                $invoiceItem = InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'type' => $item->type,
                    'amount' => $item->price,
                    'description' => $item->name,
                    'reference_data' => json_encode($item->meta),
                ]);

                \Log::info('✅ Invoice item created', [
                    'invoice_item_id' => $invoiceItem->id
                ]);

                // 🚀 SERVICE PROVISIONING
                if ($item->type === 'server') {
                    $server = $lifecycle->createServer($invoiceItem);
                    $invoiceItem->update(['service_id' => $server->id]);

                    \Log::info('🖥 Server created', ['server_id' => $server->id]);
                }
            }

            // 🧹 6. CLOSE CART
            $cart->update(['status' => 'checked_out']);

            \Log::info('🏁 Checkout complete', ['cart_id' => $cart->id]);

            return response()->json([
                'success' => true,
                'invoice' => $invoice->load('items')
            ]);
        });

    } catch (\Exception $e) {
        \Log::error('❌ Checkout crashed', [
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Checkout failed. Please try again.'
        ], 500);
    }
}


public function prepare(Request $request)
{
    \Log::info('Prepare called', ['request_raw' => $request->all()]);

    // Validate cart token
    try {
        $data = $request->validate([
            'cart_token' => 'required|uuid'
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error('Validation failed', ['errors' => $e->errors()]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    }

    $cartToken = $data['cart_token'];
    \Log::info('Validated cart token', ['cart_token' => $cartToken]);

    // Ensure cart exists
    $cart = Cart::firstOrCreate(
        ['cart_token' => $cartToken],
        ['status' => 'open']
    );

    \Log::info('Cart fetched', [
        'cart_id' => $cart->id,
        'status' => $cart->status,
        'user_id' => $cart->user_id,
        'items_count' => $cart->items()->count()
    ]);

    // Make sure there are items
    $items = $cart->items()->get();
    if ($items->isEmpty()) {
        \Log::warning('Cart is empty', [
            'cart_id' => $cart->id,
            'items_db' => $items->toArray()
        ]);

        return response()->json([
            'message' => 'Cart is empty'
        ], 422);
    }

    // Attach user and lock
    $cart->update([
        'user_id' => auth()->id(), // null if guest
        'status' => 'locked'
    ]);

    \Log::info('Cart locked', [
        'cart_id' => $cart->id,
        'user_id' => $cart->user_id,
        'status' => $cart->status
    ]);

    $total = $items->sum(fn($i) => $i->price * $i->quantity);

    return response()->json([
        'success' => true,
        'total' => $total,
        'items_count' => $items->count(),
        'cart_id' => $cart->id
    ]);
}


}
