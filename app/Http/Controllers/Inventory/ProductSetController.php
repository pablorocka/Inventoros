<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\ProductSet;
use App\Services\ProductSetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductSetController extends Controller
{
    public function __construct(private ProductSetService $sets)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $sets = ProductSet::forOrganization($request->user()->organization_id)
            ->with('items.activeProducts:id,stock')
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(config('limits.pagination.default'))
            ->withQueryString()
            ->through(fn (ProductSet $set) => [
                'id' => $set->id,
                'name' => $set->name,
                'description' => $set->description,
                'items_count' => $set->items->count(),
                'available' => $set->availableQuantity(),
            ]);

        return Inertia::render('Sets/Index', [
            'sets' => $sets,
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Sets/Create', [
            'productOptions' => $this->sets->productOptions($request->user()->organization_id),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateSet($request);

        $set = DB::transaction(function () use ($request, $validated) {
            $set = ProductSet::create([
                'organization_id' => $request->user()->organization_id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'instructions' => $validated['instructions'] ?? null,
            ]);

            $this->sets->syncItems($set, $validated['items']);

            return $set;
        });

        return redirect()->route('sets.show', $set)
            ->with('success', 'Set created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, ProductSet $set): Response
    {
        $this->authorizeSet($request, $set);

        $set->load('items.activeProducts.location:id,name');

        return Inertia::render('Sets/Show', [
            'set' => [
                'id' => $set->id,
                'name' => $set->name,
                'description' => $set->description,
                'instructions' => $set->instructions,
                'available' => $set->availableQuantity(),
                'items' => $set->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                    'products' => $item->activeProducts->map(fn ($product) => [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'price' => $product->price,
                        'stock' => $product->stock,
                        'location_name' => $product->location?->name ?? 'No location',
                    ])->values(),
                ])->values(),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Request $request, ProductSet $set): Response
    {
        $this->authorizeSet($request, $set);

        $set->load('items.activeProducts:id');

        return Inertia::render('Sets/Edit', [
            'set' => [
                'id' => $set->id,
                'name' => $set->name,
                'description' => $set->description,
                'instructions' => $set->instructions,
                'items' => $set->items->map(fn ($item) => [
                    'product_id' => $item->activeProducts->first()?->id,
                    'name' => $item->name,
                    'quantity' => $item->quantity,
                ])->values(),
            ],
            'productOptions' => $this->sets->productOptions($request->user()->organization_id),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ProductSet $set): RedirectResponse
    {
        $this->authorizeSet($request, $set);

        $validated = $this->validateSet($request);

        DB::transaction(function () use ($set, $validated) {
            $set->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'instructions' => $validated['instructions'] ?? null,
            ]);

            $this->sets->syncItems($set, $validated['items']);
        });

        return redirect()->route('sets.show', $set)
            ->with('success', 'Set updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, ProductSet $set): RedirectResponse
    {
        $this->authorizeSet($request, $set);

        $set->delete();

        return redirect()->route('sets.index')
            ->with('success', 'Set deleted successfully.');
    }

    /**
     * Validate the set form.
     */
    private function validateSet(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'instructions' => 'nullable|string|max:60000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1|max:1000',
        ], [
            'items.required' => 'Add at least one product to the set.',
            'items.*.product_id.required' => 'This product is no longer available. Remove it from the set.',
        ]);
    }

    /**
     * Ensure users can only reach sets from their organization.
     */
    private function authorizeSet(Request $request, ProductSet $set): void
    {
        if ($set->organization_id !== $request->user()->organization_id) {
            abort(403, 'Unauthorized action.');
        }
    }
}
