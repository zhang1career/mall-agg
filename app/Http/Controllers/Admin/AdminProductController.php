<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Mall\ProductInventoryService;
use App\Services\Mall\ProductPriceService;
use App\Services\Mall\ServFd\CmsProductClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Paganini\Aggregation\Exceptions\DownstreamServiceException;

class AdminProductController extends Controller
{
    public function __construct(
        private readonly CmsProductClient $cms,
        private readonly ProductPriceService $prices,
        private readonly ProductInventoryService $inventory,
    ) {}

    public function index(Request $request): View
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 15)));

        try {
            $pack = $this->cms->paginate($page, $perPage);
        } catch (DownstreamServiceException $e) {
            abort(502, $e->getMessage());
        }

        $ids = [];
        foreach ($pack['items'] as $row) {
            if (isset($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }
        $priceMap = $this->prices->getPriceMinorByProductIds($ids);
        $qtyMap = $this->inventory->getQuantityByProductIds($ids);

        return view('admin.products.index', [
            'items' => $pack['items'],
            'pagination' => $pack['pagination'],
            'priceMap' => $priceMap,
            'qtyMap' => $qtyMap,
        ]);
    }

    public function create(): View
    {
        return view('admin.products.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string|max:2000',
            'main_media' => 'nullable|string|max:2000',
            'ext_media' => 'nullable|string|max:2000',
            'price_minor' => 'nullable|integer|min:0',
            'quantity' => 'nullable|integer|min:0',
        ]);

        $fields = [
            'title' => $validated['title'],
            'description' => (string) ($validated['description'] ?? ''),
            'thumbnail' => (string) ($validated['thumbnail'] ?? ''),
            'main_media' => (string) ($validated['main_media'] ?? ''),
            'ext_media' => (string) ($validated['ext_media'] ?? ''),
        ];

        try {
            $created = $this->cms->create($fields);
        } catch (DownstreamServiceException $e) {
            return back()->withInput()->withErrors(['cms' => $e->getMessage()]);
        }

        $id = (int) ($created['id'] ?? 0);
        if ($id < 1) {
            return back()->withInput()->withErrors(['cms' => 'Invalid create response.']);
        }

        if (isset($validated['price_minor'])) {
            $this->prices->upsertPrice($id, (int) $validated['price_minor']);
        }
        if (isset($validated['quantity'])) {
            $this->inventory->upsertQuantity($id, (int) $validated['quantity']);
        }

        return redirect()->route('admin.products.edit', $id)
            ->with('status', 'Product created.');
    }

    public function edit(int $product): View
    {
        try {
            $row = $this->cms->find($product);
        } catch (DownstreamServiceException $e) {
            abort(404, $e->getMessage());
        }

        $priceMap = $this->prices->getPriceMinorByProductIds([$product]);
        $qtyMap = $this->inventory->getQuantityByProductIds([$product]);

        return view('admin.products.edit', [
            'product' => $row,
            'price_minor' => $priceMap[$product] ?? null,
            'quantity' => $qtyMap[$product] ?? null,
        ]);
    }

    public function update(Request $request, int $product): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'thumbnail' => 'nullable|string|max:2000',
            'main_media' => 'nullable|string|max:2000',
            'ext_media' => 'nullable|string|max:2000',
            'price_minor' => 'nullable|integer|min:0',
            'quantity' => 'nullable|integer|min:0',
        ]);

        $fields = [
            'title' => $validated['title'],
            'description' => (string) ($validated['description'] ?? ''),
            'thumbnail' => (string) ($validated['thumbnail'] ?? ''),
            'main_media' => (string) ($validated['main_media'] ?? ''),
            'ext_media' => (string) ($validated['ext_media'] ?? ''),
        ];

        try {
            $this->cms->update($product, $fields);
        } catch (DownstreamServiceException $e) {
            return back()->withInput()->withErrors(['cms' => $e->getMessage()]);
        }

        if (array_key_exists('price_minor', $validated) && $validated['price_minor'] !== null) {
            $this->prices->upsertPrice($product, (int) $validated['price_minor']);
        }
        if (array_key_exists('quantity', $validated) && $validated['quantity'] !== null) {
            $this->inventory->upsertQuantity($product, (int) $validated['quantity']);
        }

        return redirect()->route('admin.products.edit', $product)
            ->with('status', 'Saved.');
    }

    public function destroy(int $product): RedirectResponse
    {
        try {
            $this->cms->delete($product);
        } catch (DownstreamServiceException $e) {
            return redirect()->route('admin.products.index')
                ->withErrors(['cms' => $e->getMessage()]);
        }

        $this->prices->deleteForProduct($product);
        $this->inventory->deleteForProduct($product);

        return redirect()->route('admin.products.index')
            ->with('status', 'Product deleted.');
    }
}
