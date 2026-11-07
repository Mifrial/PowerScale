<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeWideRun;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Срез, бросок атаки и списания широкого удара.
 */
final class GameStrikeWideOpening
{
    /**
     * Создаёт начало прогона.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeDeclaredSpend $declaredSpend Списание заявок.
     * @param GameStrikeSupport $support Поддержки строки.
     * @param GameStrikeTargetClose $targets Расчёт цели.
     * @param GameStrikeWideResume $resume Возобновление строк.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeDeclaredSpend $declaredSpend,
        private readonly GameStrikeSupport $support,
        private readonly GameStrikeTargetClose $targets,
        private readonly GameStrikeWideResume $resume,
    ) {
    }

    /**
     * Готовит срез, списания и общий бросок атаки.
     *
     * @param GameStrikeWideRun $run Контекст прогона.
     * @param array<string, mixed> $open Открытый удар.
     * @param list<array<string, mixed>> $rows Строки.
     * @param list<string> $targetKeys Ключи целей.
     *
     * @return array{slice: \Mifrial\Roleplay\Character\Dto\CharacterRuleSlice, hitCode: string,
     *               rows: list<array<string, mixed>>, attackerRoll: array<string, mixed>} Начало.
     *
     * @throws \Mifrial\Roleplay\Game\Exception\GameInvalidException Если срез или бросок битые.
     */
    public function open(GameStrikeWideRun $run, array $open, array $rows, array $targetKeys): array
    {
        $slice = $this->rules->data->ruleSlice($run->game->getSpaceId(), $run->game->getRulesRevision());
        $hitCode = $this->rules->hitCheck->findHitCode($run->game->getSpaceId(), $run->game->getRulesRevision());
        $rows = $this->declaredSpend->spend($run->game, $slice, $rows);
        $attackerRoll = $this->resume->attackerRoll($rows, $run->resume) ?? $this->targets->rolls->rollOf(
            $run->game,
            $open,
            $run->battle,
            $slice,
            count($rows),
            $targetKeys,
            $this->supports($rows),
        );

        return [
            'slice' => $slice,
            'hitCode' => $hitCode,
            'rows' => $rows,
            'attackerRoll' => $attackerRoll,
        ];
    }

    /**
     * Преимущества атакующего из поддержек всех строк.
     *
     * @param list<array<string, mixed>> $rows Строки.
     *
     * @return array<int, RollAdvantage> Преимущества.
     */
    private function supports(array $rows): array
    {
        $supports = [];
        foreach ($rows as $row) {
            array_push($supports, ...$this->support->advantages($row)['attacker']);
        }

        return $supports;
    }
}
