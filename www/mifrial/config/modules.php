<?php

declare(strict_types=1);

use Mifrial\Library\Asset\Interface\Container\IAssetContainer;
use Mifrial\Messages\Chat\Interface\Container\IChatContainer;
use Mifrial\Messages\Notes\Interface\Container\INotesContainer;
use Mifrial\Roleplay\Character\Interface\Container\ICharacterContainer;
use Mifrial\Roleplay\Game\Interface\Container\IGameContainer;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Map\Interface\Container\IMapContainer;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Rule\Interface\Container\IRuleContainer;
use Mifrial\Roleplay\RuleSpace\Interface\Container\IRuleSpaceContainer;
use Mifrial\Roleplay\Scene\Interface\Container\ISceneContainer;
use Mifrial\Versioning\Space\Interface\Container\ISpaceContainer;

return [
    'lazy' => [
        IAssetContainer::class => [
            'group' => 'Library',
            'name' => 'Asset',
        ],
        IChatContainer::class => [
            'group' => 'Messages',
            'name' => 'Chat',
        ],
        INotesContainer::class => [
            'group' => 'Messages',
            'name' => 'Notes',
        ],
        ISpaceContainer::class => [
            'group' => 'Versioning',
            'name' => 'Space',
        ],
        IKeywordContainer::class => [
            'group' => 'Roleplay',
            'name' => 'Keyword',
        ],
        IMapContainer::class => [
            'group' => 'Roleplay',
            'name' => 'Map',
        ],
        ISceneContainer::class => [
            'group' => 'Roleplay',
            'name' => 'Scene',
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
        IGameContainer::class => [
            'group' => 'Roleplay',
            'name' => 'Game',
        ],
    ],
];
