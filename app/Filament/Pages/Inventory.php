<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget\Stat;

class Inventory extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    public static function getNavigationGroup(): ?string
    {
        return __('Products Management');
    }
    protected static string $view = 'filament.pages.inventory';

    public $products;
    public $totalValue;
    public $topProducts;
    public $stockByCategory;
    public $lowStockProducts;

    public function mount()
    {
        $this->products = Product::with('category')->get();
        $this->totalValue = $this->products->sum(fn($product) => $product->stock * $product->purchase_price);
        $this->topProducts = $this->products->sortByDesc('stock')->take(10)->values();
        $this->stockByCategory = $this->products
            ->groupBy(fn($product) => $product->category->name ?? 'Uncategorized')
            ->map(fn($group) => $group->sum('stock'));
        $this->lowStockProducts = $this->products->filter(fn($product) => $product->stock < 10);
    }

}

