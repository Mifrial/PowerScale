<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Interface\Service\IGameStrikes;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;
use Mifrial\Roleplay\Game\Interface\Service\IGames;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Единый вход действий: карточка, удар и рана.
 */
final class GameRunAction
{
    /**
     * Создаёт диспетчер.
     *
     * @param GameRunActionBody $body Разбор конверта.
     * @param IGameStrikes $strikes Одиночный удар.
     * @param IGameWideStrikes $wideStrikes Широкий удар.
     * @param GameActionDeclare $actions Карточки действий.
     * @param GameWoundActions $wounds Зажим и перевязка.
     * @param GameActionRepository $repository Строки удара.
     * @param GameCommandRepository $commands Журнал команд.
     * @param IGames $games Игра.
     * @param GameStrikeRules $rules Карточки.
     *
     * @return void
     */
    public function __construct(
        private readonly GameRunActionBody $body,
        private readonly IGameStrikes $strikes,
        private readonly IGameWideStrikes $wideStrikes,
        private readonly GameActionDeclare $actions,
        private readonly GameWoundActions $wounds,
        private readonly GameActionRepository $repository,
        private readonly GameCommandRepository $commands,
        private readonly IGames $games,
        private readonly GameStrikeRules $rules,
    ) {
    }

    /**
     * Выполняет действие.
     *
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право править чужое.
     * @param bool $viewAll Право видеть карточку.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ повтора.
     * @param int $expectedVersion Версия боя.
     * @param array<string, mixed> $action Конверт.
     *
     * @return array<string, mixed> Нормализованный итог.
     *
     * @throws \Mifrial\Core\Kernel\Exception\ActionException AUTH_DENIED или INVALID_PARAMS.
     * @throws \Mifrial\Roleplay\Game\Exception\GameNotFoundException Если строки нет.
     * @throws GameInvalidException Если действие отвергнуто.
     */
    public function run(
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
        array $action,
    ): array {
        $parsed = $this->body->parse($action);
        if ($parsed['phase'] === 'respond') {
            return $this->respond(
                $parsed,
                $gameId,
                $actorUserId,
                $editAll,
                $viewAll,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
            );
        }

        if ($parsed['actor'] === null) {
            throw new GameInvalidException('Game action actor is invalid');
        }

        if ($parsed['kind'] === 'action') {
            return $this->declareAction(
                $parsed,
                $gameId,
                $actorUserId,
                $editAll,
                $viewAll,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
            );
        }

        if ($parsed['kind'] === 'wound') {
            return $this->declareWound(
                $parsed,
                $gameId,
                $actorUserId,
                $editAll,
                $viewAll,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
            );
        }

        return $this->declareStrike(
            $parsed,
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $battleId,
            $idempotencyKey,
            $expectedVersion,
        );
    }

    /**
     * Карточка действия.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право.
     * @param bool $viewAll Право.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Итог.
     */
    private function declareAction(
        array $parsed,
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): array {
        $context = $parsed['context'];
        $result = $this->actions->act($gameId, $actorUserId, $editAll, $viewAll, [
            'battleId' => $battleId,
            'idempotencyKey' => $idempotencyKey,
            'expectedVersion' => $expectedVersion,
            'expectedSheetVersion' => $context['expectedSheetVersion'],
            'actor' => $parsed['actor'],
            'actionRuleCode' => $parsed['code'],
            'followUpTargetKey' => $context['followUpTargetKey'],
            'chosenAmounts' => $context['chosenAmounts'],
        ]);

        return array_merge($result, [
            'kind' => 'action',
            'phase' => 'declare',
            'battleId' => $result['battleId'] ?? $battleId,
            'version' => $result['version'] ?? $expectedVersion,
        ]);
    }

    /**
     * Зажим, отпускание или перевязка.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право.
     * @param bool $viewAll Право.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Итог.
     */
    private function declareWound(
        array $parsed,
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): array {
        $context = $parsed['context'];
        $result = $this->wounds->act($gameId, $actorUserId, $editAll, $viewAll, [
            'idempotencyKey' => $idempotencyKey,
            'operation' => $context['operation'],
            'actor' => ['kind' => $parsed['actor']['type'], 'id' => $parsed['actor']['id']],
            'target' => $context['target'],
            'stateId' => $context['stateId'],
            'actionRuleCode' => $parsed['code'],
            'expectedSheetVersion' => $context['expectedSheetVersion'],
            'battleId' => $battleId,
            'expectedBattleVersion' => $expectedVersion,
        ]);

        return array_merge($result, [
            'kind' => 'wound',
            'phase' => 'declare',
            'battleId' => $battleId,
            'version' => is_int($result['battleVersion'] ?? null) ? $result['battleVersion'] : $expectedVersion,
        ]);
    }

