<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Character\Service\Resource\CharacterResourceStorage;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;

/**
 * Делит трату F1 и слот долга. Лист и бой не пишет.
 */
final class GameActionDebt
{
    /**
     * Ключ актора.
     *
     * @param string $kind Вид character или npc.
     * @param int $id Id.
     *
     * @return string Ключ карты.
     */
    public function key(string $kind, int $id): string
    {
        return $kind . ':' . $id;
    }

    /**
     * Вторая декларация при висящем долге — отказ.
     *
     * @param array<string, mixed> $debt Карта.
     * @param string $key Ключ актора.
     *
     * @return void
     */
    public function assertFree(array $debt, string $key): void
    {
        $slot = $debt[$key] ?? null;
        if (is_array($slot) && is_int($slot['remaining'] ?? null) && $slot['remaining'] > 0) {
            throw new GameInvalidException('Game action debt is open');
        }
    }

    /**
     * Код единственной карточки turn_refill или null.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return string|null Код.
     */
    public function refillCode(CharacterRuleSlice $slice): ?string
    {
        $matched = [];
        foreach ($slice->getLiveRules() as $rule) {
            $spec = $rule->isSpecBroken() ? null : $rule->getSpec();
            if ($spec instanceof ResourceSpec && $spec->isTurnRefill()) {
                $matched[] = $rule->getCode();
            }
        }

        if (count($matched) > 1) {
            throw new GameInvalidException('Game turn resource is invalid');
        }

        return $matched[0] ?? null;
    }

    /**
     * Платит min(current, need) по F1. Прочие траты не растягивает.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<int, ResourceSpend> $spends Траты.
     * @param string|null $refillCode Код пула конца хода.
     * @param array<string, mixed> $attack Тело declare.
     * @param string $lane Вид удара: один или много.
     *
     * @return array{spends: array<int, ResourceSpend>, open: bool, slot: array<string, mixed>|null}
     */
    public function plan(
        CharacterRuleSlice $slice,
        array $sheet,
        array $spends,
        ?string $refillCode,
        array $attack,
        string $lane,
    ): array {
        if ($refillCode === null) {
            return ['spends' => $spends, 'open' => true, 'slot' => null];
        }

        $need = 0;
        $others = [];
        foreach ($spends as $spend) {
            $native = $spend->getAmount()->toNative();
            if ($spend->getResourceCode() === $refillCode && is_int($native)) {
                $need += $native;
                continue;
            }

            $others[] = $spend;
        }

        $current = $this->current($slice, $sheet, $refillCode);
        if ($current >= $need) {
            return ['spends' => $spends, 'open' => true, 'slot' => null];
        }

        $paid = max(0, $current);
        $kept = $others;
        if ($paid > 0) {
            $kept[] = new ResourceSpend($refillCode, new ScalarResourceValue($paid));
        }

        return [
            'spends' => $kept,
            'open' => false,
            'slot' => [
                'remaining' => $need - $paid,
                'total' => $need,
                'actionRuleCode' => $attack['actionRuleCode'] ?? '',
                'lane' => $lane,
                'attack' => $attack,
                'resourceCode' => $refillCode,
            ],
        ];
    }

    /**
     * Снимает min(current, remaining). Нулевой current слот не трогает.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $slot Слот.
     *
     * @return array{spend: ResourceSpend|null, slot: array<string, mixed>, open: bool}
     */
    public function continuePay(CharacterRuleSlice $slice, array $sheet, array $slot): array
    {
        $code = $slot['resourceCode'] ?? null;
        $remaining = $slot['remaining'] ?? null;
        if (!is_string($code) || !is_int($remaining)) {
            throw new GameInvalidException('Game action debt is invalid');
        }

        $current = $this->current($slice, $sheet, $code);
        $paid = min(max(0, $current), $remaining);
        if ($paid === 0) {
            return ['spend' => null, 'slot' => $slot, 'open' => false];
        }

        $slot['remaining'] = $remaining - $paid;

        return [
            'spend' => new ResourceSpend($code, new ScalarResourceValue($paid)),
            'slot' => $slot,
            'open' => $slot['remaining'] === 0,
        ];
    }

    /**
     * @param array<string, mixed> $sheet Лист.
     */
    private function current(CharacterRuleSlice $slice, array $sheet, string $code): int
    {
        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            throw new GameInvalidException('Game action resources require backfill');
        }

        $typed = (new CharacterResourceStorage())->parseRows($rows, $slice);
        foreach ($typed as $row) {
            if ($row->getRuleCode() !== $code) {
                continue;
            }

            $native = $row->getCurrent()->toNative();

            return is_int($native) ? $native : 0;
        }

        return 0;
    }
}
