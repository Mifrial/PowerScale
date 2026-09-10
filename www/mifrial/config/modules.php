<?php

declare(strict_types=1);

use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Versioning\Space\Interface\Container\ISpaceContainer;

return [
    'lazy' => [
        IChatContainer::class => [
            'group' => 'Messages',
            'name' => 'Chat',
        ],
        ISpaceContainer::class => [
            'group' => 'Versioning',
            'name' => 'Space',
        ],
        IKeywordContainer::class => [
            'group' => 'Roleplay',
            'name' => 'Keyword',
        ],
        IMechanicContainer::class => [
            'group' => 'Roleplay',
            'name' => 'Mechanic',
        ],
        IRuleContainer::class => [
            'group' => 'Roleplay',
            'name' => 'Rule',
        ],
        IRuleSpaceContainer::class => [
            'group' => 'Roleplay',
            'name' => 'RuleSpace',
        ],
        ICharacterContainer::class => [
            'group' => 'Roleplay',
            'name' => 'Character',
        ],
    ],
];