    /**
     * Объявление удара.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право.
     * @param bool $viewAll Право.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если удары несовместимы.
     */
    private function declareStrike(
        array $parsed,
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): array {
        $context = $parsed['context'];
        $strikes = $context['strikes'];
        $targetKeys = [];
        foreach ($strikes as $strike) {
            $targetKeys[$strike['target']['type'] . ':' . $strike['target']['id']] = true;
        }

        $game = $this->games->get($gameId);
        $wideCard = $this->rules->isWideAttack(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $parsed['code'],
        );
        if (!$wideCard && count($targetKeys) === 1) {
            $base = $this->rules->actionBaseOf(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $parsed['code'],
            );
            $cardCount = $base?->getStrikeCount();
            $minWeapons = $base?->getMinWeapons();
            if (
                (is_int($cardCount) && $cardCount > count($strikes))
                || (is_int($minWeapons) && $minWeapons > count($strikes))
            ) {
                throw new GameInvalidException('Game strike count is invalid');
            }
        }

        if (!$wideCard && count($strikes) > 1 && count($targetKeys) === 1) {
            $result = $this->strikes->declareMultiStrike(
                $gameId,
                $actorUserId,
                $editAll,
                $viewAll,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
                $this->multiAttack($parsed, $strikes),
            );

            return $this->declared($result, $strikes);
        }

        if ($wideCard || count($targetKeys) > 1) {
            $result = $this->wideStrikes->declareWideStrike(
                $gameId,
                $actorUserId,
                $editAll,
                $viewAll,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
                $this->wideAttack($parsed, $strikes),
            );
        } else {
            $result = $this->strikes->declareStrike(
                $gameId,
                $actorUserId,
                $editAll,
                $viewAll,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
                $this->singleAttack($parsed, $strikes[0]),
            );
        }

        return $this->declared($result, $strikes);
    }

    /**
     * Ответ защиты.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право.
     * @param bool $viewAll Право.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если удара нет.
     */
    private function respond(
        array $parsed,
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): array {
        $open = $this->repository->findOpenByBattle($battleId, 'strike')
            ?? $this->repository->findOpenByBattle($battleId, 'wide_strike');
        if ($open === null) {
            $wideReplay = $this->commands->findByKey($gameId, 'wide_strike', $idempotencyKey) !== null;
            $kind = $wideReplay ? 'wide_strike' : 'strike';
            $open = $this->repository->findLatestWithStrikes($battleId, $kind);
        }

        $kind = is_array($open) ? ($open['kind'] ?? null) : null;
        if (!is_array($open) || ($kind !== 'strike' && $kind !== 'wide_strike')) {
            throw new GameInvalidException('Game strike is not open');
        }

        if ($kind === 'wide_strike') {
            return $this->respondWide(
                $parsed,
                $gameId,
                $actorUserId,
                $editAll,
                $viewAll,
                $battleId,
                $idempotencyKey,
                $expectedVersion,
            );
        }

        return $this->respondSingle(
            $open,
            $parsed,
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $battleId,
            $idempotencyKey,
            $expectedVersion,
        );
    }

    /**
     * Ответ по широкому удару.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право.
     * @param bool $viewAll Право.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если защита без цели.
     */
    private function respondWide(
        array $parsed,
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): array {
        $defenses = [];
        foreach ($parsed['context']['defenses'] as $defense) {
            if ($defense['target'] === null) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense target');
            }

            $defenses[] = $defense;
        }

        $result = $this->wideStrikes->resolveWideStrike(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $battleId,
            $idempotencyKey,
            $expectedVersion,
            ['defenses' => $defenses],
        );

