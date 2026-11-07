<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;

/**
 * Цена действия: cost-эффекты, пол ОД и итоговые траты.
 */
final class GameActionPerformPrice
{
    /**
     * Создаёт расчёт цены.
     *
     * @param GameActionPerformPorts $ports Порты сценария.
     *
     * @return void
     */
    public function __construct(private readonly GameActionPerformPorts $ports)
    {
    }

    /**
     * Применяет cost-эффекты и собирает итоговые траты.
     *
     * @param array<int, ResourceSpend> $spends Разрешённые траты.
     * @param CharacterRuleSlice $slice Срез правил.
     * @param array<string, mixed> $choices Выборы актора.
     * @param string $actionRuleCode Карточка.
     * @param string $actorKind Вид character или npc.
     * @param int $actorId Идентификатор актора.
     * @param array<string, mixed> $pending Карта ожидания.
     * @param bool $step Шаг процесса задан.
     *
     * @return array{
     *     pending: array<string, mixed>,
     *     spends: array<int, ResourceSpend>,
     *     amounts: array<string, int>
     * } Итог.
     */
    public function of(
        array $spends,
        CharacterRuleSlice $slice,
        array $choices,
        string $actionRuleCode,
        string $actorKind,
        int $actorId,
        array $pending,
        bool $step = false,
    ): array {
        $split = $this->splitSpends($spends);
        $declared = $this->ports->pendingCosts->declare(
            $slice,
            $choices,
            $actionRuleCode,
            $actorKind,
            $actorId,
            $pending,
            $split['amounts'],
            false,
        );
        $amounts = $this->amountsAfter($slice, $actionRuleCode, $declared['amounts'], $step);

        return [
            'pending' => $declared['pending'],
            'spends' => [...$split['kept'], ...$this->spendsOf($amounts)],
            'amounts' => $amounts,
        ];
    }

    /**
     * Суммы после карточного пола.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $actionRuleCode Карточка.
     * @param array<string, int> $amounts Суммы.
     * @param bool $step Шаг процесса задан.
     *
     * @return array<string, int> Суммы.
     */
    private function amountsAfter(
        CharacterRuleSlice $slice,
        string $actionRuleCode,
        array $amounts,
        bool $step,
    ): array {
        return $step
            ? $amounts
            : (new GameActionFloor())->amounts($slice, $actionRuleCode, $amounts, false);
    }

    /**
     * Делит разрешённые траты на целые суммы и неразобранные записи.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return array{amounts: array<string, int>, kept: array<int, ResourceSpend>} Итог.
     */
    private function splitSpends(array $spends): array
    {
        $amounts = [];
        $kept = [];
        foreach ($spends as $spend) {
            $native = $spend->getAmount()->toNative();
            if (!is_int($native)) {
                $kept[] = $spend;
                continue;
            }

            $code = $spend->getResourceCode();
            $amounts[$code] = ($amounts[$code] ?? 0) + $native;
        }

        return ['amounts' => $amounts, 'kept' => $kept];
    }

    /**
     * Собирает траты из итоговых целых сумм.
     *
     * @param array<string, int> $amounts Суммы.
     *
     * @return array<int, ResourceSpend> Траты.
     */
    private function spendsOf(array $amounts): array
    {
        $spends = [];
        foreach ($amounts as $code => $amount) {
            $spends[] = new ResourceSpend($code, new ScalarResourceValue($amount));
        }

        return $spends;
    }
}
