<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Dto\GameActiveEffectRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Repository\GameActiveEffectRepository;
use Mifrial\Roleplay\Game\Repository\GameSessionRepository;

/**
 * Строки состояний цели из наложенных длительных эффектов.
 *
 * Состояние не пишется в лист: пока строка `game_active_effect` жива,
 * пересчёт листа получает её строку состояния.
 */
final class GameActiveEffectStates
{
    /**
     * Создаёт чтение состояний.
     *
     * @param GameSessionRepository $sessions Сессия игры.
     * @param GameActiveEffectRepository $effects Строки эффектов.
     * @param GameStrikeSlices $slices Срез ревизии.
     * @param ICharacterSheets $sheets Пересчёт листа.
     * @param GameSpellEffect $spellEffects Эффекты карточек.
     *
     * @return void
     */
    public function __construct(
        private readonly GameSessionRepository $sessions,
        private readonly GameActiveEffectRepository $effects,
        private readonly GameStrikeSlices $slices,
        private readonly ICharacterSheets $sheets,
        private readonly GameSpellEffect $spellEffects = new GameSpellEffect(),
    ) {
    }

    /**
     * Строки состояний цели текущей игры.
     *
     * @param GameRecord $game Игра.
     * @param string $kind Вид цели.
     * @param int $id Идентификатор цели.
     *
     * @return array<int, array<string, mixed>> Строки состояний.
     */
    public function rowsOf(GameRecord $game, string $kind, int $id): array
    {
        return $this->rows(
            $game->getId(),
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $kind,
            $id,
        );
    }

    /**
     * Строки состояний цели по миру и ревизии.
     *
     * @param int $gameId Игра.
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $kind Вид цели.
     * @param int $id Идентификатор цели.
     *
     * @return array<int, array<string, mixed>> Строки состояний.
     */
    public function rows(int $gameId, int $spaceId, int $rulesRevision, string $kind, int $id): array
    {
        $sessionId = $this->sessions->findSessionId($gameId);
        if ($sessionId === null) {
            return [];
        }

        $rows = [];
        foreach ($this->effects->findForSession($sessionId) as $effect) {
            if ($this->belongsTo($effect, $kind, $id)) {
                $rows[] = $effect;
            }
        }

        if ($rows === []) {
            return [];
        }

        return $this->spellEffects->runtimeStates(
            $this->slices->slice($spaceId, $rulesRevision),
            $rows,
        );
    }

    /**
     * Лист цели с состояниями из наложенных эффектов.
     *
     * @param int $gameId Игра.
     * @param int $spaceId Мир.
     * @param int $rulesRevision Ревизия.
     * @param string $kind Вид цели.
     * @param int $id Идентификатор цели.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы.
     *
     * @return array<string, mixed> Лист.
     */
    public function apply(
        int $gameId,
        int $spaceId,
        int $rulesRevision,
        string $kind,
        int $id,
        array $sheet,
        array $choices,
    ): array {
        $rows = $this->rows($gameId, $spaceId, $rulesRevision, $kind, $id);
        if ($rows === []) {
            return $sheet;
        }

        return $this->sheets->compute(
            $this->slices->slice($spaceId, $rulesRevision),
            $choices,
            $sheet,
            [],
            $rows,
        );
    }

    /**
     * Эффект наложен на эту цель.
     *
     * @param GameActiveEffectRecord $effect Строка эффекта.
     * @param string $kind Вид цели.
     * @param int $id Идентификатор цели.
     *
     * @return bool true, если цель совпала.
     */
    private function belongsTo(GameActiveEffectRecord $effect, string $kind, int $id): bool
    {
        return $effect->getTargetKind() === $kind && $effect->getTargetId() === $id;
    }
}
