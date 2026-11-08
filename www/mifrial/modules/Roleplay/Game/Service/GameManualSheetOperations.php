<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Операции порта ручной правки листа.
 */
final class GameManualSheetOperations
{
    /**
     * Одна операция порта.
     *
     * @param string $operation Код HTTP.
     * @param array<string, mixed> $details Поля.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return list<array<string, mixed>> Операции.
     *
     * @throws GameInvalidException Если форма.
     */
    public function effects(string $operation, array $details, CharacterRuleSlice $slice): array
    {
        return match ($operation) {
            'setResource' => [$this->resource($details, $slice)],
            'addState' => [$this->putState($details, $slice)],
            'patchState' => [$this->patchState($details, $slice)],
            'removeState' => [$this->removeState($details)],
            'setEquipped' => [$this->equipped($details)],
            'setOccupyHands' => [$this->occupyHands($details)],
            default => throw new GameInvalidException('Game manual sheet action is invalid'),
        };
    }

    /**
     * Native current по спеке.
     *
     * @param mixed $current Вход.
     * @param ResourceSpec $spec Карточка.
     *
     * @return int|array{base: int, size: int} Native.
     *
     * @throws GameInvalidException Если форма.
     */
    public function nativeCurrent(mixed $current, ResourceSpec $spec): int|array
    {
        if (!$spec->isDimensional()) {
            if (is_int($current)) {
                return $current;
            }

            $base = is_array($current) ? ($current['base'] ?? null) : null;
            if (is_int($base)) {
                return $base;
            }

            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        $base = is_array($current) ? ($current['base'] ?? null) : null;
        $size = is_array($current) ? ($current['size'] ?? null) : null;
        if (!is_int($base) || !is_int($size)) {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        return ['base' => $base, 'size' => $size];
    }

    /**
     * Операция current ресурса.
     *
     * @param array<string, mixed> $details Поля.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если форма.
     */
    private function resource(array $details, CharacterRuleSlice $slice): array
    {
        $code = $details['resourceCode'] ?? null;
        if (!is_string($code) || $code === '' || !array_key_exists('current', $details)) {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        $spec = $slice->findLive($code)?->getSpec();
        if (!$spec instanceof ResourceSpec) {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        return [
            'kind' => 'setResourceCurrent',
            'resourceCode' => $code,
            'current' => $this->nativeCurrent($details['current'], $spec),
        ];
    }

    /**
     * Операция setEquipped.
     *
     * @param array<string, mixed> $details Поля.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если форма.
     */
    private function equipped(array $details): array
    {
        $inventoryId = $details['inventoryId'] ?? null;
        $equipped = $details['equipped'] ?? null;
        if (!is_int($inventoryId) || $inventoryId < 1 || !is_bool($equipped)) {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        return [
            'kind' => 'setInventoryEquipped',
            'inventoryId' => $inventoryId,
            'equipped' => $equipped,
        ];
    }

    /**
     * Операция setOccupyHands.
     *
     * @param array<string, mixed> $details Поля.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если форма.
     */
    private function occupyHands(array $details): array
    {
        $inventoryId = $details['inventoryId'] ?? null;
        $occupyHands = $details['occupyHands'] ?? null;
        if (!is_int($inventoryId) || $inventoryId < 1 || !is_int($occupyHands) || $occupyHands < 0) {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        return [
            'kind' => 'setInventoryOccupyHands',
            'inventoryId' => $inventoryId,
            'occupyHands' => $occupyHands,
        ];
    }

    /**
     * putState. Увечье на HTTP требует sidecar.
     *
     * @param array<string, mixed> $details Поля.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если форма.
     */
    private function putState(array $details, CharacterRuleSlice $slice): array
    {
        $code = $details['stateRuleCode'] ?? null;
        if (!is_string($code) || $code === '') {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        $spec = $slice->findLive($code)?->getSpec();
        if (!$spec instanceof StateSpec) {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        $row = ['kind' => 'putState', 'stateRuleCode' => $code];
        if (array_key_exists('value', $details)) {
            $row['value'] = $details['value'];
        }

        return $this->withRequiredMaim($row, $details, $spec);
    }

    /**
     * Патч состояния по id.
     *
     * @param array<string, mixed> $details Поля.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если форма.
     */
    private function patchState(array $details, CharacterRuleSlice $slice): array
    {
        $row = ['kind' => 'patchState'] + $this->stateTarget($details);
        foreach (['value', 'maim'] as $field) {
            if (array_key_exists($field, $details)) {
                $row[$field] = $details[$field];
            }
        }

        $code = $details['stateRuleCode'] ?? null;
        $spec = is_string($code) ? $slice->findLive($code)?->getSpec() : null;
        if ($spec instanceof StateSpec) {
            return $this->withRequiredMaim($row, $details, $spec);
        }

        return $row;
    }

    /**
     * Снятие состояния по id.
     *
     * @param array<string, mixed> $details Поля.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если нет id.
     */
    private function removeState(array $details): array
    {
        return ['kind' => 'removeState'] + $this->stateTarget($details);
    }

    /**
     * Id строки листа.
     *
     * @param array<string, mixed> $details Поля.
     *
     * @return array{id: int} Ключ.
     *
     * @throws GameInvalidException Если нет id.
     */
    private function stateTarget(array $details): array
    {
        $id = $details['id'] ?? null;
        if (is_int($id) && $id >= 1) {
            return ['id' => $id];
        }

        throw new GameInvalidException('Game manual sheet action is invalid');
    }

    /**
     * HTTP увечья требует срок.
     *
     * @param array<string, mixed> $row Операция.
     * @param array<string, mixed> $details Поля.
     * @param StateSpec $spec Карточка.
     *
     * @return array<string, mixed> Операция.
     *
     * @throws GameInvalidException Если увечье без срока.
     */
    private function withRequiredMaim(array $row, array $details, StateSpec $spec): array
    {
        if (!$spec->isMaim()) {
            if (array_key_exists('maim', $details)) {
                throw new GameInvalidException('Game manual sheet action is invalid');
            }

            return $row;
        }

        if (!array_key_exists('maim', $details)) {
            throw new GameInvalidException('Game manual sheet action is invalid');
        }

        $row['maim'] = $details['maim'];

        return $row;
    }
}
