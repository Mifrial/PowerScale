<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Roleplay\Game\Dto\Action\CastGameSpellInput;

/**
 * Разбор и нормализация тела game.castSpell.
 */
final class GameSpellCastBody
{
    /**
     * Проверяет формы и собирает тело команды.
     *
     * @param CastGameSpellInput $input Вход.
     *
     * @return array<string, mixed> Нормализованное тело.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    public function parse(CastGameSpellInput $input): array
    {
        if ($input->gameId < 1 || $input->battleId < 1 || $input->expectedVersion < 0) {
            throw $this->invalid();
        }

        if ($input->idempotencyKey === '' || $input->spellCode === '' || $input->pathCode === '') {
            throw $this->invalid();
        }

        if ($input->expectedSheetVersion < 0 || $input->distanceIpari < 0) {
            throw $this->invalid();
        }

        $actor = $this->side($input->actor);
        $target = $input->target === null ? null : $this->side($input->target);
        if (($target !== null) !== ($input->expectedTargetVersion !== null)) {
            throw $this->invalid();
        }

        if ($target !== null && $input->expectedTargetVersion < 0) {
            throw $this->invalid();
        }

        return [
            'gameId' => $input->gameId,
            'battleId' => $input->battleId,
            'idempotencyKey' => $input->idempotencyKey,
            'expectedVersion' => $input->expectedVersion,
            'expectedSheetVersion' => $input->expectedSheetVersion,
            'expectedTargetVersion' => $input->expectedTargetVersion,
            'actor' => $actor,
            'spellCode' => $input->spellCode,
            'pathCode' => $input->pathCode,
            'parameters' => $this->parameters($input->parameters),
            'usedPower' => $this->usedPower($input->usedPower),
            'target' => $target,
            'distanceIpari' => $input->distanceIpari,
        ];
    }

    /**
     * Сторона боя.
     *
     * @param array<mixed> $side Сторона.
     *
     * @return array{type: string, id: int} Сторона.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function side(array $side): array
    {
        $type = $side['type'] ?? null;
        $id = $side['id'] ?? null;
        if (($type !== 'character' && $type !== 'npc') || !is_int($id) || $id < 1) {
            throw $this->invalid();
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * Карта активационных параметров.
     *
     * @param array<mixed> $parameters Параметры.
     *
     * @return array<string, int|array{base: int, size: int}> Параметры.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function parameters(array $parameters): array
    {
        if ($parameters !== [] && array_is_list($parameters)) {
            throw $this->invalid();
        }

        $normalized = [];
        foreach ($parameters as $code => $value) {
            if (!is_string($code) || $code === '') {
                throw $this->invalid();
            }

            if (is_int($value)) {
                $normalized[$code] = $value;
                continue;
            }

            $normalized[$code] = $this->pair($value);
        }

        return $normalized;
    }

    /**
     * Источник и значение используемой мощи.
     *
     * @param array<mixed> $usedPower Мощь.
     *
     * @return array{source: array<string, mixed>, value: array{base: int, size: int}} Значение.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function usedPower(array $usedPower): array
    {
        $source = $usedPower['source'] ?? null;
        if (!is_array($source) || array_is_list($source)) {
            throw $this->invalid();
        }

        $kind = $source['kind'] ?? null;
        $normalized = match ($kind) {
            'characteristic' => $this->sourceCode($source),
            'item' => $this->sourceItem($source),
            'grant' => $this->sourceGrant($source),
            default => null,
        };
        if ($normalized === null) {
            throw $this->invalid();
        }

        return [
            'source' => $normalized,
            'value' => $this->pair($usedPower['value'] ?? null),
        ];
    }

    /**
     * Источник-характеристика.
     *
     * @param array<mixed> $source Источник.
     *
     * @return array{kind: string, code: string} Источник.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function sourceCode(array $source): array
    {
        $code = $source['code'] ?? null;
        if (!is_string($code) || $code === '') {
            throw $this->invalid();
        }

        return ['kind' => 'characteristic', 'code' => $code];
    }

    /**
     * Источник-предмет.
     *
     * @param array<mixed> $source Источник.
     *
     * @return array{kind: string, inventoryId: int} Источник.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function sourceItem(array $source): array
    {
        $inventoryId = $source['inventoryId'] ?? null;
        if (!is_int($inventoryId) || $inventoryId < 1) {
            throw $this->invalid();
        }

        return ['kind' => 'item', 'inventoryId' => $inventoryId];
    }

    /**
     * Источник-грант.
     *
     * @param array<mixed> $source Источник.
     *
     * @return array{kind: string, abilityCode: string} Источник.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function sourceGrant(array $source): array
    {
        $abilityCode = $source['abilityCode'] ?? null;
        if (!is_string($abilityCode) || $abilityCode === '') {
            throw $this->invalid();
        }

        return ['kind' => 'grant', 'abilityCode' => $abilityCode];
    }

    /**
     * Размерная пара.
     *
     * @param mixed $value Значение.
     *
     * @return array{base: int, size: int} Пара.
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function pair(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            throw $this->invalid();
        }

        $base = $value['base'] ?? null;
        $size = $value['size'] ?? null;
        if (!is_int($base) || !is_int($size) || count($value) !== 2) {
            throw $this->invalid();
        }

        return ['base' => $base, 'size' => $size];
    }

    /**
     * Ошибка формы запроса.
     *
     * @return ActionException Исключение.
     */
    private function invalid(): ActionException
    {
        return new ActionException('INVALID_PARAMS', 'Game spell cast is invalid');
    }
}
