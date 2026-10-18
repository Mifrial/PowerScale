<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\StateSpec;

/**
 * Операции порта и текст чата для ручной правки.
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
            default => throw new GameInvalidException('Game manual sheet action is invalid'),
        };
    }

    /**
     * Текст сообщения чата.
     *
     * @param string $operation Код.
     * @param array<string, mixed> $details Поля.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $beforeSheet Лист до.
     * @param string $subjectName Имя персонажа или NPC.
     *
     * @return string Текст.
     */
    public function chatText(
        string $operation,
        array $details,
        CharacterRuleSlice $slice,
        array $beforeSheet,
        string $subjectName = '',
    ): string {
        if ($operation === 'setResource') {
            $code = is_string($details['resourceCode'] ?? null) ? $details['resourceCode'] : '';
            $before = $this->resourceCurrent($beforeSheet, $code);
            $after = $details['current'] ?? null;
            $spec = $slice->findLive($code)?->getSpec();
            if ($spec instanceof ResourceSpec) {
                try {
                    $after = $this->nativeCurrent($after, $spec);
                } catch (GameInvalidException) {
                    $after = $details['current'] ?? null;
                }
            }

            return $this->about($subjectName, 'Задаёт ' . $this->ruleName($slice, $code) . ': ' . $this->label($before) . ' → ' . $this->label($after) . '.');
        }

        $code = is_string($details['stateRuleCode'] ?? null)
            ? $details['stateRuleCode']
            : $this->stateCodeById($beforeSheet, $details['id'] ?? null);

        return match ($operation) {
            'addState' => $this->about($subjectName, 'Добавляет состояние «' . $this->ruleName($slice, $code) . '».'),
            'patchState' => $this->about($subjectName, 'Меняет состояние «' . $this->ruleName($slice, $code) . '».'),
            'removeState' => $this->about($subjectName, 'Снимает состояние «' . $this->ruleName($slice, $code) . '».'),
            default => $this->about($subjectName, 'Правит лист.'),
        };
    }

    /**
     * Имя цели перед действием.
     *
     * @param string $name Имя.
     * @param string $text Действие.
     *
     * @return string Текст.
     */
    private function about(string $name, string $text): string
    {
        if ($name === '') {
            return $text;
        }

        return $name . ': ' . $text;
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
     * Native current по спеке.
     *
     * @param mixed $current Вход.
     * @param ResourceSpec $spec Карточка.
     *
     * @return int|array{base: int, size: int} Native.
     *
     * @throws GameInvalidException Если форма.
     */
    private function nativeCurrent(mixed $current, ResourceSpec $spec): int|array
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

    /**
     * Имя правила или код.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $code Код.
     *
     * @return string Имя.
     */
    private function ruleName(CharacterRuleSlice $slice, string $code): string
    {
        $name = $slice->findLive($code)?->getName();

        return is_string($name) && $name !== '' ? $name : $code;
    }

    /**
     * Текущее native ресурса.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param string $code Код.
     *
     * @return mixed Current.
     */
    private function resourceCurrent(array $sheet, string $code): mixed
    {
        $rows = $sheet['resources'] ?? null;
        if (!is_array($rows)) {
            return null;
        }

        foreach ($rows as $row) {
            if (is_array($row) && ($row['ruleCode'] ?? null) === $code) {
                return $row['current'] ?? null;
            }
        }

        return null;
    }

    /**
     * Код строки по id.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param mixed $id Id.
     *
     * @return string Код.
     */
    private function stateCodeById(array $sheet, mixed $id): string
    {
        $rows = $sheet['states'] ?? null;
        if (!is_array($rows) || !is_int($id)) {
            return '';
        }

        foreach ($rows as $row) {
            if (is_array($row) && ($row['id'] ?? null) === $id && is_string($row['stateRuleCode'] ?? null)) {
                return $row['stateRuleCode'];
            }
        }

        return '';
    }

    /**
     * Короткое представление current.
     *
     * @param mixed $current Native.
     *
     * @return string Текст.
     */
    private function label(mixed $current): string
    {
        if (is_int($current)) {
            return (string) $current;
        }

        if (is_array($current) && is_int($current['base'] ?? null)) {
            return (string) $current['base'];
        }

        return '—';
    }
}
