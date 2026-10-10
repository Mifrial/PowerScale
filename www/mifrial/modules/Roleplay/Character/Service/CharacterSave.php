<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Dto\Action\CreateCharacterInput;
use Mifrial\Roleplay\Character\Dto\Action\UpdateCharacterInput;
use Mifrial\Roleplay\Character\Dto\Action\ValidateCharacterInput;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\NewCharacter;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Exception\CharacterSaveRejectedException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Service\Save\CharacterChoiceAssembler;
use Mifrial\Roleplay\Character\Service\Save\CharacterSaveAssembly;
use Mifrial\Roleplay\Character\Service\Save\CharacterSaveKeys;
use Mifrial\Roleplay\Character\Service\Save\CharacterShopBalance;

/**
 * Create, update и validate-only: один валидатор, запись только без problems.
 */
final class CharacterSave
{
    private const CREATE = 'character.create';

    private readonly CharacterSaveKeys $keys;

    private readonly CharacterChoiceAssembler $choiceAssembler;

    private readonly CharacterSaveAssembly $assembly;

    /**
     * Собирает сценарий.
     *
     * @param IUserAccess $userAccess Права.
     * @param ICharacters $characters Строки.
     * @param ICharacterRuleSlices $ruleSlices Срез.
     * @param ICharacterSheets $sheets Валидатор.
     * @param CharacterShopBalance $shopBalance Шоп create.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly ICharacters $characters,
        private readonly ICharacterRuleSlices $ruleSlices,
        private readonly ICharacterSheets $sheets,
        private readonly CharacterShopBalance $shopBalance,
    ) {
        $this->keys = new CharacterSaveKeys();
        $this->choiceAssembler = new CharacterChoiceAssembler();
        $this->assembly = new CharacterSaveAssembly($ruleSlices, $sheets, $shopBalance);
    }

    /**
     * Создаёт персонажа. actual_version = 1.
     *
     * @param CreateCharacterInput $input JSON.
     *
     * @return array<string, mixed> Лист сервера.
     *
     * @throws ActionException AUTH или INVALID_PARAMS.
     * @throws CharacterSaveRejectedException Если есть problems.
     */
    public function create(CreateCharacterInput $input): array
    {
        $actor = $this->userAccess->requireKey(self::CREATE);
        $this->assertShape($input->limits, $input->abilities, $input->inventory, $input->characteristicPurchases, $input->customRules, $input->expectedSheet, true);
        $built = $this->assembly->build(
            $input->spaceId,
            $input->revision,
            $input->name,
            $input->raceCode,
            $input->abilities,
            $input->inventory,
            $input->characteristicPurchases,
            $input->customRules,
            $input->active,
            $input->limits,
            null,
            true,
            $input->expectedSheet,
            $this->choiceAssembler->buildDocument($input),
        );
        $this->guard($built['problems']);
        $characterId = $this->characters->add($this->newCharacter($actor->getUserId(), $input, $built));

        return $this->savedView($this->characters->get($characterId));
    }

    /**
     * Пишет лист владельца при совпадении actual_version.
     *
     * @param UpdateCharacterInput $input JSON.
     *
     * @return array<string, mixed> Лист сервера.
     *
     * @throws ActionException AUTH или INVALID_PARAMS.
     * @throws CharacterInvalidException Если нет expectedVersion или смена ревизии.
     * @throws CharacterNotFoundException Если лист чужой или его нет.
     * @throws CharacterSaveRejectedException Если есть problems.
     */
    public function update(UpdateCharacterInput $input): array
    {
        $record = $this->owned($input->id, $this->userAccess->requireActor()->getUserId());
        $this->assertUpdateLock($input, $record);
        $built = $this->builtUpdate($input, $record);

        return $this->savedView($this->writeUpdate($record, $built, $input));
    }


