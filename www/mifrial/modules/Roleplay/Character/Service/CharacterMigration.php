<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Service;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Dto\Action\MigrateCharacterInput;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Exception\CharacterSaveRejectedException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Service\Save\CharacterSaveAssembly;
use Mifrial\Roleplay\Character\Service\Save\CharacterSaveKeys;

/**
 * Перевод листа на другую ревизию того же мира. Create и update не меняет.
 */
final class CharacterMigration
{
    private readonly CharacterSaveKeys $keys;

    private readonly CharacterRevisionRemap $remap;

    private readonly CharacterMigrationResume $resume;

    /**
     * Собирает сценарий.
     *
     * @param IUserAccess $userAccess Права.
     * @param ICharacters $characters Строки.
     * @param ICharacterRuleSlices $ruleSlices Срезы мира.
     * @param CharacterSaveAssembly $assembly Тот же прогон, что save.
     *
     * @return void
     */
    public function __construct(
        private readonly IUserAccess $userAccess,
        private readonly ICharacters $characters,
        private readonly ICharacterRuleSlices $ruleSlices,
        private readonly CharacterSaveAssembly $assembly,
    ) {
        $this->keys = new CharacterSaveKeys();
        $this->remap = new CharacterRevisionRemap();
        $this->resume = new CharacterMigrationResume();
    }

    /**
     * Ремапит или принимает тело редактора. Пишет только без problems.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return array<string, mixed> kind и лист либо conflicts.
     *
     * @throws ActionException AUTH или INVALID_PARAMS.
     * @throws CharacterInvalidException Если ревизия та же, version или тело неполное.
     * @throws CharacterNotFoundException Если лист чужой, его нет или нет ревизии.
     * @throws CharacterConflictException Если version устарел.
     */
    public function migrate(MigrateCharacterInput $input): array
    {
        $record = $this->owned($input->id, $this->userAccess->requireActor()->getUserId());
        $expectedVersion = $this->requiredVersion($input);
        $this->assertTargetRevision($input->revision, $record);
        $prepared = $this->prepare($input, $record);
        $built = $this->build($record, $input, $prepared);
        if ($built['problems'] !== []) {
            return $this->conflicts($input->revision, $built);
        }

        return $this->savedView(
            $this->write($record, $built, $input->revision, $expectedVersion),
            $prepared['changed'] ? 'resolved' : 'ok',
        );
    }

    /**
     * Тот же прогон, что update: без шопа.
     *
     * @param CharacterRecord $record Строка.
     * @param MigrateCharacterInput $input JSON.
     * @param array{name: string, raceCode: string, abilities: array<mixed>, inventory: array<mixed>, characteristicPurchases: array<mixed>, customRules: array<mixed>, active: bool|null, limits: array<mixed>, money: int, expectedSheet: array<mixed>|null, choices: array<string, mixed>} $prepared Поля.
     *
     * @return array{problems: array<int, CharacterProblem>, choices: array<string, mixed>, sheet: array<string, mixed>, name: string, active: bool} Сборка.
     *
     * @throws ActionException INVALID_PARAMS.
     * @throws CharacterNotFoundException Если нет целевой ревизии.
     */
    private function build(CharacterRecord $record, MigrateCharacterInput $input, array $prepared): array
    {
        $this->keys->assertLimits($prepared['limits'], false);
        $this->keys->assertLists(
            $prepared['abilities'],
            $prepared['inventory'],
            $prepared['characteristicPurchases'],
            $prepared['customRules'],
        );
        $this->keys->assertExpectedSheet($prepared['expectedSheet']);
        $built = $this->assembly->build(
            $record->getSpaceId(),
            $input->revision,
            $prepared['name'],
            $prepared['raceCode'],
            $prepared['abilities'],
            $prepared['inventory'],
            $prepared['characteristicPurchases'],
            $prepared['customRules'],
            $prepared['active'],
            $prepared['limits'],
            $prepared['money'],
            false,
            $prepared['expectedSheet'],
            $prepared['choices'],
        );
        $built['problems'] = $this->assembly->withClientCash($built['problems'], $input->money);

        return $built;
    }

    /**
     * Пишет успешный перевод.
     *
     * @param CharacterRecord $record Строка.
     * @param array{name: string, active: bool, choices: array<string, mixed>, sheet: array<string, mixed>} $built Сборка.
     * @param int $revision Цель.
     * @param int $expectedVersion Lock.
     *
     * @return CharacterRecord После записи.
     *
     * @throws CharacterInvalidException Если имя или ревизия.
     * @throws CharacterNotFoundException Если строки нет.
     * @throws CharacterConflictException Если version устарел.
     */
    private function write(CharacterRecord $record, array $built, int $revision, int $expectedVersion): CharacterRecord
    {
        return $this->characters->replaceMigrated(
            $record->getId(),
            $built['name'],
            $built['active'],
            $built['choices'],
            $built['sheet'],
            $revision,
            $expectedVersion,
        );
    }

    /**
     * Ремап actual или тело продолжения.
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
     * @throws CharacterInvalidException Если продолжение неполное.
     * @throws CharacterNotFoundException Если нет ревизии.
     */
    private function prepare(MigrateCharacterInput $input, CharacterRecord $record): array
    {
        if ($this->resume->isStarted($input)) {
            return $this->resume->prepare($input, $record);
        }

        return $this->remapped($input, $record);
    }

