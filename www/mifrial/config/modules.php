<?php

declare(strict_types=1);

use Mifrial\Messages\Chat\Interface\Container\IChatContainer;

return [
    'lazy' => [
        IChatContainer::class => [
            'group' => 'Messages',
            'name' => 'Chat',
        ],
    ],
];
