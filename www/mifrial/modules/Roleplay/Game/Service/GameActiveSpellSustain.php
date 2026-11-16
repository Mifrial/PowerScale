<?php

declare(strict_types=1);

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies
// Подготовка, трата, запись и чат — одна причина изменения команды.

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Character\Dto\ScalarResourceValue;
use Mifrial\Roleplay\Game\Dto\GameActiveSpellPrepared;
use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Dto\GameBattleRecord;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameBattleConflictException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Exception\GameNotFoundException;
use Mifrial\Roleplay\Game\Repository\GameActiveSpellRepository;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Component\TurnAmount;
use Mifrial\Roleplay\Rule\Exception\RuleInvalidException;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Поддержание заклинания на своём ходу: мощь, ОД, чат.
 */
final class GameActiveSpellSustain
{
    /**
     * Создаёт команду.
     *
     * @param GameActiveSpellFacts $facts Загрузка строки.
     * @param GameActiveSpellRepository $spells Строки поддержания.
     * @param GameActionPerform $perform Траты ОД.
     * @param GameActiveSpellChat $chat Чат.
     * @param GameTurnAccess $access Право хода.
     * @param GameSpellPowerCap $powerCap Потолок мощи.
     * @param GameSpellDurationRules $durations Цена поддержания.
     * @param GameActiveSpellView $view JSON.
     * @param GameSpellCharge $charge Расчёт заряда.
     * @param GameStrikeSheetWrites $writes Запись листа.
     * @param GameActiveSpellCharge $activeCharge Снятие заряда.
     * @param GameSpellMana $mana Цена продолжения в мане.
     * @param GameSpellStability $stability Стабильность эффекта.
     *
     * @return void
     */
    public function __construct(
        private readonly GameActiveSpellFacts $facts,
        private readonly GameActiveSpellRepository $spells,
        private readonly GameActionPerform $perform,
        private readonly GameActiveSpellChat $chat,
        private readonly GameTurnAccess $access,
        private readonly GameSpellPowerCap $powerCap,
        private readonly GameSpellDurationRules $durations,
        private readonly GameActiveSpellView $view,
        private readonly GameSpellCharge $charge,
        private readonly GameStrikeSheetWrites $writes,
        private readonly GameActiveSpellCharge $activeCharge,
        private readonly GameSpellMana $mana,
        private readonly GameSpellStability $stability,
    ) {
    }

    /**
     * Продолжает поддержание с новой мощью.
     *
     * @param GameRecord $game Игра.
     * @param GameBattleRecord $battle Бой.
     * @param array{type: string, id: int} $current Текущий участник.
     * @param int $expectedBattle Ожидаемая версия боя.
     * @param int $expectedSheet Ожидаемая версия листа кастера.
     * @param array<string, mixed> $body Тело команды.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameBattleConflictException Если версия боя, листа или строки другая.
     * @throws GameNotFoundException Если строки или листа нет.
     * @throws GameInvalidException Если мощь, ОД, кастер или источник не подходят.
     */
    public function sustain(
        GameRecord $game,
        GameBattleRecord $battle,
        array $current,
        int $expectedBattle,
        int $expectedSheet,
        array $body,
        int $actorUserId,
        bool $editAll,
    ): array {
        $this->assertBattleVersion($battle, $expectedBattle);
        $record = $this->facts->load(
            $battle->getId(),
            is_int($body['spellId'] ?? null) ? $body['spellId'] : 0,
            is_int($body['expectedSpellVersion'] ?? null) ? $body['expectedSpellVersion'] : -1,
            $current,
            $this->access->isWriter($game, $actorUserId, $editAll),
        );
        if ($record->getCasterKey() !== $current['type'] . ':' . $current['id']) {
            throw new GameInvalidException('Game active spell is not yours');
        }

        $prepared = $this->facts->prepare($game, $record);
        $thread = GameCombatThread::buildTurn($battle->getId(), $battle->getRound(), $battle->getActiveIndex());
        if (!$prepared->sourceAvailable) {
            return $this->lostSource($game, $actorUserId, $battle, $record, $thread, $prepared);
        }

        $power = $this->power($body);
        $this->assertCap($prepared, $power);
        $this->assertRange($prepared, $power);
        $cost = $this->durations->actionCost($prepared->card->getSpell()->getDuration());
        $turnCode = (new GameActionFloor())->turnResourceCode($prepared->slice);
        $creation = $turnCode === null ? 0 : $this->creationSpent($prepared, $turnCode);
        $mana = $this->mana->quote(
            $prepared->slice,
            $prepared->sheet,
            $record->getSourceKey(),
            $power,
            $creation,
            $turnCode ?? '',
        );
        if ($mana !== null && $mana['cost'] > $mana['before']) {
            return $this->unpaid($game, $actorUserId, $battle, $record, $thread, $prepared, 'маны');
        }

        $spends = $this->spends($prepared, $cost);
        if ($spends !== [] && !$this->perform->hasSufficientSpends($game, $prepared->sheet, $spends)) {
            return $this->unpaid($game, $actorUserId, $battle, $record, $thread, $prepared, 'ОД');
        }

        [$sheetVersion, $granted] = $this->write(
            $game,
            $prepared,
            $expectedSheet,
            $spends,
            $this->mana->operation($record->getSourceKey(), $mana['cost'] ?? 0),
        );
        if ($granted > 0) {
            $spec = $this->charge->spec($prepared->card);
            $name = $spec === null
                ? 'заряд'
                : ($prepared->slice->findLive($spec->getStateCode())?->getName() ?? $spec->getStateCode());
            $this->chat->chargeGrantedAt($game, $actorUserId, $record, $thread, $name, $granted);
        }

        $updated = $this->spells->updateSustainPower(
            $record->getId(),
            $record->getStateVersion(),
            ['base' => $power->getBase(), 'size' => $power->getSize()],
        );
        $stability = $this->stability->value($prepared->card, $record->getParameterValues(), $power);
        $this->chat->sustainedAt($game, $actorUserId, $updated, $thread, $cost, $mana['cost'] ?? 0);

        return [
            'spell' => $this->view->build($updated, $stability),
            'actionCost' => $cost,
            'mana' => $mana,
            'battleId' => $battle->getId(),
            'version' => $battle->getStateVersion(),
            'sheetVersion' => $sheetVersion,
        ];
    }

