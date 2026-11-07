<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Жетон концентрации атакующего на удар.
 */
final class GameStrikeSpendToken
{
    /**
     * Создаёт расчёт жетона атаки.
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
     * Жетоны концентрации на удар.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы листа.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array{resource: string, amount: int, source: string, mode: string}|null Трата.
     *
     * @throws GameInvalidException Если жетон недоступен.
     */
    public function spend(
        GameRecord $game,
        array $choice,
        array $sheet,
        array $choices,
        CharacterRuleSlice $slice,
    ): ?array {
        $raw = $choice['tokenSpend'] ?? null;
        $amount = is_array($raw) ? ($raw['amount'] ?? 0) : $raw;
        if (!is_int($amount) || $amount < 1) {
            return null;
        }

        $resource = $this->concentration->resource($slice, $sheet);
        $checkCode = $this->rules->hitCheck->findHitCode($game->getSpaceId(), $game->getRulesRevision());
        $this->concentration->verify($slice, $sheet, $checkCode, $resource, $amount);
        $mode = is_array($raw) && is_string($raw['mode'] ?? null) ? $raw['mode'] : 'advantage';
        $card = $this->card($game, $choice, $sheet, $choices, $slice, $checkCode, $mode);

        return [
            'resource' => $resource,
            'amount' => $amount,
            'source' => is_array($card) && is_string($card['source'] ?? null) ? $card['source'] : $resource,
            'mode' => $mode,
        ];
    }

    /**
     * Узел карточки точности.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы листа.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $checkCode Карточка проверки.
     * @param string $mode Режим жетона.
     *
     * @return array<string, mixed>|null Узел token_spend.
     *
     * @throws GameInvalidException Если карточка точности не найдена.
     */
    private function card(
        GameRecord $game,
        array $choice,
        array $sheet,
        array $choices,
        CharacterRuleSlice $slice,
        string $checkCode,
        string $mode,
    ): ?array {
        if ($mode !== 'accuracy') {
            return null;
        }

        $card = $this->accuracyCard($game, $choice, $sheet, $choices, $slice, $checkCode);
        if ($card === null) {
            throw new GameInvalidException('Game concentration accuracy card is missing');
        }

        return $card;
    }

    /**
     * Карточка с точностью жетона и проверка мастерства оружия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы листа.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $checkCode Карточка проверки.
     *
     * @return array<string, mixed>|null Узел token_spend.
     *
     * @throws GameInvalidException Если мастерство ниже требования.
     */
    private function accuracyCard(
        GameRecord $game,
        array $choice,
        array $sheet,
        array $choices,
        CharacterRuleSlice $slice,
        string $checkCode,
    ): ?array {
        foreach ($slice->getLiveRules() as $rule) {
            $node = $this->accuracyNode($rule->getSpecDocument(), $checkCode);
            if ($node === null) {
                continue;
            }

            $this->assertMastery($game, $choice, $sheet, $choices, $node);

            return $node;
        }

        return null;
    }

    /**
     * Узел token_spend с точностью и нужной проверкой.
     *
     * @param array<string, mixed> $document Документ правила.
     * @param string $checkCode Карточка проверки.
     *
     * @return array<string, mixed>|null Узел.
     */
    private function accuracyNode(array $document, string $checkCode): ?array
    {
        $node = $document['token_spend'] ?? null;
        if (!is_array($node) || ($node['check'] ?? null) !== $checkCode) {
            return null;
        }

        $bonus = is_array($node['bonus'] ?? null) ? $node['bonus'] : null;
        if ($bonus === null || ($bonus['target'] ?? null) !== 'accuracy') {
            return null;
        }

        return $node;
    }

    /**
     * Проверяет минимальное мастерство оружия.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Выбор.
     * @param array<string, mixed> $sheet Лист.
     * @param array<string, mixed> $choices Выборы листа.
     * @param array<string, mixed> $node Узел token_spend.
     *
     * @return void
     *
     * @throws GameInvalidException Если мастерство ниже требования.
     */
    private function assertMastery(
        GameRecord $game,
        array $choice,
        array $sheet,
        array $choices,
        array $node,
    ): void {
        $minimum = is_array($node['min_mastery'] ?? null) ? $node['min_mastery'] : null;
        if ($minimum === null) {
            return;
        }

        $mastery = $this->rules->outcome->weaponMasteryOf(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $sheet,
            $choices,
            is_int($choice['itemInventoryId'] ?? null) ? $choice['itemInventoryId'] : 0,
            is_string($choice['itemRuleCode'] ?? null) ? $choice['itemRuleCode'] : '',
            is_string($choice['profileType'] ?? null) ? $choice['profileType'] : '',
        );
        if ($mastery->compare(new DimensionalNumber($minimum['base'] ?? 0, $minimum['size'] ?? 0)) < 0) {
            throw new GameInvalidException('Game concentration accuracy mastery is insufficient');
        }
    }
}
