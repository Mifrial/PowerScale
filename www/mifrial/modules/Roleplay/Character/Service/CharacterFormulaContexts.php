<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Разбор abilityLevels и characteristicPurchases в FormulaContext.
 */
final class CharacterFormulaContexts implements ICharacterFormulaContexts
{
    /**
     * Создаёт разбор.
     *
     * @param ICharacters $characters Строка персонажа.
     *
     * @return void
     */
    public function __construct(
        private readonly ICharacters $characters,
    ) {
    }

    /**
     * Собирает контекст из документа sheet.
     *
     * @param array<string, mixed> $sheet Снимок листа.
     * @param array<string, int> $parameters Параметры формул.
     *
     * @return FormulaContext Контекст без баз действий.
     *
     * @throws CharacterInvalidException Если документ битый.
     */
    public function build(array $sheet, array $parameters = []): FormulaContext
    {
        return new FormulaContext(
            $this->characteristics($sheet),
            $this->abilityLevels($sheet),
            $parameters,
        );
    }

    /**
     * Собирает контекст из sheet строки персонажа.
     *
     * @param int $characterId Идентификатор.
     *
     * @return FormulaContext Контекст без параметров и баз действий.
     *
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterInvalidException Если документ битый.
     */
    public function buildStored(int $characterId): FormulaContext
    {
        return $this->build($this->characters->get($characterId)->getSheet());
    }

    /**
     * Уровни способностей из снимка.
     *
     * @param array<string, mixed> $sheet Снимок.
     *
     * @return array<string, int> Код → уровень.
     *
     * @throws CharacterInvalidException Если карта битая.
     */
    private function abilityLevels(array $sheet): array
    {
        $levels = $sheet['abilityLevels'] ?? null;
        if (!is_array($levels)) {
            $this->reject();
        }

        $result = [];
        foreach ($levels as $code => $level) {
            if (!is_string($code) || $code === '' || !is_int($level)) {
                $this->reject();
            }

            $result[$code] = $level;
        }

        return $result;
    }

    /**
     * Характеристики из закупок снимка.
     *
     * @param array<string, mixed> $sheet Снимок.
     *
     * @return array<string, DimensionalNumber> Код → пара.
     *
     * @throws CharacterInvalidException Если список битый.
     */
    private function characteristics(array $sheet): array
    {
        $rows = $sheet['characteristicPurchases'] ?? null;
        if (!is_array($rows)) {
            $this->reject();
        }

        $seen = [];
        $result = [];
        foreach ($rows as $row) {
            $code = $this->purchaseCode($row, $seen);
            $result[$code] = $this->purchaseValue($row);
        }

        return $result;
    }

    /**
     * Код закупки. Повтор отклоняется.
     *
     * @param mixed $row Строка.
     * @param array<string, true> $seen Уже виденные коды.
     *
     * @return string Код.
     *
     * @throws CharacterInvalidException Если строки нет или код повторный.
     */
    private function purchaseCode(mixed $row, array &$seen): string
    {
        if (!is_array($row)) {
            $this->reject();
        }

        $code = $row['characteristicCode'] ?? null;
        if (!is_string($code) || $code === '' || isset($seen[$code])) {
            $this->reject();
        }

        $seen[$code] = true;

        return $code;
    }

    /**
     * Пара базы и размера.
     *
     * @param array<mixed> $row Строка закупки.
     *
     * @return DimensionalNumber Пара.
     *
     * @throws CharacterInvalidException Если value битый.
     */
    private function purchaseValue(array $row): DimensionalNumber
    {
        $value = $row['value'] ?? null;
        if (!is_array($value)) {
            $this->reject();
        }

        $base = $value['base'] ?? null;
        $size = $value['size'] ?? null;
        if (!is_int($base) || !is_int($size)) {
            $this->reject();
        }

        return new DimensionalNumber($base, $size);
    }

    /**
     * Отказ до сборки контекста.
     *
     * @return never
     *
     * @throws CharacterInvalidException Всегда.
     */
    private function reject(): never
    {
        throw new CharacterInvalidException('Character formula context is invalid');
    }
}