    /**
     * Дропает эффект, если не хватает оплаты продолжения.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param GameBattleRecord $battle Бой.
     * @param GameActiveSpellRecord $record Строка.
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     * @param GameActiveSpellPrepared $prepared Снимок.
     * @param string $shortage Чего не хватило.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function unpaid(
        GameRecord $game,
        int $actorUserId,
        GameBattleRecord $battle,
        GameActiveSpellRecord $record,
        array $thread,
        GameActiveSpellPrepared $prepared,
        string $shortage,
    ): array {
        $sheetVersion = $this->activeCharge->removeFor($game, $prepared);
        $this->spells->deleteWithVersion($record->getId(), $record->getStateVersion());
        $this->chat->droppedAt($game, $actorUserId, $record, $thread, false, $shortage);

        return [
            'spell' => null,
            'dropped' => true,
            'reason' => 'payment',
            'shortage' => $shortage,
            'actionCost' => 0,
            'battleId' => $battle->getId(),
            'version' => $battle->getStateVersion(),
            'sheetVersion' => $sheetVersion,
        ];
    }

    /**
     * Создание карточки в ОД для цены маны за ход.
     *
     * @param GameActiveSpellPrepared $prepared Снимок.
     * @param string $turnCode Код ресурса хода.
     *
     * @return int ОД.
     *
     * @throws GameInvalidException Если карточка или ресурс битые.
     */
    private function creationSpent(GameActiveSpellPrepared $prepared, string $turnCode): int
    {
        $sum = 0;
        foreach ((new GameActionResourceComponents())->of($prepared->card) as $component) {
            if ($component->getResourceCode() !== $turnCode) {
                continue;
            }

            $amount = $component->getAmount();
            $sum += match (true) {
                $amount instanceof TurnAmount => $amount->getCount() * $this->charge->turnLimit(
                    $prepared->sheet,
                    $prepared->slice,
                    $turnCode,
                ),
                $amount instanceof DimensionalNumber => $amount->toInteger(),
                is_int($amount) => $amount,
                default => throw new GameInvalidException('Game spell creation cost is unsupported'),
            };
        }

        return $sum;
    }

    /**
     * Траты ОД на обновление.
     *
     * @param GameActiveSpellPrepared $prepared Снимок.
     * @param int $cost Цена в ОД.
     *
     * @return array<int, ResourceSpend> Траты.
     *
     * @throws GameInvalidException Если ресурса хода нет.
     */
    private function spends(GameActiveSpellPrepared $prepared, int $cost): array
    {
        if ($cost <= 0) {
            return [];
        }

        $code = (new GameActionFloor())->turnResourceCode($prepared->slice);
        if ($code === null) {
            throw new GameInvalidException('Game active spell turn resource is missing');
        }

        return [new ResourceSpend($code, new ScalarResourceValue($cost))];
    }

    /**
     * Пишет потерю источника и снимает строку.
     *
     * @param GameRecord $game Игра.
     * @param int $actorUserId Пользователь.
     * @param GameBattleRecord $battle Бой.
     * @param GameActiveSpellRecord $record Строка.
     * @param array{id: string, parentId: string, kind: string} $thread Нить.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если поле.
     */
    private function lostSource(
        GameRecord $game,
        int $actorUserId,
        GameBattleRecord $battle,
        GameActiveSpellRecord $record,
        array $thread,
        GameActiveSpellPrepared $prepared,
    ): array {
        $sheetVersion = $this->activeCharge->removeFor($game, $prepared);
        $this->spells->deleteWithVersion($record->getId(), $record->getStateVersion());
        $this->chat->droppedAt($game, $actorUserId, $record, $thread, true);

        return [
            'spell' => null,
            'dropped' => true,
            'reason' => 'source',
            'actionCost' => 0,
            'battleId' => $battle->getId(),
            'version' => $battle->getStateVersion(),
            'sheetVersion' => $sheetVersion,
        ];
    }