    /**
     * Тот же прогон без записи.
     *
     * @param ValidateCharacterInput $input JSON.
     *
     * @return array<string, mixed> valid, problems, sheet.
     *
     * @throws ActionException AUTH или INVALID_PARAMS.
     * @throws CharacterNotFoundException Если лист с id чужой или его нет.
     */
    public function validate(ValidateCharacterInput $input): array
    {
        if ($input->id === null) {
            return $this->validateCreate($input);
        }

        return $this->validateUpdate($input);
    }

    /**
     * Сборка update после проверки ключей. Problems не пишутся.
     *
     * @param UpdateCharacterInput $input JSON.
     * @param CharacterRecord $record Строка.
     *
     * @return array{problems: array<int, CharacterProblem>, choices: array<string, mixed>, sheet: array<string, mixed>, name: string, active: bool}
     * Сборка.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws CharacterSaveRejectedException Если есть problems.
     */
    private function builtUpdate(UpdateCharacterInput $input, CharacterRecord $record): array
    {
        $this->assertShape($input->limits, $input->abilities, $input->inventory, $input->characteristicPurchases, $input->customRules, $input->expectedSheet, false);
        $built = $this->assembly->build(
            $record->getSpaceId(),
            $record->getRulesRevision(),
            $input->name,
            $input->raceCode,
            $input->abilities,
            $input->inventory,
            $input->characteristicPurchases,
            $input->customRules,
            $input->active,
            $input->limits,
            $this->updateMoney($input, $record),
            false,
            $input->expectedSheet,
            $this->choiceAssembler->buildDocument($input),
            $record->getSheet(),
        );
        $built['problems'] = $this->assembly->withClientCash($built['problems'], $input->money);
        $this->guard($built['problems']);

        return $built;
    }

    /**
     * Записывает успешный update одним guard.
     *
     * @param CharacterRecord $record Строка.
     * @param array{name: string, active: bool, choices: array<string, mixed>, sheet: array<string, mixed>} $built Сборка.
     * @param UpdateCharacterInput $input JSON.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterInvalidException Если version меньше 1 или имя.
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterConflictException Если version устарел.
     */
    private function writeUpdate(CharacterRecord $record, array $built, UpdateCharacterInput $input): CharacterRecord
    {
        return $this->characters->replaceSaved(
            $record->getId(),
            $built['name'],
            $built['active'],
            $built['choices'],
            $built['sheet'],
            $this->requiredVersion($input),
        );
    }
    /**
     * Validate без id: правила create.
     *
     * @param ValidateCharacterInput $input JSON.
     *
     * @return array<string, mixed> Отчёт.
     *
     * @throws ActionException AUTH или INVALID_PARAMS.
     */
    private function validateCreate(ValidateCharacterInput $input): array
    {
        $this->userAccess->requireKey(self::CREATE);
        $this->rejectCash($input);
        $this->assertShape($input->limits, $input->abilities, $input->inventory, $input->characteristicPurchases, $input->customRules, $input->expectedSheet, true);

        return $this->report($this->assembly->build(
            $input->spaceId,
            $input->revision,
            $input->name,
            $input->raceCode,
            $input->abilities,
            $input->inventory,
            $input->characteristicPurchases,
            $input->customRules,
            $input->active,
            $input->limits,
            null,
            true,
            $input->expectedSheet,
            $this->choiceAssembler->buildDocument($input),
        ));
    }

