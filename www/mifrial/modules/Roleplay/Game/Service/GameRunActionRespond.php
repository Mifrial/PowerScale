<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Interface\Service\IGameStrikes;
use Mifrial\Roleplay\Game\Interface\Service\IGameWideStrikes;
use Mifrial\Roleplay\Game\Repository\GameActionRepository;
use Mifrial\Roleplay\Game\Repository\GameCommandRepository;

/**
 * Ответ защитой на открытый удар.
 */
final class GameRunActionRespond
{
    /**
     * Создаёт сценарий ответа.
     *
     * @param IGameStrikes $strikes Одиночный удар.
     * @param IGameWideStrikes $wideStrikes Широкий удар.
     * @param GameActionRepository $repository Строки удара.
     * @param GameCommandRepository $commands Журнал команд.
     *
     * @return void
     */
    public function __construct(
        private readonly IGameStrikes $strikes,
        private readonly IGameWideStrikes $wideStrikes,
        private readonly GameActionRepository $repository,
        private readonly GameCommandRepository $commands,
    ) {
    }

    /**
     * Отвечает на открытый удар.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws GameInvalidException Если удара нет или защит не столько.
     */
    public function resolve(array $parsed, GameRunActionCall $call): array
    {
        $open = $this->open($call);

        return $open['kind'] === 'wide_strike'
            ? $this->wide($parsed, $call)
            : $this->single($open, $parsed, $call);
    }

    /**
     * Открытый удар боя.
     *
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Строка.
     *
     * @throws GameInvalidException Если удара нет.
     */
    private function open(GameRunActionCall $call): array
    {
        $open = $this->repository->findOpenByBattle($call->battleId, 'strike')
            ?? $this->repository->findOpenByBattle($call->battleId, 'wide_strike');
        if ($open === null) {
            $wideReplay = $this->commands->findByKey($call->gameId, 'wide_strike', $call->idempotencyKey) !== null;
            $kind = $wideReplay ? 'wide_strike' : 'strike';
            $open = $this->repository->findLatestWithStrikes($call->battleId, $kind);
        }

        if (!is_array($open)) {
            throw new GameInvalidException('Game strike is not open');
        }

        $kind = $open['kind'] ?? null;
        if ($kind !== 'strike' && $kind !== 'wide_strike') {
            throw new GameInvalidException('Game strike is not open');
        }

        return $open;
    }

    /**
     * Ответ по широкому удару.
     *
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws GameInvalidException Если защита без цели.
     */
    private function wide(array $parsed, GameRunActionCall $call): array
    {
        $result = $this->wideStrikes->resolveWideStrike(
            $call->gameId,
            $call->actorUserId,
            $call->editAll,
            $call->viewAll,
            $call->battleId,
            $call->idempotencyKey,
            $call->expectedVersion,
            ['defenses' => $this->requiredDefenses($parsed)],
        );

        return $this->respondedWide($result);
    }

    /**
     * Защиты широкого удара — все с целью.
     *
     * @param array<string, mixed> $parsed Разбор.
     *
     * @return list<array<string, mixed>> Защиты.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function requiredDefenses(array $parsed): array
    {
        $defenses = [];
        foreach ($parsed['context']['defenses'] as $defense) {
            if ($defense['target'] === null) {
                throw new ActionException('INVALID_PARAMS', 'Invalid parameter: defense target');
            }

            $defenses[] = $defense;
        }

        return $defenses;
    }

    /**
     * Ответ по одиночному удару.
     *
     * @param array<string, mixed> $open Открытая строка.
     * @param array<string, mixed> $parsed Разбор.
     * @param GameRunActionCall $call Снимок вызова.
     *
     * @return array<string, mixed> Итог.
     *
     * @throws GameInvalidException Если защит не одна.
     */
    private function single(array $open, array $parsed, GameRunActionCall $call): array
    {
        $defense = $this->defense($parsed);
        $result = $this->strikes->resolveStrike(
            $call->gameId,
            $call->actorUserId,
            $call->editAll,
            $call->viewAll,
            $call->battleId,
            $call->idempotencyKey,
            $call->expectedVersion,
            $defense['expectedSheetVersion'],
            $this->choice($defense),
        );

        return $this->respondedSingle($open, $result);
    }

    /**
     * Единственная защита одиночного удара.
     *
     * @param array<string, mixed> $parsed Разбор.
     *
     * @return array<string, mixed> Защита.
     *
     * @throws GameInvalidException Если защит не одна.
     */
    private function defense(array $parsed): array
    {
        $defenses = $parsed['context']['defenses'];
        if (count($defenses) !== 1) {
            throw new GameInvalidException('Game strike defense is invalid');
        }

        return $defenses[0];
    }

    /**
     * Выбор защиты одиночного удара.
     *
     * @param array<string, mixed> $defense Защита.
     *
     * @return array<string, mixed> Выбор.
     */
    private function choice(array $defense): array
    {
        $choice = [
            'reaction' => $defense['reaction'],
            'blockItemInventoryId' => $defense['blockItemInventoryId'],
            'blockItemProfileIndex' => $defense['blockItemProfileIndex'],
            'blockItemRuleCode' => $defense['blockItemRuleCode'],
            'turn' => $defense['turn'],
        ];
        if (array_key_exists('spendConcentration', $defense)) {
            $choice['spendConcentration'] = $defense['spendConcentration'];
        }

        return $choice;
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
            $entry = $this->singleRow($index, $row, $result, $sequential, $resolvedIndex);
            if ($entry['reaction'] === null) {
                $pending[] = ['index' => $index, 'target' => $this->rowTarget($row)];
            }

            $entries[] = $entry;
        }

