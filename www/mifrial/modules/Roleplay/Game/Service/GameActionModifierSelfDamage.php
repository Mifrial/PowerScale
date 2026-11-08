<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\SelfDamageOutcomeEffect;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Урон по себе от модификатора: сильнейшая покрытая строка или каждая.
 */
final class GameActionModifierSelfDamage
{
    /**
     * Создаёт исполнитель урона по себе.
     *
     * @param GameActionModifierPorts $ports Правила, листы и запись.
     *
     * @return void
     */
    public function __construct(private readonly GameActionModifierPorts $ports)
    {
    }

    /**
     * Применяет урон по себе к покрытым строкам.
     *
     * @param GameActionModifierInput $input Снимок действия.
     * @param SelfDamageOutcomeEffect $effect Последствие.
     * @param list<array<string, mixed>> $covered Покрытые строки.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     *
     * @throws GameInvalidException Если строка, лист или последствие битые.
     */
    public function apply(
        GameActionModifierInput $input,
        SelfDamageOutcomeEffect $effect,
        array $covered,
        array &$result,
    ): void {
        if ($covered === []) {
            throw new GameInvalidException('Game action modifier has no covered strike');
        }

        $rows = $effect->getMode() === SelfDamageOutcomeEffect::MODE_EACH
            ? $this->strengths($input, $covered)
            : [$this->strongest($input, $covered)];
        foreach ($rows as $row) {
            $this->write($input, $row, $effect, $result);
        }
    }

    /**
     * Сила всех покрытых строк в порядке объявления.
     *
     * @param GameActionModifierInput $input Снимок действия.
     * @param list<array<string, mixed>> $covered Покрытые строки.
     *
     * @return list<array{0: array<string, mixed>, 1: CharacteristicNumber}> Строки и сила.
     */
    private function strengths(GameActionModifierInput $input, array $covered): array
    {
        $rows = [];
        foreach ($covered as $row) {
            $rows[] = [$row, $this->strengthOf($input, $row)];
        }

        return $rows;
    }

    /**
     * Самая сильная покрытая строка; при равенстве — первая.
     *
     * @param GameActionModifierInput $input Снимок действия.
     * @param list<array<string, mixed>> $covered Покрытые строки.
     *
     * @return array{0: array<string, mixed>, 1: CharacteristicNumber} Строка и сила.
     */
    private function strongest(GameActionModifierInput $input, array $covered): array
    {
        $best = $covered[0];
        $bestStrength = $this->strengthOf($input, $best);
        foreach ($covered as $row) {
            $strength = $this->strengthOf($input, $row);
            if ($strength->characteristicCompare($bestStrength) <= 0) {
                continue;
            }

            $best = $row;
            $bestStrength = $strength;
        }

        return [$best, $bestStrength];
    }

    /**
     * Считает урон, пишет лист и итог одной строки.
     *
     * @param GameActionModifierInput $input Снимок действия.
     * @param array{0: array<string, mixed>, 1: CharacteristicNumber} $row Строка и сила.
     * @param SelfDamageOutcomeEffect $effect Последствие.
     * @param array<string, mixed> $result Итог.
     *
     * @return void
     */
    private function write(
        GameActionModifierInput $input,
        array $row,
        SelfDamageOutcomeEffect $effect,
        array &$result,
    ): void {
        $injury = $this->injuryOf($row[1], $effect);
        $prepared = $this->ports->resolution->damage->selfDamage(
            $input->game,
            $input->attackerKind,
            $input->attackerId,
            $injury,
            $effect->getDamageTypeCode(),
            1,
            $effect->isIgnoreDefense(),
            $effect->isInternal(),
        );
        $view = $this->ports->amounts->view($prepared['injury']);
        $result['sheetVersion'] = $this->ports->sheetWrites->write(
            $input->game,
            $input->attackerKind,
            $input->attackerId,
            $prepared['actualVersion'],
            $prepared['operations'],
        );
        $result['injury'] = $view;
        $result['consequence'] = $prepared['consequence'];
        $result['injuries'][] = ['injury' => $view, 'consequence' => $prepared['consequence']];
    }