    /**
     * Validate с id: правила update и владелец.
     *
     * @param ValidateCharacterInput $input JSON.
     *
     * @return array<string, mixed> Отчёт.
     *
     * @throws ActionException AUTH или INVALID_PARAMS.
     * @throws CharacterNotFoundException Если лист чужой или его нет.
     * @throws CharacterInvalidException Если мир или ревизия не совпали со строкой.
     */
    private function validateUpdate(ValidateCharacterInput $input): array
    {
        $characterId = $input->id;
        if ($characterId === null) {
            throw new CharacterNotFoundException();
        }

        $record = $this->owned($characterId, $this->userAccess->requireActor()->getUserId());
        $this->assertSameRevision($input->spaceId, $input->revision, $record);
        $this->assertShape($input->limits, $input->abilities, $input->inventory, $input->characteristicPurchases, $input->customRules, $input->expectedSheet, false);

        $built = $this->assembly->build(
            $record->getSpaceId(),
            $record->getRulesRevision(),
            $input->name,
            $input->raceCode,
            $input->abilities,
            $input->inventory,
            $input->characteristicPurchases,
            $input->customRules,
            $input->active,
            $input->limits,
            $this->keptMoney($input->money->isPresent() ? $input->money->getValue() : null, $record),
            false,
            $input->expectedSheet,
            $this->choiceAssembler->buildDocument($input),
            $record->getSheet(),
        );
        $built['problems'] = $this->assembly->withClientCash($built['problems'], $input->money);

        return $this->report($built);
    }

    /**
     * Наличные update: ключ есть — он, иначе остаток sheet.
     *
     * @param UpdateCharacterInput $input JSON.
     * @param CharacterRecord $record Строка.
     *
     * @return int Остаток.
     *
     * @throws ActionException Если присланный money отрицательный тип уже отсёк биндер; null — 0.
     */
    private function updateMoney(UpdateCharacterInput $input, CharacterRecord $record): int
    {
        if (!$input->money->isPresent()) {
            return $this->sheetMoney($record);
        }

        return $this->keptMoney($input->money->getValue(), $record);
    }

    /**
     * Присланное целое или прежний остаток, если ключ был null.
     *
     * @param int|null $money Значение ключа.
     * @param CharacterRecord $record Строка.
     *
     * @return int Остаток.
     */
    private function keptMoney(?int $money, CharacterRecord $record): int
    {
        if ($money === null) {
            return $this->sheetMoney($record);
        }

        return $money;
    }

    /**
     * money из сохранённого sheet. Любое целое, в том числе отрицательное.
     *
     * @param CharacterRecord $record Строка.
     *
     * @return int Остаток или 0.
     */
    private function sheetMoney(CharacterRecord $record): int
    {
        $money = $record->getSheet()['money'] ?? 0;

        return is_int($money) ? $money : 0;
    }

    /**
     * expectedVersion обязателен на update.
     *
     * @param UpdateCharacterInput $input JSON.
     *
     * @return int Версия.
     *
     * @throws CharacterInvalidException Если ключа нет.
     */
    private function requiredVersion(UpdateCharacterInput $input): int
    {
        if ($input->expectedVersion === null) {
            throw new CharacterInvalidException('Character expected version is required');
        }

        return $input->expectedVersion;
    }

    /**
     * Нет expectedVersion или смена мира — CHARACTER_INVALID до валидатора.
     *
     * @param UpdateCharacterInput $input JSON.
     * @param CharacterRecord $record Строка.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если lock или ревизия.
     */
    private function assertUpdateLock(UpdateCharacterInput $input, CharacterRecord $record): void
    {
        $this->requiredVersion($input);
        $this->assertSameRevision($input->spaceId, $input->revision, $record);
    }

    /**
     * Update не меняет мир и ревизию строки.
     *
     * @param int $spaceId Мир из тела.
     * @param int $revision Ревизия из тела.
     * @param CharacterRecord $record Строка.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если мир или ревизия другие.
     */
    private function assertSameRevision(int $spaceId, int $revision, CharacterRecord $record): void
    {
        if ($spaceId !== $record->getSpaceId() || $revision !== $record->getRulesRevision()) {
            throw new CharacterInvalidException('Character revision cannot change');
        }
    }

