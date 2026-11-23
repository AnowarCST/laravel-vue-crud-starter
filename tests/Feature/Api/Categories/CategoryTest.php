<?php

namespace Tests\Feature\Api\Categories;

use Tests\Feature\Api\TaxonomyTestCase;

class CategoryTest extends TaxonomyTestCase
{
    protected function resource(): string
    {
        return 'category';
    }

    protected function label(): string
    {
        return 'Category';
    }
}
