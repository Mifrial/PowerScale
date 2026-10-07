<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Mechanic\Service\Handler;

use Mifrial\Roleplay\Mechanic\Constant\PurchaseSurchargeEvent;
use Mifrial\Roleplay\Mechanic\Dto\CharacterMechanicContext;
use Mifrial\Roleplay\Mechanic\Dto\PurchaseSurchargePayload;
use Mifrial\Roleplay\Mechanic\Interface\IMechanicHandler;
use Mifrial\Roleplay\Mechanic\Interface\MechanicPayload;

/**
 * Прогрессивная доплата: каждая способность сверх freeCount доплачивает surcharge ОС.
 */
final class PurchaseSurchargeHandler implements IMechanicHandler
{
    /**
     * Код семейства.
     *
     * @return string Код purchase_surcharge.
     */
    public function getCode(): string
    {
        return 'purchase_surcharge';
    }

    /**
     * Поставка контракта.
     *
     * @return string Версия 1.0.0.
     */
    public function getVersion(): string
    {
        return '1.0.0';
    }

    /**
     * Подписка на шаг «Основа» с приоритетом 0.
     *
     * @return array<string, int> Карта.
     */
    public function getSubscriptions(): array
    {
        return [PurchaseSurchargeEvent::NAME => 0];
    }

    /**
     * Пишет доплату в аккумуляторы контекста, если payload задан и контекст — шаг персонажа.
     *
     * @param MechanicPayload|null $payload Payload доплаты.
     * @param object $context Контекст события.
     * @param string $event Имя события. Чужое событие не начисляет доплату.
     *
     * @return void
     */
    public function run(?MechanicPayload $payload, object $context, string $event): void
    {
        if ($event !== PurchaseSurchargeEvent::NAME) {
            return;
        }

        if (!$context instanceof CharacterMechanicContext || !$payload instanceof PurchaseSurchargePayload) {
            return;
        }

        $matchedCodes = $this->collectMatchedCodes($payload, $context);
        $this->applySurcharge($payload, $context, $matchedCodes);
    }

    /**
     * Коды способностей с уровнем не ниже 1, попавшие в фильтр, в порядке уровней.
     *
     * @param PurchaseSurchargePayload $payload Фильтр и порог.
     * @param CharacterMechanicContext $context Снимок шага.
     *
     * @return array<int, string> Коды.
     */
    private function collectMatchedCodes(PurchaseSurchargePayload $payload, CharacterMechanicContext $context): array
    {
        $matchedCodes = [];
        foreach ($context->getState()->getAbilityLevels() as $code => $level) {
            if ($level < 1 || !$this->matches($payload, $context, $code)) {
                continue;
            }

            $matchedCodes[] = $code;
        }

        return $matchedCodes;
    }

    /**
     * Способность подходит по признаку или по списку расы.
     *
     * @param PurchaseSurchargePayload $payload Фильтр.
     * @param CharacterMechanicContext $context Снимок шага.
     * @param string $code Код способности.
     *
     * @return bool true, если фильтр сработал.
     */
    private function matches(PurchaseSurchargePayload $payload, CharacterMechanicContext $context, string $code): bool
    {
        return $this->matchesKeyword($payload, $context, $code) || $this->matchesRace($payload, $context, $code);
    }

    /**
     * Признак способности совпал с keywordCode.
     *
     * @param PurchaseSurchargePayload $payload Фильтр.
     * @param CharacterMechanicContext $context Снимок шага.
     * @param string $code Код способности.
     *
     * @return bool true, если фильтр по признаку задан и признак есть.
     */
    private function matchesKeyword(
        PurchaseSurchargePayload $payload,
        CharacterMechanicContext $context,
        string $code,
    ): bool {
        $keywordCode = $payload->getKeywordCode();
        if ($keywordCode === null) {
            return false;
        }

        $keywords = $context->getState()->getAbilityKeywords()[$code] ?? [];

        return in_array($keywordCode, $keywords, true);
    }

    /**
     * Код способности есть среди расовых. Значение raceCode не сравнивается.
     *
     * @param PurchaseSurchargePayload $payload Фильтр.
     * @param CharacterMechanicContext $context Снимок шага.
     * @param string $code Код способности.
     *
     * @return bool true, если фильтр по расе задан и код в списке.
     */
    private function matchesRace(
        PurchaseSurchargePayload $payload,
        CharacterMechanicContext $context,
        string $code,
    ): bool {
        if ($payload->getRaceCode() === null) {
            return false;
        }

        return in_array($code, $context->getState()->getRacialAbilityCodes(), true);
    }

    /**
     * Начисляет доплату за хвост совпадений после freeCount.
     *
     * @param PurchaseSurchargePayload $payload Порог и размер.
     * @param CharacterMechanicContext $context Аккумуляторы шага.
     * @param array<int, string> $matchedCodes Совпавшие коды по порядку.
     *
     * @return void
     */
    private function applySurcharge(
        PurchaseSurchargePayload $payload,
        CharacterMechanicContext $context,
        array $matchedCodes,
    ): void {
        $surchargedCount = max(0, count($matchedCodes) - $payload->getFreeCount());
        if ($surchargedCount === 0) {
            return;
        }

        $chargedCodes = array_slice($matchedCodes, $payload->getFreeCount());
        foreach ($chargedCodes as $abilityCode) {
            $context->addSurcharge($abilityCode, $payload->getSurcharge());
        }
    }
}