    /**
     * Наличные на validate без id запрещены.
     *
     * @param ValidateCharacterInput $input JSON.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function rejectCash(ValidateCharacterInput $input): void
    {
        if ($input->money->isPresent()) {
            throw new ActionException('INVALID_PARAMS', 'Unknown parameter: money');
        }
    }

    /**
     * Вложенные ключи.
     *
     * @param array<mixed> $limits Лимиты.
     * @param array<mixed> $abilities Способности.
     * @param array<mixed> $inventory Инвентарь.
     * @param array<mixed> $purchases Закупки.
     * @param array<mixed> $customRules Свои правила.
     * @param array<mixed>|null $expectedSheet Сверка.
     * @param bool $allowLimitMoney Ключ limits.money.
     *
     * @return void
     *
     * @throws ActionException INVALID_PARAMS.
     */
    private function assertShape(
        array $limits,
        array $abilities,
        array $inventory,
        array $purchases,
        array $customRules,
        ?array $expectedSheet,
        bool $allowLimitMoney,
    ): void {
        $this->keys->assertLimits($limits, $allowLimitMoney);
        $this->keys->assertLists($abilities, $inventory, $purchases, $customRules);
        $this->keys->assertExpectedSheet($expectedSheet);
    }

    /**
     * Problems запрещают запись.
     *
     * @param array<int, CharacterProblem> $problems Отказы.
     *
     * @return void
     *
     * @throws CharacterSaveRejectedException Если список не пуст.
     */
    private function guard(array $problems): void
    {
        if ($problems !== []) {
            throw new CharacterSaveRejectedException($problems);
        }
    }

    /**
     * Отчёт validate-only.
     *
     * @param array{problems: array<int, CharacterProblem>, sheet: array<string, mixed>} $built Сборка.
     *
     * @return array<string, mixed> 200-тело.
     */
    private function report(array $built): array
    {
        return [
            'valid' => $built['problems'] === [],
            'problems' => CharacterSaveRejectedException::rows($built['problems']),
            'sheet' => $built['sheet'],
        ];
    }

    /**
     * Ответ save: строка сервера, не тело клиента.
     *
     * @param CharacterRecord $record После записи.
     *
     * @return array<string, mixed> Лист.
     */
    private function savedView(CharacterRecord $record): array
    {
        return [
            'id' => $record->getId(),
            'name' => $record->getName(),
            'spaceId' => $record->getSpaceId(),
            'revision' => $record->getRulesRevision(),
            'active' => $record->isActive(),
            'actualVersion' => $record->getActualVersion(),
            'choices' => $record->getChoices(),
            'sheet' => $record->getSheet(),
            'validation' => ['valid' => true, 'problems' => []],
        ];
    }

    /**
     * Строка только владельца.
     *
     * @param int $characterId Идентификатор.
     * @param int $actorUserId Актор.
     *
     * @return CharacterRecord Строка.
     *
     * @throws CharacterNotFoundException Если нет или владелец другой.
     */
    private function owned(int $characterId, int $actorUserId): CharacterRecord
    {
        $record = $this->characters->get($characterId);
        if ($record->getOwnerId() !== $actorUserId) {
            throw new CharacterNotFoundException();
        }

        return $record;
    }

    /**
     * Поля insert create.
     *
     * @param int $ownerUserId Владелец.
     * @param CreateCharacterInput $input JSON.
     * @param array{name: string, active: bool, choices: array<string, mixed>, sheet: array<string, mixed>} $built Сборка.
     *
     * @return NewCharacter Поля.
     *
     * @throws CharacterInvalidException Если типы New.
     */
    private function newCharacter(int $ownerUserId, CreateCharacterInput $input, array $built): NewCharacter
    {
        return NewCharacter::fromNormalized([
            'ownerUserId' => $ownerUserId,
            'spaceId' => $input->spaceId,
            'rulesRevision' => $input->revision,
            'name' => $built['name'],
            'choices' => $built['choices'],
            'sheet' => $built['sheet'],
            'visibilityFields' => [],
            'ownerNotes' => '',
            'active' => $built['active'],
        ]);
    }

}
