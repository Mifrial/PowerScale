<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterAbilityGrantReader;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\ItemGrant;
use Mifrial\Roleplay\Rule\Dto\Spec\MagicPath\MagicPathSpec;

/**
 * Принадлежность источника каста и его доступность при поддержании.
 */
final class GameSpellSource
{
    /**
     * Создаёт проверки источника.
     *
     * @param CharacterAbilityGrantReader $grants Гранты листа.
     *
     * @return void
     */
    public function __construct(private readonly CharacterAbilityGrantReader $grants)
    {
    }

    /**
     * Источник принадлежит кастеру в момент каста.
     *
     * @param array<string, mixed> $source Нормализованный источник тела.
     * @param array<string, mixed> $choices Документ choices.
     * @param array<string, mixed> $sheet Пересчитанный лист.
     * @param MagicPathSpec $path Путь каста.
     * @param CharacterRuleSlice $slice Срез ревизии.
     *
     * @return void
     *
     * @throws GameInvalidException Если источник чужой.
     */
    public function assertOwned(
        array $source,
        array $choices,
        array $sheet,
        MagicPathSpec $path,
        CharacterRuleSlice $slice,
    ): void {
        if (!$this->isOwned($source, $choices, $sheet, $path, $slice)) {
            throw new GameInvalidException('Game spell power source is invalid');
        }
    }

    /**
     * Источник ещё доступен для поддержания.
     *
     * @param string $sourceKey Ключ вида inventory:9, grant:x или characteristic:y.
     * @param array<string, mixed> $choices Документ choices.
     * @param array<string, mixed> $sheet Пересчитанный лист.
     * @param MagicPathSpec $path Путь каста.
     * @param CharacterRuleSlice $slice Срез ревизии.
     *
     * @return bool true, если источник на месте.
     */
    public function isAvailable(
        string $sourceKey,
        array $choices,
        array $sheet,
        MagicPathSpec $path,
        CharacterRuleSlice $slice,
    ): bool {
        [$kind, $value] = $this->split($sourceKey);

        return match ($kind) {
            'inventory' => $this->hasInventory($choices, (int) $value, true),
            'grant' => $this->hasGrantSource($sheet, $this->model($choices), $value, $slice),
            'characteristic' => $this->hasCharacteristicSource($sheet, $path, $value),
            default => false,
        };
    }

    /**
     * Проверяет принадлежность по нормализованному источнику.
     *
     * @param array<string, mixed> $source Источник.
     * @param array<string, mixed> $choices Choices.
     * @param array<string, mixed> $sheet Лист.
     * @param MagicPathSpec $path Путь.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return bool Наличие.
     */
    private function isOwned(
        array $source,
        array $choices,
        array $sheet,
        MagicPathSpec $path,
        CharacterRuleSlice $slice,
    ): bool {
        $kind = $source['kind'] ?? null;
        if ($kind === 'characteristic') {
            return ($source['code'] ?? null) === $path->getPowerCharacteristicCode();
        }

        if ($kind === 'item') {
            $inventoryId = $source['inventoryId'] ?? null;

            return is_int($inventoryId) && $this->hasInventory($choices, $inventoryId, false);
        }

        if ($kind === 'grant') {
            $abilityCode = $source['abilityCode'] ?? null;

            return is_string($abilityCode) && $this->hasGrantSource($sheet, $this->model($choices), $abilityCode, $slice);
        }

        return false;
    }

    /**
     * Строка инвентаря кастера.
     *
     * @param array<string, mixed> $choices Choices.
     * @param int $inventoryId Id строки.
     * @param bool $requireEquipped Требовать признак «надет».
     *
     * @return bool Наличие.
     */
    private function hasInventory(array $choices, int $inventoryId, bool $requireEquipped): bool
    {
        if ($inventoryId < 1) {
            return false;
        }

        foreach (is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [] as $row) {
            if (!is_array($row) || ($row['id'] ?? null) !== $inventoryId) {
                continue;
            }

            return $requireEquipped ? ($row['equipped'] ?? false) === true : true;
        }

        return false;
    }

    /**
     * Активная способность выдаёт предмет-источник.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param CharacterChoices $model Выборы.
     * @param string $abilityCode Код способности.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return bool Наличие.
     */
    private function hasGrantSource(
        array $sheet,
        CharacterChoices $model,
        string $abilityCode,
        CharacterRuleSlice $slice,
    ): bool {
        if ($abilityCode === '') {
            return false;
        }

        $rawLevels = is_array($sheet['abilityLevels'] ?? null) ? $sheet['abilityLevels'] : [];
        $levels = [];
        foreach ($rawLevels as $code => $level) {
            if (is_string($code) && is_int($level)) {
                $levels[$code] = $level;
            }
        }

        if (($levels[$abilityCode] ?? 0) < 1) {
            return false;
        }

        foreach ($this->grants->grants($slice, $model, $levels) as $row) {
            if ($row->getDonorCode() === $abilityCode && $row->getGrant() instanceof ItemGrant) {
                return true;
            }
        }

        return false;
    }

    /**
     * Характеристика пути есть в листе.
     *
     * @param array<string, mixed> $sheet Лист.
     * @param MagicPathSpec $path Путь.
     * @param string $code Код из ключа.
     *
     * @return bool Наличие.
     */
    private function hasCharacteristicSource(array $sheet, MagicPathSpec $path, string $code): bool
    {
        if ($code === '' || $code !== $path->getPowerCharacteristicCode()) {
            return false;
        }

        foreach (is_array($sheet['characteristics'] ?? null) ? $sheet['characteristics'] : [] as $row) {
            if (is_array($row) && ($row['characteristicCode'] ?? null) === $code) {
                return true;
            }
        }

        return false;
    }

    /**
     * Документ choices в модель.
     *
     * @param array<string, mixed> $choices Документ.
     *
     * @return CharacterChoices Выборы.
     */
    private function model(array $choices): CharacterChoices
    {
        return (new CharacterChoiceAssembler())->assemble(
            is_string($choices['name'] ?? null) ? $choices['name'] : '',
            is_string($choices['raceCode'] ?? null) ? $choices['raceCode'] : '',
            is_array($choices['abilities'] ?? null) ? $choices['abilities'] : [],
            is_array($choices['inventory'] ?? null) ? $choices['inventory'] : [],
            is_array($choices['characteristicPurchases'] ?? null) ? $choices['characteristicPurchases'] : [],
            is_array($choices['customRules'] ?? null) ? $choices['customRules'] : [],
            is_bool($choices['active'] ?? null) ? $choices['active'] : null,
        );
    }

    /**
     * Делит ключ источника.
     *
     * @param string $sourceKey Ключ.
     *
     * @return array{0: string, 1: string} Вид и значение.
     */
    private function split(string $sourceKey): array
    {
        $parts = explode(':', $sourceKey, 2);
        if (count($parts) !== 2) {
            return ['', ''];
        }

        return [$parts[0], $parts[1]];
    }
}