        return $this->singleResponse($result, $entries, $pending);
    }

    /**
     * Строка ответа одиночного удара.
     *
     * @param int $index Индекс.
     * @param array<string, mixed> $row Строка.
     * @param array<string, mixed> $result Итог домена.
     * @param bool $sequential Последовательный ответ.
     * @param int $resolvedIndex Разрешённый индекс.
     *
     * @return array<string, mixed> Строка.
     */
    private function singleRow(
        int $index,
        array $row,
        array $result,
        bool $sequential,
        int $resolvedIndex,
    ): array {
        $entry = $this->rowEntry($index, $row);
        if (!$sequential || $index === $resolvedIndex) {
            return $this->resolvedEntry($entry, $index, $result, $sequential);
        }

        return $entry;
    }

    /**
     * Строка с броском и итогом.
     *
     * @param array<string, mixed> $entry Строка.
     * @param int $index Индекс.
     * @param array<string, mixed> $result Итог домена.
     * @param bool $sequential Последовательный ответ.
     *
     * @return array<string, mixed> Строка.
     */
    private function resolvedEntry(array $entry, int $index, array $result, bool $sequential): array
    {
        $entry = array_merge($entry, [
            'roll' => $result['attackerRoll'] ?? null,
            'defenseRoll' => $result['defenderRoll'] ?? null,
            'success' => $result['success'] ?? 0,
            'damage' => $this->packet($result, 'damage', 'packetDamages', $index, $sequential),
            'resistance' => $result['resistance'] ?? null,
            'injury' => $this->packet($result, 'injury', 'packetInjuries', $index, $sequential),
            'sheetVersion' => $result['sheetVersion'] ?? null,
        ]);
        if (array_key_exists('S', $result)) {
            $entry['S'] = $result['S'];
        }

        if (array_key_exists('durabilityShave', $result)) {
            $entry['durabilityShave'] = $result['durabilityShave'];
        }

        return $entry;
    }

    /**
     * Значение пакета или общее.
     *
     * @param array<string, mixed> $result Итог домена.
     * @param string $key Общий ключ.
     * @param string $packet Ключ пакета.
     * @param int $index Индекс.
     * @param bool $sequential Последовательный ответ.
     *
     * @return mixed Значение.
     */
    private function packet(array $result, string $key, string $packet, int $index, bool $sequential): mixed
    {
        if ($sequential) {
            return $result[$key] ?? null;
        }

        return $result[$packet][$index] ?? $result[$key] ?? null;
    }

    /**
     * Итог ответа одиночного удара.
     *
     * @param array<string, mixed> $result Итог домена.
     * @param list<array<string, mixed>> $entries Строки.
     * @param list<array<string, mixed>> $pending Ожидание.
     *
     * @return array<string, mixed> Итог.
     */
    private function singleResponse(array $result, array $entries, array $pending): array
    {
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
        $byTarget = $this->recordsByTarget($result);
        $strikes = [];
        $pending = [];
        foreach ($rows as $index => $row) {
            $target = $this->rowTarget($row);
            $record = $byTarget[$target['type'] . ':' . $target['id']] ?? null;
            $entry = $this->wideRow($index, $row, $record);
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
            'awaiting' => is_array($result['awaiting'] ?? null) ? $result['awaiting'] : [],
        ];
    }

    /**
     * Итоги по целям.
     *
     * @param array<string, mixed> $result Итог домена.
     *
     * @return array<string, array<string, mixed>> Итоги.
     */
    private function recordsByTarget(array $result): array
    {
        $records = is_array($result['targetResults'] ?? null) ? $result['targetResults'] : [];
        $byTarget = [];
        foreach ($records as $record) {
            $target = is_array($record['target'] ?? null) ? $record['target'] : null;
            if ($target !== null && is_string($target['type'] ?? null) && is_int($target['id'] ?? null)) {
                $byTarget[$target['type'] . ':' . $target['id']] = $record;
            }
        }

        return $byTarget;
    }

    /**
     * Строка ответа широкого удара.
     *
     * @param int $index Индекс.
     * @param array<string, mixed> $row Строка.
     * @param mixed $record Итог цели.
     *
     * @return array<string, mixed> Строка.
     */
    private function wideRow(int $index, array $row, mixed $record): array
    {
        $entry = $this->rowEntry($index, $row);
        if (!is_array($record)) {
            return $entry;
        }

        return array_merge($entry, $this->recordExtra($record));
    }

    /**
     * Дополнительные поля итога цели.
     *
     * @param array<string, mixed> $record Итог цели.
     *
     * @return array<string, mixed> Поля.
     */
    private function recordExtra(array $record): array
    {
        $extra = [];
        foreach ($record as $key => $value) {
            if ($key === 'target') {
                continue;
            }

            $extra[$key === 'defenderRoll' ? 'defenseRoll' : $key] = $value;
        }

        return $extra;
    }

    /**
     * Базовая строка из записи удара.
     *
     * @param int $index Индекс.
     * @param array<string, mixed> $row Строка.
     *
     * @return array<string, mixed> Строка.
     */
    private function rowEntry(int $index, array $row): array
    {
        return [
            'index' => $index,
            'itemInventoryId' => is_int($row['item_inventory_id'] ?? null) ? $row['item_inventory_id'] : 0,
            'itemRuleCode' => is_string($row['item_rule_code'] ?? null) ? $row['item_rule_code'] : '',
            'profileType' => is_string($row['profile_type'] ?? null) ? $row['profile_type'] : '',
            'profileIndex' => is_int($row['profile_index'] ?? null) ? $row['profile_index'] : 0,
            'instanceIndex' => is_int($row['instance_index'] ?? null) ? $row['instance_index'] : 0,
            'target' => $this->rowTarget($row),
            'reaction' => is_string($row['reaction'] ?? null) ? $row['reaction'] : null,
            'conditions' => GameStrikeTargetRow::conditions($row),
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
