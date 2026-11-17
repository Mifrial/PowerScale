<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Roleplay\Game\Repository\GameActiveEffectRepository;

/**
 * Сборка сервисов длительных эффектов.
 */
final class GameActiveEffectWiring
{
    public readonly GameActiveEffectRepository $effects;

    public readonly GameActiveEffectChat $chat;

    public readonly GameActiveEffectCreate $create;

    public readonly GameActiveEffectTick $tick;

    public readonly GameActiveEffectView $view;

    /**
     * Создаёт сервисы.
     *
     * @param IServiceLocator $serviceLocator Каталог контейнеров.
     * @param GameStrikeShared $shared Общие сервисы удара.
     *
     * @return void
     */
    public function __construct(IServiceLocator $serviceLocator, GameStrikeShared $shared)
    {
        $ports = new GameStrikePorts($serviceLocator);
        $this->effects = $shared->repos->activeEffects;
        $this->chat = new GameActiveEffectChat($ports->chats(), $shared->chat);
        $this->create = new GameActiveEffectCreate($this->effects, new GameSpellDurationRules());
        $this->tick = new GameActiveEffectTick($this->effects, $this->chat);
        $this->view = new GameActiveEffectView();
    }
}
