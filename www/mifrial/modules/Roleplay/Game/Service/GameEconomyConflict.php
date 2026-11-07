<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Game\Exception\GameEconomyConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;

/**
 * Конфликты экономики с проекцией актуального листа.
 */
final class GameEconomyConflict
{
    /**
     * Создаёт проекцию.
     *
     * @param GameEconomyPorts $ports Порты.
     * @param GameEconomyContext $context Контекст.
     * @param GameEconomyActor $actor Права актора.
     *
     * @return void
     */
    public function __construct(
        private readonly GameEconomyPorts $ports,
        private readonly GameEconomyContext $context,
        private readonly GameEconomyActor $actor,
    ) {
    }

    /**
     * Проецирует актуальный лист NPC и выбрасывает economy-конфликт.
     *
     * @param int $npcId NPC.
     * @param GameEconomyConflictException $exception Исходный конфликт.
     *
     * @return never Не возвращает управление.
     *
     * @throws GameNotFoundException Если NPC исчез.
     * @throws GameEconomyConflictException С проекцией листа.
     */
    public function npc(int $npcId, GameEconomyConflictException $exception): never
    {
        $npc = $this->ports->npcs->getById($npcId);
        $full = $this->context->viewAll || $this->actor->isStaff();
        $current = $exception->getCurrentSheet() ?? [
            'choices' => $npc->getVersion()['choices'] ?? [],
            'sheet' => $npc->getVersion()['sheet'] ?? [],
        ];
        $projected = $this->ports->conflictProjection->projectNpc(
            $current,
            $npc->getVisibility(),
            $full,
        );

        throw new GameEconomyConflictException(
            $exception->getCurrentVersion(),
            $exception->getMessage(),
            $exception,
            [
                'choices' => is_array($projected['choices'] ?? null) ? $projected['choices'] : [],
                'sheet' => is_array($projected['sheet'] ?? null) ? $projected['sheet'] : [],
            ],
        );
    }

    /**
     * Переводит stale Character в Game projection.
     *
     * @param int $characterId Персонаж.
     * @param CharacterConflictException $exception Исходный конфликт.
     *
     * @return GameEconomyConflictException Конфликт Game.
     *
     * @throws GameNotFoundException Если membership отсутствует.
     * @throws GameInvalidException Если snapshot битый.
     */
    public function character(
        int $characterId,
        CharacterConflictException $exception,
    ): GameEconomyConflictException {
        $membership = $this->ports->memberships->get($this->context->game->getId(), $characterId);
        $record = $this->ports->characters->get($characterId);
        $full = $this->context->viewAll
            || $this->actor->isStaff()
            || $membership->getCharacterOwnerId() === $this->context->actorUserId;
        $current = $exception->getCurrentSheet();
        if ($current === null) {
            $current = ['choices' => $record->getChoices(), 'sheet' => $record->getSheet()];
        }

        return new GameEconomyConflictException(
            $exception->getCurrentVersion(),
            'Game economy character version conflict',
            $exception,
            $this->ports->conflictProjection->projectGameCharacter(
                $current['choices'],
                $current['sheet'],
                $membership->getSectionVisibility(),
                $full,
            ),
        );
    }
}