        return $this->respondedWide($result);
    }

    /**
     * Ответ по одиночному удару.
     *
     * @param array<string, mixed> $open Открытая строка.
     * @param array<string, mixed> $parsed Разбор.
     * @param int $gameId Игра.
     * @param int $actorUserId Пользователь.
     * @param bool $editAll Право.
     * @param bool $viewAll Право.
     * @param int $battleId Бой.
     * @param string $idempotencyKey Ключ.
     * @param int $expectedVersion Версия.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если защит не одна.
     */
    private function respondSingle(
        array $open,
        array $parsed,
        int $gameId,
        int $actorUserId,
        bool $editAll,
        bool $viewAll,
        int $battleId,
        string $idempotencyKey,
        int $expectedVersion,
    ): array {
        $defenses = $parsed['context']['defenses'];
        if (count($defenses) !== 1) {
            throw new GameInvalidException('Game strike defense is invalid');
        }

        $defense = $defenses[0];
        $result = $this->strikes->resolveStrike(
            $gameId,
            $actorUserId,
            $editAll,
            $viewAll,
            $battleId,
            $idempotencyKey,
            $expectedVersion,
            $defense['expectedSheetVersion'],
            [
                'reaction' => $defense['reaction'],
                'blockItemInventoryId' => $defense['blockItemInventoryId'],
                'blockItemProfileIndex' => $defense['blockItemProfileIndex'],
                'blockItemRuleCode' => $defense['blockItemRuleCode'],
                'turn' => $defense['turn'],
            ],
        );

        return $this->respondedSingle($open, $result);
    }

    /**
     * Старый выбор одиночного удара.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param array<string, mixed> $strike Строка.
     *
     * @return array<string, mixed> Выбор.
     */
    private function singleAttack(array $parsed, array $strike): array
    {
        $attack = [
            'attacker' => $parsed['actor'],
            'defender' => $strike['target'],
            'actionRuleCode' => $parsed['code'],
            'itemInventoryId' => $strike['itemInventoryId'],
            'itemRuleCode' => $strike['itemRuleCode'],
            'profileType' => $strike['profileType'],
            'profileIndex' => $strike['profileIndex'],
            'instanceIndex' => $strike['instanceIndex'],
            'conditions' => $strike['conditions'],
            'chosenAmounts' => $parsed['context']['chosenAmounts'],
            'afterStrikeOptions' => $strike['afterStrikeOptions'] ?? [],
        ];
        if ($strike['ammunitionInventoryId'] !== null) {
            $attack['ammunitionInventoryId'] = $strike['ammunitionInventoryId'];
        }

        return $attack;
    }

    /**
     * Выбор нескольких экземпляров одного оружия.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return array<string, mixed> Выбор.
     */
    private function multiAttack(array $parsed, array $strikes): array
    {
        $this->assertNoAfterStrikeOptions($strikes);
        $first = $strikes[0];
        $attack = [
            'attacker' => $parsed['actor'],
            'defender' => $first['target'],
            'actionRuleCode' => $parsed['code'],
            'itemInventoryId' => $first['itemInventoryId'],
            'itemRuleCode' => $first['itemRuleCode'],
            'profileType' => $first['profileType'],
            'profileIndex' => $first['profileIndex'],
            'instanceIndex' => $first['instanceIndex'],
            'conditions' => $first['conditions'],
            'chosenAmounts' => $parsed['context']['chosenAmounts'],
            'strikes' => $strikes,
        ];
        if ($first['ammunitionInventoryId'] !== null) {
            $attack['ammunitionInventoryId'] = $first['ammunitionInventoryId'];
        }

        return $attack;
    }

    /**
     * Старый выбор широкого удара.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return array<string, mixed> Выбор.
     *
     * @throws GameInvalidException Если строки различаются предметом или профилем.
     */
    private function wideAttack(array $parsed, array $strikes): array
    {
        $this->assertNoAfterStrikeOptions($strikes);
        $first = $strikes[0];
        $targets = [];
        foreach ($strikes as $strike) {
            if (
                $strike['itemInventoryId'] !== $first['itemInventoryId']
                || $strike['itemRuleCode'] !== $first['itemRuleCode']
                || $strike['profileType'] !== $first['profileType']
                || $strike['profileIndex'] !== $first['profileIndex']
                || $strike['instanceIndex'] !== $first['instanceIndex']
            ) {
                throw new GameInvalidException('Game wide strike item is invalid');
            }

            $targets[] = [
                'type' => $strike['target']['type'],
                'id' => $strike['target']['id'],
                'conditions' => $strike['conditions'],
            ];
        }

        $attack = [
            'attacker' => $parsed['actor'],
            'targets' => $targets,
            'actionRuleCode' => $parsed['code'],
            'itemInventoryId' => $first['itemInventoryId'],
            'itemRuleCode' => $first['itemRuleCode'],
            'profileType' => $first['profileType'],
            'profileIndex' => $first['profileIndex'],
            'chosenAmounts' => $parsed['context']['chosenAmounts'],
        ];
        if ($first['ammunitionInventoryId'] !== null) {
            $attack['ammunitionInventoryId'] = $first['ammunitionInventoryId'];
        }

        return $attack;
    }

    /**
     * Опции после удара объявляются только одиночным ударом.
     *
     * @param list<array<string, mixed>> $strikes Строки.
     *
     * @return void
     *
     * @throws GameInvalidException Если опция пришла на серию или широкий удар.
     */
    private function assertNoAfterStrikeOptions(array $strikes): void
    {
        foreach ($strikes as $strike) {
            if (($strike['afterStrikeOptions'] ?? []) !== []) {
                throw new GameInvalidException('Game strike after-strike option is not supported');
            }
        }
    }

    /**
     * Нормализует объявление удара.
     *
     * @param array<string, mixed> $result Итог домена.
     * @param list<array<string, mixed>> $strikes Запрошенные строки.
     *
     * @return array<string, mixed> Итог.
     */
    private function declared(array $result, array $strikes): array
    {
        $rows = [];
        $pending = [];
        foreach (array_values($strikes) as $index => $strike) {
            $rows[] = [
                'index' => $index,
                'itemInventoryId' => $strike['itemInventoryId'],
                'itemRuleCode' => $strike['itemRuleCode'],
                'profileType' => $strike['profileType'],
                'profileIndex' => $strike['profileIndex'],
                'instanceIndex' => $strike['instanceIndex'],
                'target' => $strike['target'],
                'reaction' => null,
                'conditions' => $strike['conditions'] ?? (new GameStrikeConditions())->defaults(),
            ];
            $pending[] = ['index' => $index, 'target' => $strike['target']];
        }

        $response = [
            'kind' => 'strike',
            'phase' => 'declare',
            'battleId' => $result['battleId'] ?? 0,
            'version' => $result['version'] ?? 0,
            'strikeId' => $result['strikeId'] ?? 0,
            'closed' => false,
            'sheetVersion' => null,
            'strikes' => $rows,
            'pendingStrikes' => $pending,
        ];
        if (($result['debt'] ?? false) === true) {
            $response['debt'] = true;
        }

        return $response;
    }

    /**
     * Нормализует ответ одиночного удара.
     *
     * @param array<string, mixed> $open Открытая строка.
     * @param array<string, mixed> $result Итог домена.
     *
     * @return array<string, mixed> Итог.
     */
    private function respondedSingle(array $open, array $result): array
    {
        $strikeId = is_int($open['id'] ?? null) ? $open['id'] : 0;
        $rows = $strikeId > 0 ? $this->repository->getStrikes($strikeId) : [];
        $sequential = ($result['sequential'] ?? false) === true;
        $resolvedIndex = is_int($result['resolvedIndex'] ?? null) ? $result['resolvedIndex'] : 0;
        $entries = [];
        $pending = [];
        foreach ($rows as $index => $row) {
            $reaction = is_string($row['reaction'] ?? null) ? $row['reaction'] : null;
            $entry = [
                'index' => $index,
                'itemInventoryId' => is_int($row['item_inventory_id'] ?? null) ? $row['item_inventory_id'] : 0,
                'itemRuleCode' => is_string($row['item_rule_code'] ?? null) ? $row['item_rule_code'] : '',
                'profileType' => is_string($row['profile_type'] ?? null) ? $row['profile_type'] : '',
                'profileIndex' => is_int($row['profile_index'] ?? null) ? $row['profile_index'] : 0,
                'instanceIndex' => is_int($row['instance_index'] ?? null) ? $row['instance_index'] : 0,
                'target' => $this->rowTarget($row),
                'reaction' => $reaction,
                'conditions' => GameStrikeTargetRow::conditions($row),
            ];
            if (!$sequential || $index === $resolvedIndex) {
                $entry = array_merge($entry, [
                    'roll' => $result['attackerRoll'] ?? null,
                    'defenseRoll' => $result['defenderRoll'] ?? null,
                    'success' => $result['success'] ?? 0,
                    'damage' => $sequential
                        ? ($result['damage'] ?? null)
                        : ($result['packetDamages'][$index] ?? $result['damage'] ?? null),
                    'resistance' => $result['resistance'] ?? null,
                    'injury' => $sequential
                        ? ($result['injury'] ?? null)
                        : ($result['packetInjuries'][$index] ?? $result['injury'] ?? null),
                    'sheetVersion' => $result['sheetVersion'] ?? null,
                ]);
                if (array_key_exists('S', $result)) {
                    $entry['S'] = $result['S'];
                }

                if (array_key_exists('durabilityShave', $result)) {
                    $entry['durabilityShave'] = $result['durabilityShave'];
                }
            }

            if ($reaction === null) {
                $pending[] = ['index' => $index, 'target' => $this->rowTarget($row)];
            }

            $entries[] = $entry;
        }

        $base = $result;
        unset(
            $base['attackerRoll'],
            $base['defenderRoll'],
            $base['sequential'],
            $base['resolvedIndex'],
            $base['packetDamages'],
            $base['packetInjuries'],
        );
        $base['kind'] = 'strike';
        $base['phase'] = 'respond';
        $base['strikes'] = $entries;
        $base['pendingStrikes'] = $pending;

        return $base;
    }

    /**
     * Нормализует ответ широкого удара.
     *
     * @param array<string, mixed> $result Итог домена.
     *
     * @return array<string, mixed> Итог.
     */
    private function respondedWide(array $result): array
    {
        $strikeId = is_int($result['strikeId'] ?? null) ? $result['strikeId'] : 0;
        $rows = $strikeId > 0 ? $this->repository->getStrikes($strikeId) : [];
        $records = is_array($result['targetResults'] ?? null) ? $result['targetResults'] : [];
        $byTarget = [];
        foreach ($records as $record) {
            $target = is_array($record['target'] ?? null) ? $record['target'] : null;
            if ($target !== null && is_string($target['type'] ?? null) && is_int($target['id'] ?? null)) {
                $byTarget[$target['type'] . ':' . $target['id']] = $record;
            }
        }

        $strikes = [];
        $pending = [];
        foreach ($rows as $index => $row) {
            $target = $this->rowTarget($row);
            $key = $target['type'] . ':' . $target['id'];
            $record = $byTarget[$key] ?? null;
            $entry = [
                'index' => $index,
                'itemInventoryId' => is_int($row['item_inventory_id'] ?? null) ? $row['item_inventory_id'] : 0,
                'itemRuleCode' => is_string($row['item_rule_code'] ?? null) ? $row['item_rule_code'] : '',
                'profileType' => is_string($row['profile_type'] ?? null) ? $row['profile_type'] : '',
                'profileIndex' => is_int($row['profile_index'] ?? null) ? $row['profile_index'] : 0,
                'instanceIndex' => is_int($row['instance_index'] ?? null) ? $row['instance_index'] : 0,
                'target' => $target,
                'reaction' => is_string($row['reaction'] ?? null) ? $row['reaction'] : null,
                'conditions' => GameStrikeTargetRow::conditions($row),
            ];
            if (is_array($record)) {
                $extra = [];
                foreach ($record as $key => $value) {
                    if ($key === 'target') {
                        continue;
                    }

                    $extra[$key === 'defenderRoll' ? 'defenseRoll' : $key] = $value;
                }

                $entry = array_merge($entry, $extra);
            }

            if ($entry['reaction'] === null) {
                $pending[] = ['index' => $index, 'target' => $target];
            }

            $strikes[] = $entry;
        }

        return [
            'kind' => 'strike',
            'phase' => 'respond',
            'battleId' => $result['battleId'] ?? 0,
            'version' => $result['version'] ?? 0,
            'strikeId' => $strikeId,
            'closed' => ($result['closed'] ?? false) === true,
            'strikes' => $strikes,
            'pendingStrikes' => $pending,
        ];
    }

    /**
     * Цель строки удара.
     *
     * @param array<string, mixed> $row Строка.
     *
     * @return array{type: string, id: int} Пара.
     */
    private function rowTarget(array $row): array
    {
        $type = $row['target_type'] ?? null;
        $id = $row['target_id'] ?? null;

        return [
            'type' => is_string($type) ? $type : '',
            'id' => is_int($id) ? $id : 0,
        ];
    }
}
