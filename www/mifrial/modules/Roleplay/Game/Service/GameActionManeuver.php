<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityWithBase;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeManeuver;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\StrikeManeuverEffect;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Манёвр после удара: проверка прошлой атаки и эффект для её цели.
 *
 * Манёвр остаётся обычным действием: цену и запись листа делает общий
 * сценарий действия. Здесь только условие «сразу после удара», проверка
 * мастерства оружия удара и запись типизированных эффектов.
 */
final class GameActionManeuver
{
    /**
     * Создаёт исполнитель манёвра.
     *
     * @param GameStrikeRules $rules Срез и расчётные роли.
     * @param GameStrikeSheets $sheets Снимки листов.
     * @param GameStrikeSheetWrites $sheetWrites Запись листа.
     * @param GameStateIncreaseChecks $stateChecks Проверки прироста.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSheetWrites $sheetWrites,
        private readonly GameStateIncreaseChecks $stateChecks,
    ) {
    }

    /**
     * Карточка объявляет манёвр после удара.
     *
     * @param GameRecord $game Игра.
     * @param string|null $ruleCode Карточка.
     *
     * @return bool Истина, если у карточки есть узел strike_maneuver.
     */
    public function isManeuver(GameRecord $game, ?string $ruleCode): bool
    {
        return $ruleCode !== null && $this->specOf($game, $ruleCode) !== null;
    }

    /**
     * Проверяет манёвр до списания цены.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход действия.
     * @param array<string, mixed> $pending Ожидание боя до действия.
     *
     * @return void
     *
     * @throws GameInvalidException Если прошлый удар, мастерство или цель не подходят.
     */
    public function assert(GameRecord $game, array $input, array $pending): void
    {
        $this->resolve($game, $input, $pending);
    }

    /**
     * Применяет манёвр к цели прошлого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход действия.
     * @param array<string, mixed> $pending Ожидание боя до действия.
     *
     * @return array<string, mixed> Применённый эффект.
     *
     * @throws GameInvalidException Если прошлый удар, мастерство или цель не подходят.
     */
    public function apply(GameRecord $game, array $input, array $pending): array
    {
        $resolved = $this->resolve($game, $input, $pending);

        return $this->write($game, $resolved['targetKey'], $resolved['selected']);
    }

    /**
     * Проверяет условия и выбирает эффекты.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $input Вход действия.
     * @param array<string, mixed> $pending Ожидание боя.
     *
     * @return array{
     *     targetKey: string,
     *     selected: array{
     *         ruleCode: string,
     *         boosted: bool,
     *         effects: list<array{kind: string, state: string, amount: int}>
     *     }
     * } Разбор.
     *
     * @throws GameInvalidException Если прошлый удар, мастерство или цель не подходят.
     */
    private function resolve(GameRecord $game, array $input, array $pending): array
    {
        $ruleCode = $input['actionRuleCode'] ?? null;
        $actor = $input['actor'] ?? null;
        if (!is_string($ruleCode) || !is_array($actor)) {
            throw new GameInvalidException('Game strike maneuver is invalid');
        }

        $spec = $this->specOf($game, $ruleCode);
        if ($spec === null) {
            throw new GameInvalidException('Game strike maneuver card is invalid');
        }

        $actorKind = is_string($actor['type'] ?? null) ? $actor['type'] : '';
        $actorId = is_int($actor['id'] ?? null) ? $actor['id'] : 0;
        $targetKey = $input['targetKey'] ?? null;
        if (!is_string($targetKey) || $targetKey === '') {
            throw new GameInvalidException('Game strike maneuver target is missing');
        }

        $hit = $this->hitOf($pending, $actorKind, $actorId, $targetKey);
        if (($hit['profileType'] ?? null) !== 'strike') {
            throw new GameInvalidException('Game strike maneuver requires a melee strike');
        }

        $snapshot = $this->sheets->snapshot($game, $actorKind, $actorId);
        $sheet = is_array($snapshot['sheet']['sheet'] ?? null) ? $snapshot['sheet']['sheet'] : [];
        $choices = is_array($snapshot['sheet']['choices'] ?? null) ? $snapshot['sheet']['choices'] : [];
        $mastery = $this->masteryOf($game, $hit, $sheet, $choices);
        $this->assertMastery($spec, $mastery);

        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $levels = $this->sheets->abilityLevels($game, $actorKind, $actorId);
        $boost = (new GameStrikeManeuverOptions())->boostOf($slice, $levels, $ruleCode, $mastery);
        $selected = (new GameStrikeManeuverResolution())->select([
            'ruleCode' => $ruleCode,
            'minSuccess' => $spec->getMinSuccess(),
            'effects' => GameStrikeManeuverOptions::effectsOf($spec->getEffects()),
            'boost' => $boost,
        ], is_int($hit['attackSr'] ?? null) ? $hit['attackSr'] : 0);
        if ($selected === null) {
            throw new GameInvalidException('Game strike maneuver requires a stronger previous strike');
        }

        $selected['effects'] = $this->merge($selected['effects']);

        return ['targetKey' => $targetKey, 'selected' => $selected];
    }

