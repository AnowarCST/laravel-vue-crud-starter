<?php

namespace App\Enums;

/**
 * Values of the "stock" filter of GET /api/product: "in" is 1 or more
 * units, "low" is 1 to Product::LOW_STOCK_THRESHOLD units and "out" is 0
 * (see the Product::stockLevel() scope).
 */
enum StockLevel: string
{
    case In = 'in';
    case Low = 'low';
    case Out = 'out';
}
