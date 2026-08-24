<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use App\Models\Order\Order;
use App\Models\Order\OrderPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderPaymentController extends Controller
{
    /**
     * Register a payment (partial or full) for an order.
     */
    public function store(Request $request, Order $order)
    {
        // Ensure user can only manage payments for orders from their organization
        if ($order->organization_id !== $request->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,transfer,card,yappy,other',
            'reference' => 'nullable|string|max:255',
            'paid_at' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::transaction(function () use ($order, $validated, $request) {
                // Lock the order row to prevent concurrent payment races
                $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();

                if ($lockedOrder->status === 'cancelled') {
                    throw ValidationException::withMessages([
                        'amount' => 'Cannot register payments on a cancelled order.',
                    ]);
                }

                if ($lockedOrder->payment_status === 'paid') {
                    throw ValidationException::withMessages([
                        'amount' => 'This order is already fully paid.',
                    ]);
                }

                $balance = round((float) $lockedOrder->total - (float) $lockedOrder->amount_paid, 2);

                if ((float) $validated['amount'] > $balance + 0.01) {
                    throw ValidationException::withMessages([
                        'amount' => 'Payment amount ($' . number_format((float) $validated['amount'], 2) . ') exceeds the outstanding balance ($' . number_format($balance, 2) . ').',
                    ]);
                }

                $lockedOrder->payments()->create([
                    'organization_id' => $lockedOrder->organization_id,
                    'created_by' => $request->user()->id,
                    'amount' => round((float) $validated['amount'], 2),
                    'method' => $validated['method'],
                    'reference' => $validated['reference'] ?? null,
                    'paid_at' => $validated['paid_at'],
                    'notes' => $validated['notes'] ?? null,
                ]);

                $lockedOrder->recalculatePaymentStatus();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()->back()
            ->with('success', 'Payment registered successfully.');
    }

    /**
     * Remove a payment from an order (soft delete) and recalculate balance.
     */
    public function destroy(Request $request, Order $order, OrderPayment $payment)
    {
        // Ensure user can only manage payments for orders from their organization
        if ($order->organization_id !== $request->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }

        // Ensure the payment belongs to the given order
        if ($payment->order_id !== $order->id) {
            abort(404);
        }

        DB::transaction(function () use ($order, $payment) {
            $payment->delete();

            $lockedOrder = Order::where('id', $order->id)->lockForUpdate()->first();
            $lockedOrder->recalculatePaymentStatus();
        });

        return redirect()->back()
            ->with('success', 'Payment deleted successfully.');
    }
}