    /**
     * Снимок узла манёвра живой карточки.
     *
     * @param GameRecord $game Игра.
     * @param string $ruleCode Карточка.
     *
     * @return StrikeManeuver|null Узел или null.
     */
    private function specOf(GameRecord $game, string $ruleCode): ?StrikeManeuver
    {
        if ($ruleCode === '') {
            return null;
        }

        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $rule = $slice->findLive($ruleCode);
        if ($rule === null || $rule->isSpecBroken()) {
            return null;
        }

        $spec = $rule->getSpec();

        return $spec instanceof AbilityWithBase
            ? $spec->getBase()->getStrikeManeuver()
            : null;
    }

    /**
     * Мастерство оружия прошлого удара.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $hit Попадание.
     * @param array<string, mixed> $sheet Лист актора.
     * @param array<string, mixed> $choices Выборы листа.
     *
     * @return DimensionalNumber|null Мастерство или null.
     */
    private function masteryOf(GameRecord $game, array $hit, array $sheet, array $choices): ?DimensionalNumber
    {
        $inventoryId = $hit['itemInventoryId'] ?? null;
        $itemCode = $hit['itemRuleCode'] ?? null;
        $profile = $hit['profileType'] ?? null;
        if (!is_int($inventoryId) || !is_string($itemCode) || $itemCode === '' || !is_string($profile)) {
            return null;
        }

        try {
            return $this->rules->outcome->weaponMasteryOf(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $sheet,
                $choices,
                $inventoryId,
                $itemCode,
                $profile,
            );
        } catch (GameInvalidException) {
            return null;
        }
    }

    /**
     * Мастерство прошлого удара не ниже требования карточки.
     *
     * @param StrikeManeuver $spec Манёвр.
     * @param DimensionalNumber|null $mastery Мастерство или null.
     *
     * @return void
     *
     * @throws GameInvalidException Если мастерство ниже требования.
     */
    private function assertMastery(StrikeManeuver $spec, ?DimensionalNumber $mastery): void
    {
        $required = $spec->getMinWeaponMastery();
        if ($required === null) {
            return;
        }

        if ($mastery === null || $mastery->compare(new DimensionalNumber($required, 0)) < 0) {
            throw new GameInvalidException('Game strike maneuver requires higher weapon mastery');
        }
    }

    /**
     * Складывает одинаковые эффекты.
     *
     * @param list<array{kind: string, state: string, amount: int}> $effects Эффекты.
     *
     * @return list<array{kind: string, state: string, amount: int}> Свёрнутые эффекты.
     */
    private function merge(array $effects): array
    {
        $merged = [];
        foreach ($effects as $effect) {
            $key = $effect['kind'] . ':' . $effect['state'];
            if (isset($merged[$key])) {
                $merged[$key]['amount'] += $effect['amount'];
                continue;
            }

            $merged[$key] = $effect;
        }

        return array_values($merged);
    }

