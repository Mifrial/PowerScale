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
     *
     * @throws GameInvalidException Если долг ещё открыт.
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
     *
     * @throws GameInvalidException Если карточек больше одной.
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
     * @param string $kind Вид объявленного действия.
     * @param array<string, mixed> $payload Объявление.
     * @param string|null $lane Полоса удара.
     *
     * @return array{spends: array<int, ResourceSpend>, open: bool, slot: array<string, mixed>|null}
     *
     * @throws GameInvalidException Если лист не содержит ресурсов.
     */
    public function plan(
        CharacterRuleSlice $slice,
        array $sheet,
        array $spends,
        ?string $refillCode,
        string $kind,
        array $payload,
        ?string $lane = null,
    ): array {
        if ($refillCode === null) {
            return ['spends' => $spends, 'open' => true, 'slot' => null];
        }

        $split = $this->split($spends, $refillCode);
        $current = $this->current($slice, $sheet, $refillCode);
        if ($current >= $split['need']) {
            return ['spends' => $spends, 'open' => true, 'slot' => null];
        }

        return $this->openSlot($refillCode, $kind, $payload, $lane, $split, $current);
    }

    /**
     * Вид слота с совместимостью старых слотов удара.
     *
     * @param array<string, mixed> $slot Слот.
     *
     * @return string strike, action или wound.
     */
    public function kindOf(array $slot): string
    {
        $kind = $slot['kind'] ?? null;

        return is_string($kind) && $kind !== '' ? $kind : 'strike';
    }

    /**
     * Объявление слота с совместимостью старых слотов удара.
     *
     * @param array<string, mixed> $slot Слот.
     *
     * @return array<string, mixed>|null Объявление или null.
     */
    public function payloadOf(array $slot): ?array
    {
        $payload = $slot['payload'] ?? $slot['attack'] ?? null;

        return is_array($payload) ? $payload : null;
    }

    /**
     * Снимает min(current, remaining). Нулевой current слот не трогает.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $slot Слот.
     *
     * @return array{spend: ResourceSpend|null, slot: array<string, mixed>, open: bool}
     *
     * @throws GameInvalidException Если слот битый или лист без ресурсов.
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
     * Текущий запас пула по листу.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код ресурса.
     *
     * @return int Запас.
     *
     * @throws GameInvalidException Если лист без ресурсов.
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

    /**
     * Делит траты на пул F1 и остальные.
     *
     * @param array<int, ResourceSpend> $spends Траты.
     * @param string $refillCode Код пула.
     *
     * @return array{need: int, others: array<int, ResourceSpend>} Части.
     */
    private function split(array $spends, string $refillCode): array
    {
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

        return ['need' => $need, 'others' => $others];
    }

    /**
     * Собирает слот долга и оплаченную часть.
     *
     * @param string $refillCode Код пула.
     * @param string $kind Вид объявленного действия.
     * @param array<string, mixed> $payload Объявление.
     * @param string|null $lane Полоса удара.
     * @param array{need: int, others: array<int, ResourceSpend>} $split Части траты.
     * @param int $current Текущий запас.
     *
     * @return array{spends: array<int, ResourceSpend>, open: bool, slot: array<string, mixed>} Итог.
     */
    private function openSlot(
        string $refillCode,
        string $kind,
        array $payload,
        ?string $lane,
        array $split,
        int $current,
    ): array {
        $paid = max(0, $current);
        $kept = $split['others'];
        if ($paid > 0) {
            $kept[] = new ResourceSpend($refillCode, new ScalarResourceValue($paid));
        }

        $slot = $this->slotOf($refillCode, $kind, $payload, $lane, $split['need'], $paid);

        return ['spends' => $kept, 'open' => false, 'slot' => $slot];
    }

    /**
     * Поля слота долга.
     *
     * @param string $refillCode Код пула.
     * @param string $kind Вид объявленного действия.
     * @param array<string, mixed> $payload Объявление.
     * @param string|null $lane Полоса удара.
     * @param int $need Полная цена.
     * @param int $paid Оплачено.
     *
     * @return array<string, mixed> Слот.
     */
    private function slotOf(
        string $refillCode,
        string $kind,
        array $payload,
        ?string $lane,
        int $need,
        int $paid,
    ): array {
        $slot = [
            'remaining' => $need - $paid,
            'total' => $need,
            'kind' => $kind,
            'payload' => $payload,
            'actionRuleCode' => is_string($payload['actionRuleCode'] ?? null) ? $payload['actionRuleCode'] : '',
            'resourceCode' => $refillCode,
        ];
        if ($lane !== null) {
            $slot['lane'] = $lane;
        }

        if ($kind === 'strike') {
            $slot['attack'] = $payload;
        }

        return $slot;
    }
}
