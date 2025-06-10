<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget\Stat;

class Inventory extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function getNavigationLabel(): string
    {
        return __('Inventory');
    }

    protected function getCurrencySymbol(): string
{
    return app()->getLocale() === 'ar' ? 'جم' : 'EGP';
}

    public static function getNavigationGroup(): ?string
    {
        return __('Products Management'); // Translatable navigation group
    }

    protected static string $view = 'filament.pages.inventory';

    public $products;
    public $totalValue;
    public $topProducts;
    public $stockByCategory;
    public $lowStockProducts;

    public string $currencySymbol = '';

public function mount()
{
    $this->currencySymbol = $this->getCurrencySymbol();

    $this->products = Product::with('category')->get();

    $this->totalValue = $this->products->sum(fn($product) => $product->stock * $product->purchase_price);

    $this->topProducts = $this->products->sortByDesc('stock')->take(10)->values();

    $this->stockByCategory = $this->products
        ->groupBy(fn($product) => $product->category->name ?? __('Uncategorized'))
        ->map(fn($group) => $group->sum('stock'));

    $this->lowStockProducts = $this->products->filter(fn($product) => $product->stock < 10);
}


    public function getStats(): array
    {
        $currency = $this->getCurrencySymbol();

        return [
            Stat::make(__('Total Products'), $this->products->count())
                ->description(__('Number of products in inventory'))
                ->color('primary'),

            Stat::make(__('Total Stock'), $this->products->sum('stock'))
                ->description(__('Total quantity of all products'))
                ->color('success'),

            Stat::make(__('Inventory Value'), number_format($this->totalValue, 2) . " $currency")
                ->description(__('Total value of inventory'))
                ->color('warning'),
        ];
    }
}