    /**
     * Попадание прошлого удара по цели.
     *
     * @param array<string, mixed> $pending Ожидание боя.
     * @param string $actorKind Вид актора.
     * @param int $actorId Id актора.
     * @param string $targetKey Цель.
     *
     * @return array<string, mixed> Попадание.
     *
     * @throws GameInvalidException Если прошлого удара или цели нет.
     */
    private function hitOf(array $pending, string $actorKind, int $actorId, string $targetKey): array
    {
        $snapshot = (new GameLastStrikeSnapshot())->snapshotOf($pending, $actorKind, $actorId);
        if ($snapshot === null) {
            throw new GameInvalidException('Game strike maneuver requires a previous strike');
        }

        foreach ((new GameLastStrikeStore())->hitsOf($snapshot) as $hit) {
            if (($hit['targetKey'] ?? null) === $targetKey) {
                return $hit;
            }
        }

        throw new GameInvalidException('Game strike maneuver target is invalid');
    }

    /**
     * Записывает состояния цели и возвращает данные для чата.
     *
     * @param GameRecord $game Игра.
     * @param string $targetKey Цель.
     * @param array{
     *     ruleCode: string,
     *     boosted: bool,
     *     effects: list<array{kind: string, state: string, amount: int}>
     * } $selected Выбранные эффекты.
     *
     * @return array<string, mixed> Применённый эффект.
     *
     * @throws GameInvalidException Если цель или состояние битые.
     */
    private function write(GameRecord $game, string $targetKey, array $selected): array
    {
        $target = $this->pair($targetKey);
        $slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $operations = [];
        $codes = [];
        foreach ($selected['effects'] as $effect) {
            if ($effect['kind'] !== StrikeManeuverEffect::KIND_STATE) {
                throw new GameInvalidException('Game strike maneuver effect is invalid');
            }

            $rule = $slice->findLive($effect['state']);
            $spec = $rule === null || $rule->isSpecBroken() ? null : $rule->getSpec();
            if (!$spec instanceof StateSpec) {
                throw new GameInvalidException('Game strike maneuver state is invalid');
            }

            $operations[] = [
                'kind' => 'putState',
                'stateRuleCode' => $effect['state'],
                'value' => $effect['amount'],
            ];
            $codes[] = $effect['state'];
        }

        if ($operations === []) {
            throw new GameInvalidException('Game strike maneuver effect is invalid');
        }

        $document = $this->sheets->snapshot($game, $target['type'], $target['id']);
        $sheet = is_array($document['sheet']['sheet'] ?? null) ? $document['sheet']['sheet'] : [];
        $choices = is_array($document['sheet']['choices'] ?? null) ? $document['sheet']['choices'] : [];
        $preview = $this->sheetWrites->previewDocument($game, [
            'rulesRevision' => $game->getRulesRevision(),
            'choices' => $choices,
            'sheet' => $sheet,
        ], $operations);
        $planned = $this->stateChecks->plan(
            $game,
            $sheet,
            $preview['rulesRevision'],
            $preview['states'],
            $operations,
            $codes,
        );
        $version = $this->sheetWrites->write(
            $game,
            $target['type'],
            $target['id'],
            is_int($document['actualVersion'] ?? null) ? $document['actualVersion'] : 0,
            $planned['operations'],
        );

        return [
            'ruleCode' => $selected['ruleCode'],
            'boosted' => $selected['boosted'],
            'targetKey' => $targetKey,
            'effects' => $selected['effects'],
            'sheetVersion' => $version,
            'rolls' => $planned['rolls'],
        ];
    }

    /**
     * Пара вида и id из ключа цели.
     *
     * @param string $targetKey Ключ.
     *
     * @return array{type: string, id: int} Пара.
     *
     * @throws GameInvalidException Если ключ чужой.
     */
    private function pair(string $targetKey): array
    {
        $parts = explode(':', $targetKey, 2);
        $type = $parts[0];
        $id = $parts[1] ?? null;
        if (($type !== 'character' && $type !== 'npc') || !is_numeric($id) || (int) $id < 1) {
            throw new GameInvalidException('Game strike maneuver target is invalid');
        }

        return ['type' => $type, 'id' => (int) $id];
    }
}
