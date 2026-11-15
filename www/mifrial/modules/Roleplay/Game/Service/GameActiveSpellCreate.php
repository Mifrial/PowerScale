<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameActiveSpellNew;
use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;

/**
 * Создаёт поддерживаемое заклинание из завершённого каста.
 */
final class GameActiveSpellCreate
{
    /**
     * Создаёт сервис.
     *
     * @param GameActiveSpellRepository $spells Строки поддержания.
     * @param GameActiveSpellGuard $guard Занятость источника.
     * @param GameSpellDurationRules $durations Форма длительности.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActiveSpellRepository $spells,
        private readonly GameActiveSpellGuard $guard,
        private readonly GameSpellDurationRules $durations,
    ) {
    }

    /**
     * Пишет строку, если каст создал поддерживаемый эффект.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка каста.
     * @param GameBattleRecord $battle Бой.
     * @param bool $castOk Проверка сотворения пройдена.
     * @param bool $milk Касание ушло в воздух.
     * @param int $casterSheetVersion Версия листа после записи каста.
     *
     * @return GameActiveSpellRecord|null Строка или null.
     *
     * @throws GameInvalidException Если источник занят или поле битое.
     * @throws GameNotFoundException Если строки нет после вставки.
     */
    public function fromCast(
        GameRecord $game,
        GameSpellCastPrepared $prepared,
        GameBattleRecord $battle,
        bool $castOk,
        bool $milk,
        int $casterSheetVersion,
    ): ?GameActiveSpellRecord {
        if (!$castOk || $milk) {
            return null;
        }

        $duration = $prepared->spell->getSpell()->getDuration();
        if (!$this->durations->isSustained($duration)) {
            return null;
        }

        $this->guard->assertSourceFree(
            $battle->getId(),
            $prepared->casterKind,
            $prepared->casterId,
            $prepared->sourceKey,
        );

        return $this->spells->add(new GameActiveSpellNew(
            $game->getId(),
            $battle->getSessionId(),
            $battle->getId(),
            $prepared->casterKind,
            $prepared->casterId,
            $prepared->spellCode,
            $prepared->pathCode,
            $prepared->sourceKey,
            $duration->getType(),
            $this->pair($prepared),
            $this->pair($prepared),
            $prepared->parameters,
            $this->upgradeCodes($prepared),
            $battle->getRound(),
            $prepared->casterKind . ':' . $prepared->casterId,
            $casterSheetVersion,
        ));
    }

    /**
     * Размерная пара мощи.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return array{base: int, size: int} Пара.
     */
    private function pair(GameSpellCastPrepared $prepared): array
    {
        return [
            'base' => $prepared->usedPower->getBase(),
            'size' => $prepared->usedPower->getSize(),
        ];
    }

    /**
     * Коды применённых улучшений.
     *
     * @param GameSpellCastPrepared $prepared Подготовка.
     *
     * @return array<int, string> Коды.
     */
    private function upgradeCodes(GameSpellCastPrepared $prepared): array
    {
        $codes = [];
        foreach ($prepared->upgrades->getApplied() as $upgrade) {
            $codes[] = $upgrade->getRuleCode();
        }

        return $codes;
    }
}
