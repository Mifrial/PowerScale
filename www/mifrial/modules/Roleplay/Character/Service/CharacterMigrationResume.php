<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Value\Optional\OptionalString;
use Mifrial\Roleplay\Character\Dto\Action\MigrateCharacterInput;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;

/**
 * Тело продолжения миграции. Ремап не делает.
 */
final class CharacterMigrationResume
{
    /**
     * Хотя бы одно поле листа прислано.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return bool true, если это продолжение.
     */
    public function isStarted(MigrateCharacterInput $input): bool
    {
        return $this->identityStarted($input) || $this->listsStarted($input) || $this->notesStarted($input);
    }

    /**
     * Полное тело или отказ.
     *
     * @param MigrateCharacterInput $input JSON.
     * @param CharacterRecord $record Строка.
     *
     * @return array{
     *     changed: bool,
     *     name: string,
     *     raceCode: string,
     *     abilities: array<mixed>,
     *     inventory: array<mixed>,
     *     characteristicPurchases: array<mixed>,
     *     customRules: array<mixed>,
     *     active: bool|null,
     *     limits: array<mixed>,
     *     money: int,
     *     expectedSheet: array<mixed>|null,
     *     choices: array<string, mixed>
     * } Поля сборки.
     *
     * @throws CharacterInvalidException Если набор неполный.
     */
    public function prepare(MigrateCharacterInput $input, CharacterRecord $record): array
    {
        if (!$this->isReady($input)) {
            throw new CharacterInvalidException('Character migration body is incomplete');
        }

        $name = $input->name->getValue() ?? '';
        $raceCode = $input->raceCode->getValue() ?? '';
        $choices = $this->choices($input, $name, $raceCode);

        return [
            'changed' => true,
            'name' => $name,
            'raceCode' => $raceCode,
            'abilities' => $choices['abilities'],
            'inventory' => $choices['inventory'],
            'characteristicPurchases' => $choices['characteristicPurchases'],
            'customRules' => $choices['customRules'],
            'active' => $input->active->isPresent() ? $input->active->getValue() : null,
            'limits' => $choices['limits'],
            'money' => $this->money($input, $record),
            'expectedSheet' => $input->expectedSheet->isPresent() ? $input->expectedSheet->getValue() : null,
            'choices' => $choices,
        ];
    }

    /**
     * Имя, потолки или раса присланы.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return bool true, если ключ есть.
     */
    private function identityStarted(MigrateCharacterInput $input): bool
    {
        return $input->name->isPresent() || $input->limits->isPresent() || $input->raceCode->isPresent();
    }

    /**
     * Список листа прислан.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return bool true, если ключ есть.
     */
    private function listsStarted(MigrateCharacterInput $input): bool
    {
        return $input->abilities->isPresent()
            || $input->inventory->isPresent()
            || $input->characteristicPurchases->isPresent()
            || $input->customRules->isPresent();
    }

    /**
     * Описание, деньги или сверка присланы.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return bool true, если ключ есть.
     */
    private function notesStarted(MigrateCharacterInput $input): bool
    {
        return $this->textStarted($input) || $input->active->isPresent() || $input->expectedSheet->isPresent();
    }

    /**
     * Деньги, описания или возраст присланы.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return bool true, если ключ есть.
     */
    private function textStarted(MigrateCharacterInput $input): bool
    {
        return $input->money->isPresent()
            || $input->shortDescription->isPresent()
            || $input->fullDescription->isPresent()
            || $input->ageYears->isPresent();
    }

    /**
     * Обязательный набор продолжения полон.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return bool true, если можно собирать.
     */
    private function isReady(MigrateCharacterInput $input): bool
    {
        return $this->identityReady($input) && $this->listsReady($input);
    }

    /**
     * Имя, потолки и раса присланы.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return bool true, если ключи есть.
     */
    private function identityReady(MigrateCharacterInput $input): bool
    {
        return $input->name->isPresent() && $input->limits->isPresent() && $input->raceCode->isPresent();
    }

    /**
     * Списки листа присланы.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return bool true, если ключи есть.
     */
    private function listsReady(MigrateCharacterInput $input): bool
    {
        return $input->abilities->isPresent()
            && $input->inventory->isPresent()
            && $input->characteristicPurchases->isPresent()
            && $input->customRules->isPresent();
    }

    /**
     * Документ choices из тела.
     *
     * @param MigrateCharacterInput $input JSON.
     * @param string $name Имя.
     * @param string $raceCode Раса.
     *
     * @return array<string, mixed> Choices.
     */
    private function choices(MigrateCharacterInput $input, string $name, string $raceCode): array
    {
        return [
            'name' => $name,
            'shortDescription' => $this->text($input->shortDescription),
            'fullDescription' => $this->text($input->fullDescription),
            'ageYears' => $input->ageYears->isPresent() ? $input->ageYears->getValue() : null,
            'limits' => $input->limits->getValue(),
            'raceCode' => $raceCode,
            'characteristicPurchases' => $input->characteristicPurchases->getValue(),
            'abilities' => $input->abilities->getValue(),
            'inventory' => $input->inventory->getValue(),
            'customRules' => $input->customRules->getValue(),
            'active' => $input->active->isPresent() ? $input->active->getValue() : true,
        ];
    }

    /**
     * Строка optional или пустая.
     *
     * @param OptionalString $value Поле.
     *
     * @return string Текст.
     */
    private function text(OptionalString $value): string
    {
        if (!$value->isPresent() || $value->getValue() === null) {
            return '';
        }

        return $value->getValue();
    }

    /**
     * Наличные: ключ или остаток sheet.
     *
     * @param MigrateCharacterInput $input JSON.
     * @param CharacterRecord $record Строка.
     *
     * @return int Остаток.
     */
    private function money(MigrateCharacterInput $input, CharacterRecord $record): int
    {
        if (!$input->money->isPresent() || $input->money->getValue() === null) {
            $money = $record->getSheet()['money'] ?? 0;

            return is_int($money) ? $money : 0;
        }

        return $input->money->getValue();
    }
}
