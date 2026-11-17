<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameActiveEffectNew;
use Mifrial\Roleplay\Game\Dto\GameActiveEffectRecord;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Dto\GameSpellCastPrepared;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Repository\GameActiveEffectRepository;

/**
 * Создаёт наложенный длительный эффект из завершённого каста.
 */
final class GameActiveEffectCreate
{
    /**
     * Создаёт сервис.
     *
     * @param GameActiveEffectRepository $effects Строки эффектов.
     * @param GameSpellDurationRules $durations Форма длительности.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActiveEffectRepository $effects,
        private readonly GameSpellDurationRules $durations,
    ) {
    }

    /**
     * Пишет строку, если каст создал длительный эффект.
     *
     * @param GameRecord $game Игра.
     * @param GameSpellCastPrepared $prepared Подготовка каста.
     * @param GameBattleRecord $battle Бой.
     * @param bool $castOk Проверка сотворения пройдена.
     * @param bool $milk Касание ушло в воздух.
     * @param array{type: string, id: int}|null $target Цель или null для себя.
     *
     * @return GameActiveEffectRecord|null Строка или null.
     *
     * @throws GameInvalidException Если поле битое.
     */
    public function fromCast(
        GameRecord $game,
        GameSpellCastPrepared $prepared,
        GameBattleRecord $battle,
        bool $castOk,
        bool $milk,
        ?array $target = null,
    ): ?GameActiveEffectRecord {
        if (!$castOk || $milk) {
            return null;
        }

        $duration = $prepared->spell->getSpell()->getDuration();
        if (!$this->durations->isLingering($duration)) {
            return null;
        }

        $target ??= $prepared->damageTarget === null
            ? ['type' => $prepared->casterKind, 'id' => $prepared->casterId]
            : ['type' => $prepared->damageTarget['type'], 'id' => $prepared->damageTarget['id']];
        $ticks = $this->durations->lingerTicks($duration);

        return $this->effects->add(new GameActiveEffectNew(
            $game->getId(),
            $battle->getSessionId(),
            $battle->getId(),
            'spell',
            $prepared->spellCode,
            $prepared->casterKind,
            $prepared->casterId,
            $target['type'],
            $target['id'],
            $duration->getType(),
            $duration->getUnit(),
            $ticks,
            $ticks,
            [
                'base' => $prepared->usedPower->getBase(),
                'size' => $prepared->usedPower->getSize(),
            ],
            [
                'pathCode' => $prepared->pathCode,
                'sourceKey' => $prepared->sourceKey,
                'parameterValues' => $prepared->parameters,
                'appliedUpgradeCodes' => $this->upgradeCodes($prepared),
            ],
            $battle->getRound(),
            $prepared->casterKind . ':' . $prepared->casterId,
        ));
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