    /**
     * Мощь из тела.
     *
     * @param array<string, mixed> $body Тело.
     *
     * @return CharacteristicNumber Мощь.
     *
     * @throws GameInvalidException Если форма чужая.
     */
    private function power(array $body): CharacteristicNumber
    {
        $pair = $body['sustainPower'] ?? null;
        $base = is_array($pair) ? ($pair['base'] ?? null) : null;
        $size = is_array($pair) ? ($pair['size'] ?? null) : null;
        if (!is_int($base) || !is_int($size)) {
            throw new GameInvalidException('Game active spell power is invalid');
        }

        try {
            return new CharacteristicNumber($base, $size);
        } catch (RuleInvalidException $exception) {
            throw new GameInvalidException('Game active spell power is invalid', $exception);
        }
    }

    /**
     * Проверяет потолок характеристики пути.
     *
     * @param GameActiveSpellPrepared $prepared Снимок.
     * @param CharacteristicNumber $power Мощь.
     *
     * @return void
     *
     * @throws GameInvalidException Если мощь выше потолка.
     */
    private function assertCap(GameActiveSpellPrepared $prepared, CharacteristicNumber $power): void
    {
        $cap = $this->powerCap->cap(
            $prepared->slice,
            $prepared->path,
            $prepared->sheet,
            $prepared->record->getSourceKey(),
            $prepared->choices,
            $prepared->model,
        );
        if ($cap instanceof CharacteristicNumber && $power->characteristicDiffTo($cap) > 0) {
            throw new GameInvalidException('Game active spell power is above the cap');
        }
    }

    /**
     * Проверяет пределы формы длительности: минимум и максимум параметра.
     *
     * @param GameActiveSpellPrepared $prepared Снимок.
     * @param CharacteristicNumber $power Мощь.
     *
     * @return void
     *
     * @throws GameInvalidException Если мощь вне пределов формы.
     */
    private function assertRange(GameActiveSpellPrepared $prepared, CharacteristicNumber $power): void
    {
        $limits = $this->powerCap->limits($prepared->card);
        $min = $limits['min'];
        if ($min instanceof DimensionalNumber && $power->compare($min) < 0) {
            throw new GameInvalidException('Game active spell power is below the minimum');
        }

        $max = $limits['max'];
        if ($max instanceof DimensionalNumber && $power->compare($max) > 0) {
            throw new GameInvalidException('Game active spell power is above the shape maximum');
        }
    }

    /**
     * Списывает цену тика и выдаёт заряд одной записью листа.
     *
     * @param GameRecord $game Игра.
     * @param GameActiveSpellPrepared $prepared Снимок.
     * @param int $expectedSheet Ожидаемая версия листа.
     * @param array<int, ResourceSpend> $spends Траты ОД.
     * @param list<array<string, mixed>> $extraOperations Операции маны.
     *
     * @return array{0: int, 1: int} Версия листа после записи и сила выданного заряда.
     *
     * @throws GameInvalidException Если карточка битая.
     * @throws GameBattleConflictException Если версия листа другая.
     * @throws GameNotFoundException Если листа нет.
     */
    private function write(
        GameRecord $game,
        GameActiveSpellPrepared $prepared,
        int $expectedSheet,
        array $spends,
        array $extraOperations,
    ): array {
        $granted = 0;
        $operations = $extraOperations;
        $spec = $this->charge->spec($prepared->card);
        if ($spec !== null) {
            $levels = $prepared->sheet['abilityLevels'] ?? [];
            $cap = $this->charge->cap(
                $prepared->slice,
                $prepared->model,
                is_array($levels) ? $levels : [],
                $prepared->record->getSpellCode(),
                $spec,
            );
            $current = $this->charge->value($prepared->sheet, $spec->getStateCode(), $prepared->record->getSourceKey());
            $grant = $this->charge->grant(
                $prepared->sheet,
                $spec,
                $prepared->record->getSourceKey(),
                $cap,
                false,
            );
            $operations = [...$operations, ...$grant];
            $granted = $grant === [] ? 0 : min($cap, $current + $spec->getGrant());
        }

        if ($spends === [] && $operations === []) {
            return [$expectedSheet, 0];
        }

        return [
            $this->writes->write(
                $game,
                $prepared->record->getCasterKind(),
                $prepared->record->getCasterId(),
                $expectedSheet,
                $operations,
                null,
                null,
                $spends,
            ),
            $granted,
        ];
    }

    /**
     * Сверяет версию боя.
     *
     * @param GameBattleRecord $battle Бой.
     * @param int $expected Ожидаемая версия.
     *
     * @return void
     *
     * @throws GameBattleConflictException Если версия другая.
     */
    private function assertBattleVersion(GameBattleRecord $battle, int $expected): void
    {
        if ($battle->getStateVersion() !== $expected) {
            throw new GameBattleConflictException($battle->getStateVersion());
        }
    }
}
