<?php

namespace App\Enums;

/**
 * Stored in products.status. New products are active unless another
 * status is sent.
 */
enum ProductStatus: string
{
    case Active = 'active';
    case Draft = 'draft';
    case Archived = 'archived';
}