    /**
     * Choices из actual на целевой ревизии.
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
     *     expectedSheet: null,
     *     choices: array<string, mixed>
     * } Поля сборки.
     *
     * @throws CharacterNotFoundException Если нет ревизии.
     */
    private function remapped(MigrateCharacterInput $input, CharacterRecord $record): array
    {
        $stored = $record->getChoices();
        $source = $this->ruleSlices->get($record->getSpaceId(), $record->getRulesRevision());
        $target = $this->ruleSlices->get($record->getSpaceId(), $input->revision);
        $remapped = $this->remap->remap($stored, $source, $target);
        $choices = $remapped['choices'];

        return [
            'changed' => $remapped['changed'],
            'name' => $this->stringOf($choices, 'name', $record->getName()),
            'raceCode' => $this->stringOf($choices, 'raceCode', ''),
            'abilities' => $this->listOf($choices, 'abilities'),
            'inventory' => $this->listOf($choices, 'inventory'),
            'characteristicPurchases' => $this->listOf($choices, 'characteristicPurchases'),
            'customRules' => $this->listOf($choices, 'customRules'),
            'active' => $this->activeOf($choices, $record->isActive()),
            'limits' => $this->listOf($choices, 'limits'),
            'money' => $this->sheetMoney($record),
            'expectedSheet' => null,
            'choices' => $this->choiceDocument($choices, $record),
        ];
    }

    /**
     * money из сохранённого sheet.
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
     * Документ choices для сборки. Наличные дописывает сборка.
     *
     * @param array<string, mixed> $choices После ремапа.
     * @param CharacterRecord $record Строка.
     *
     * @return array<string, mixed> Choices.
     */
    private function choiceDocument(array $choices, CharacterRecord $record): array
    {
        return [
            'name' => $this->stringOf($choices, 'name', $record->getName()),
            'shortDescription' => $this->stringOf($choices, 'shortDescription', ''),
            'fullDescription' => $this->stringOf($choices, 'fullDescription', ''),
            'ageYears' => is_int($choices['ageYears'] ?? null) ? $choices['ageYears'] : null,
            'limits' => $this->listOf($choices, 'limits'),
            'raceCode' => $this->stringOf($choices, 'raceCode', ''),
            'characteristicPurchases' => $this->listOf($choices, 'characteristicPurchases'),
            'abilities' => $this->listOf($choices, 'abilities'),
            'inventory' => $this->listOf($choices, 'inventory'),
            'customRules' => $this->listOf($choices, 'customRules'),
            'active' => $this->activeOf($choices, $record->isActive()) ?? true,
        ];
    }

    /**
     * Ответ conflicts без записи.
     *
     * @param int $revision Целевая ревизия.
     * @param array{problems: array<int, CharacterProblem>, choices: array<string, mixed>, sheet: array<string, mixed>} $built Сборка.
     *
     * @return array<string, mixed> Отчёт.
     */
    private function conflicts(int $revision, array $built): array
    {
        return [
            'kind' => 'conflicts',
            'problems' => CharacterSaveRejectedException::rows($built['problems']),
            'revision' => $revision,
            'choices' => $built['choices'],
            'sheet' => $built['sheet'],
        ];
    }

    /**
     * Ответ записи. Ключи как у save, плюс kind.
     *
     * @param CharacterRecord $record После записи.
     * @param string $kind ok или resolved.
     *
     * @return array<string, mixed> Лист.
     */
    private function savedView(CharacterRecord $record, string $kind): array
    {
        return [
            'kind' => $kind,
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
     * expectedVersion обязателен.
     *
     * @param MigrateCharacterInput $input JSON.
     *
     * @return int Версия.
     *
     * @throws CharacterInvalidException Если ключа нет.
     */
    private function requiredVersion(MigrateCharacterInput $input): int
    {
        if ($input->expectedVersion === null) {
            throw new CharacterInvalidException('Character expected version is required');
        }

        return $input->expectedVersion;
    }

    /**
     * Цель — другая ревизия того же мира.
     *
     * @param int $revision Цель.
     * @param CharacterRecord $record Строка.
     *
     * @return void
     *
     * @throws CharacterInvalidException Если номер тот же.
     */
    private function assertTargetRevision(int $revision, CharacterRecord $record): void
    {
        if ($revision === $record->getRulesRevision()) {
            throw new CharacterInvalidException('Character revision cannot stay');
        }
    }

    /**
     * Строка choices.
     *
     * @param array<string, mixed> $choices Документ.
     * @param string $key Ключ.
     * @param string $fallback Замена.
     *
     * @return string Текст.
     */
    private function stringOf(array $choices, string $key, string $fallback): string
    {
        $value = $choices[$key] ?? null;

        return is_string($value) ? $value : $fallback;
    }

    /**
     * Список choices.
     *
     * @param array<string, mixed> $choices Документ.
     * @param string $key Ключ.
     *
     * @return array<mixed> Список.
     */
    private function listOf(array $choices, string $key): array
    {
        $value = $choices[$key] ?? [];

        return is_array($value) ? $value : [];
    }

    /**
     * active из choices или строки.
     *
     * @param array<string, mixed> $choices Документ.
     * @param bool $fallback Флаг строки.
     *
     * @return bool|null Флаг.
     */
    private function activeOf(array $choices, bool $fallback): ?bool
    {
        if (!array_key_exists('active', $choices)) {
            return $fallback;
        }

        return is_bool($choices['active']) ? $choices['active'] : $fallback;
    }
}
