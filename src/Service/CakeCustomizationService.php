<?php

namespace App\Service;

use App\Entity\CakeCustomization;

class CakeCustomizationService
{
    public function getSizeOptions(): array
    {
        return [
            'Small' => 'small',
            'Medium' => 'medium',
            'Large' => 'large',
        ];
    }

    public function getFlavorOptions(): array
    {
        return [
            'Chocolate' => 'chocolate',
            'Vanilla' => 'vanilla',
            'Red Velvet' => 'red_velvet',
            'Strawberry' => 'strawberry',
        ];
    }

    public function getDecorationOptions(): array
    {
        return [
            'Sprinkles' => 'sprinkles',
            'Fruits' => 'fruits',
            'Candles' => 'candles',
            'Chocolate Chips' => 'chocolate_chips',
            'Flowers' => 'flowers',
        ];
    }

    public function getBasePriceForSize(string $size): float
    {
        return match ($size) {
            'small' => 1200.0,
            'large' => 2400.0,
            default => 1700.0,
        };
    }

    public function getDecorationPrice(string $decoration): float
    {
        return match ($decoration) {
            'candles' => 120.0,
            'flowers' => 180.0,
            'fruits' => 140.0,
            'chocolate_chips' => 130.0,
            default => 80.0,
        };
    }

    public function calculatePrice(CakeCustomization $customization): float
    {
        $base = $this->getBasePriceForSize($customization->getSize() ?? 'medium');
        $decorations = $customization->getDecorations();
        $extra = 0.0;

        foreach ($decorations as $decoration) {
            $extra += $this->getDecorationPrice($decoration);
        }

        return round($base + $extra, 2);
    }

    public function formatLabel(string $name): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $name));
    }
}
