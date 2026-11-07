<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Жетон концентрации защиты удара.
 */
final class GameStrikeSpendDefender
{
    /**
     * Создаёт расчёт жетона защиты.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSpendConcentration $concentration Жетоны концентрации.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSpendConcentration $concentration,
    ) {
    }

    /**
     * Жетон концентрации защиты удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $sheet Лист цели.
     * @param string $hitCode Карточка проверки попадания.
     * @param string $actorKey Ключ цели.
     * @param array<string, mixed> $cycle Цикл боя.
     *
     * @return array{spend: ResourceSpend, advantage: RollAdvantage, cycle: array<string, mixed>}|null Трата.
     *
     * @throws GameInvalidException Если жетон недоступен.
     */
    public function defenderToken(
        GameRecord $game,
        array $choice,
        array $sheet,
        string $hitCode,
        string $actorKey,
        array $cycle,
    ): ?array {
        $amount = $choice['spendConcentration'] ?? 0;
        if (!is_int($amount) || $amount < 1) {
            return null;
        }

        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $resource = $this->concentration->resource($slice, $sheet);
        $this->concentration->verify($slice, $sheet, $hitCode, $resource, $amount);

        return [
            'spend' => new ResourceSpend($resource, new ScalarResourceValue($amount)),
            'advantage' => new RollAdvantage($resource, $amount, $slice->findLive($resource)?->getName()),
            'cycle' => (new GameConcentrationCycle())->markUsed($cycle, $actorKey),
        ];
    }
}
