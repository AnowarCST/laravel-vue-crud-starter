<?php

namespace Tests\Feature\Api\Tags;

use Tests\Feature\Api\TaxonomyTestCase;

class TagTest extends TaxonomyTestCase
{
    protected function resource(): string
    {
        return 'tag';
    }

    protected function label(): string
    {
        return 'Tag';
    }
}
