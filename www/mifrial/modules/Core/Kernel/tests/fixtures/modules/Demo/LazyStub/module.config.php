<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Tests\Fixture\ILazyStubContainer;
use Mifrial\Core\Kernel\Tests\Fixture\LazyStubContainer;

return [
    'container' => LazyStubContainer::class,
    'locator' => ILazyStubContainer::class,
    'ports' => [],
    'routes' => [],
    'events' => [],
];