    /**
     * Урон по себе: сила удара, сдвинутая на размер.
     *
     * @param CharacteristicNumber $strength Сила удара.
     * @param SelfDamageOutcomeEffect $effect Последствие.
     *
     * @return DimensionalNumber Повреждение.
     */
    private function injuryOf(CharacteristicNumber $strength, SelfDamageOutcomeEffect $effect): DimensionalNumber
    {
        $shift = $effect->getSizeDelta();

        return $shift === 0 ? $strength : $strength->modify($shift * CharacteristicNumber::BASE_STEP);
    }

    /**
     * Сила удара покрытой строки.
     *
     * @param GameActionModifierInput $input Снимок действия.
     * @param array<string, mixed> $row Строка удара.
     *
     * @return CharacteristicNumber Сила.
     *
     * @throws GameInvalidException Если строка или профиль битые.
     */
    private function strengthOf(GameActionModifierInput $input, array $row): CharacteristicNumber
    {
        return $this->ports->rules->damage->strikeStrength($this->target($input, $row));
    }

    /**
     * Запрос удара по строке.
     *
     * @param GameActionModifierInput $input Снимок действия.
     * @param array<string, mixed> $row Строка удара.
     *
     * @return GameStrikeTarget Запрос.
     *
     * @throws GameInvalidException Если строка или лист битые.
     */
    private function target(GameActionModifierInput $input, array $row): GameStrikeTarget
    {
        $fields = $this->fields($input, $row);
        $context = $this->ports->context;
        $slice = $this->ports->rules->data->ruleSlice($input->game->getSpaceId(), $input->game->getRulesRevision());
        $attackContext = $context->attackFilterContext($input->game, $fields);
        $occupyHands = $attackContext['occupyHands'] ?? null;
        $grip = is_int($occupyHands) ? $this->ports->rules->equipment->gripBonus($occupyHands) : 0;
        $npc = $input->attackerKind === 'npc';

        return GameStrikeTarget::of(
            $input->game->getSpaceId(),
            $input->game->getRulesRevision(),
            $fields['itemRuleCode'],
            $fields['profileType'],
            $fields['profileIndex'],
            $fields['inventoryId'],
            $input->attackerKind,
            $input->attackerId,
            $npc ? $this->ports->sheets->getNpcSheet($input->game->getId(), $input->attackerId) : null,
            $npc ? $this->ports->sheets->getNpcChoices($input->game->getId(), $input->attackerId) : null,
            $npc ? $this->ports->sheets->getNpcVersion($input->game->getId(), $input->attackerId) : null,
            $context->attackCharacteristicModifier($input->game, $fields, $slice, 1, null),
            0,
            $grip,
        );
    }

    /**
     * Поля атакующего из строки удара.
     *
     * @param GameActionModifierInput $input Снимок действия.
     * @param array<string, mixed> $row Строка удара.
     *
     * @return array{kind: string, attackerId: int, itemRuleCode: string, profileType: string,
     *     profileIndex: int, inventoryId: int, actionRuleCode: string} Поля.
     *
     * @throws GameInvalidException Если строка битая.
     */
    private function fields(GameActionModifierInput $input, array $row): array
    {
        $itemRuleCode = $row['item_rule_code'] ?? null;
        $profileType = $row['profile_type'] ?? null;
        $profileIndex = $row['profile_index'] ?? null;
        $itemInventoryId = $row['item_inventory_id'] ?? null;
        if (
            !is_string($itemRuleCode)
            || !is_string($profileType)
            || !is_int($profileIndex)
            || !is_int($itemInventoryId)
        ) {
            throw new GameInvalidException('Game strike row is invalid');
        }

        return [
            'kind' => $input->attackerKind,
            'attackerId' => $input->attackerId,
            'itemRuleCode' => $itemRuleCode,
            'profileType' => $profileType,
            'profileIndex' => $profileIndex,
            'inventoryId' => $itemInventoryId,
            'actionRuleCode' => $input->actionRuleCode,
        ];
    }
}
