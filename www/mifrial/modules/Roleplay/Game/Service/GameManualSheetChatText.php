<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\ResourceSpec;

/**
 * Текст сообщения чата для ручной правки листа.
 */
final class GameManualSheetChatText
{
    /**
     * Создаёт вывод текста.
     *
     * @param GameManualSheetOperations $operations Разбор значений операции.
     *
     * @return void
     */
    public function __construct(private readonly GameManualSheetOperations $operations)
    {
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
        return $this->about($subjectName, $this->body($operation, $details, $slice, $beforeSheet));
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
     * Действие без имени цели.
     *
     * @param string $operation Код.
     * @param array<string, mixed> $details Поля.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $beforeSheet Лист до.
     *
     * @return string Текст.
     */
    private function body(string $operation, array $details, CharacterRuleSlice $slice, array $beforeSheet): string
    {
        return match ($operation) {
            'setResource' => $this->resourceText($details, $slice, $beforeSheet),
            'setEquipped' => ($details['equipped'] ?? false) === true ? 'Экипирует предмет.' : 'Снимает предмет.',
            'setOccupyHands' => 'Меняет занятость рук предмета.',
            default => $this->stateText($operation, $details, $slice, $beforeSheet),
        };
    }

    /**
     * Текст правки ресурса.
     *
     * @param array<string, mixed> $details Поля.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $beforeSheet Лист до.
     *
     * @return string Текст.
     */
    private function resourceText(array $details, CharacterRuleSlice $slice, array $beforeSheet): string
    {
        $code = is_string($details['resourceCode'] ?? null) ? $details['resourceCode'] : '';
        $before = $this->resourceCurrent($beforeSheet, $code);
        $after = $details['current'] ?? null;
        $spec = $slice->findLive($code)?->getSpec();
        if ($spec instanceof ResourceSpec) {
            try {
                $after = $this->operations->nativeCurrent($after, $spec);
            } catch (GameInvalidException) {
                $after = $details['current'] ?? null;
            }
        }

        return 'Задаёт ' . $this->ruleName($slice, $code) . ': '
            . $this->label($before) . ' → ' . $this->label($after) . '.';
    }

    /**
     * Текст правки состояния.
     *
     * @param string $operation Код.
     * @param array<string, mixed> $details Поля.
     * @param CharacterRuleSlice $slice Срез.
     * @param array<string, mixed> $beforeSheet Лист до.
     *
     * @return string Текст.
     */
    private function stateText(string $operation, array $details, CharacterRuleSlice $slice, array $beforeSheet): string
    {
        $code = is_string($details['stateRuleCode'] ?? null)
            ? $details['stateRuleCode']
            : $this->stateCodeById($beforeSheet, $details['id'] ?? null);
        $action = match ($operation) {
            'addState' => 'Добавляет',
            'patchState' => 'Меняет',
            'removeState' => 'Снимает',
            default => '',
        };
        if ($action === '') {
            return 'Правит лист.';
        }

        return $action . ' состояние «' . $this->ruleName($slice, $code) . '».';
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
