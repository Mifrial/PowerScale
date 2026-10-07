<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Kernel\Value\Optional\OptionalArray;
use Mifrial\Core\Kernel\Value\Optional\OptionalBool;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;
use Mifrial\Core\Kernel\Value\Optional\OptionalString;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Dto\Action\MigrateCharacterInput;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterProblem;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Exception\CharacterConflictException;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSessionParticipants;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\CharacterMigration;
use Mifrial\Roleplay\Character\Service\Save\CharacterSaveAssembly;
use Mifrial\Roleplay\Character\Service\Save\CharacterShopBalance;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterSpecReader;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CharacterMigrationTest extends TestCase
{
    /**
     * Problems валидатора не пишут строку. Способность с живым кодом остаётся.
     *
     * @return void
     */
    public function testConflictsDoNotWrite(): void
    {
        $characters = $this->charactersNeverWrites();
        $seen = null;
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->method('validate')->willReturnCallback(
            function (CharacterRuleSlice $slice, CharacterChoices $choices) use (&$seen): CharacterValidation {
                $seen = $choices;

                return new CharacterValidation([
                    new CharacterProblem('CHARACTER_PURCHASE', 'Characteristic purchase is not on the race ladder', 'requirement', 'characteristicPurchases.0'),
                ], [], [], 0, [], [], [], true);
            },
        );
        $migration = $this->migration($characters, $sheets, $this->slice(1, ['human', 'bolt']), $this->slice(2, ['human', 'bolt']));
        $result = $migration->migrate($this->input(2));

        self::assertSame('conflicts', $result['kind']);
        self::assertSame('requirement', $result['problems'][0]['stage']);
        self::assertInstanceOf(CharacterChoices::class, $seen);
        self::assertSame('bolt', $seen->getAbilities()[0]->getRuleCode());
        self::assertSame(1, $characters->get(4)->getRulesRevision());
        self::assertSame(1, $characters->get(4)->getActualVersion());
    }

    /**
     * Нет живого кода способности — строки нет в предложении. Problems оставляют actual.
     *
     * @return void
     */
    public function testMissingAbilityStaysUnwrittenWhenInvalid(): void
    {
        $characters = $this->charactersNeverWrites();
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->method('validate')->willReturn(new CharacterValidation([
            new CharacterProblem('CHARACTER_RACE', 'Race code is required', 'reference', 'raceCode'),
        ], [], [], 0, [], [], [], true));
        $migration = $this->migration($characters, $sheets, $this->slice(1, ['human', 'bolt']), $this->slice(2, []));
        $result = $migration->migrate($this->input(2));

        self::assertSame('conflicts', $result['kind']);
        self::assertSame([], $result['choices']['abilities']);
        self::assertSame('', $result['choices']['raceCode']);
    }

    /**
     * Tombstone предмета становится custom. Наличные не пересчитываются.
     *
     * @return void
     */
    public function testTombstoneItemBecomesCustomWithoutShop(): void
    {
        $written = null;
        $characters = $this->charactersWriting($written);
        $migration = $this->migration(
            $characters,
            $this->cleanSheets(),
            $this->slice(1, ['human', 'sword'], ['sword' => ['Sword', 'Old blade']]),
            $this->slice(2, ['human'], [], ['sword' => true]),
        );
        $result = $migration->migrate($this->input(2));

        self::assertSame('resolved', $result['kind']);
        self::assertSame(2, $result['revision']);
        self::assertIsArray($written);
        self::assertSame(7, $written['choices']['money']);
        self::assertSame('Sword', $written['choices']['inventory'][0]['custom']['name']);
        self::assertSame('Old blade', $written['choices']['inventory'][0]['custom']['description']);
        self::assertArrayNotHasKey('ruleCode', $written['choices']['inventory'][0]);
        self::assertSame(['gem'], $written['choices']['inventory'][0]['modifiers']);
    }

    /**
     * Чистый ремап пишет целевую ревизию и увеличивает version.
     *
     * @return void
     */
    public function testCleanRemapIsOk(): void
    {
        $written = null;
        $characters = $this->charactersWriting($written);
        $migration = $this->migration(
            $characters,
            $this->cleanSheets(),
            $this->slice(1, ['human', 'bolt', 'sword']),
            $this->slice(2, ['human', 'bolt', 'sword']),
        );
        $result = $migration->migrate($this->input(2));

        self::assertSame('ok', $result['kind']);
        self::assertSame(2, $result['actualVersion']);
        self::assertSame(2, $written['rulesRevision']);
    }

    /**
     * Снятая способность при пустом валидаторе — resolved и запись.
     *
     * @return void
     */
    public function testDroppedAbilityWritesResolved(): void
    {
        $written = null;
        $characters = $this->charactersWriting($written);
        $migration = $this->migration(
            $characters,
            $this->cleanSheets(),
            $this->slice(1, ['human', 'bolt']),
            $this->slice(2, ['human']),
        );
        $result = $migration->migrate($this->input(2));

        self::assertSame('resolved', $result['kind']);
        self::assertSame([], $written['choices']['abilities']);
    }

    /**
     * Устаревший expectedVersion не становится успешной записью.
     *
     * @return void
     */
    public function testStaleVersionDoesNotWrite(): void
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->method('get')->willReturn($this->record());
        $characters->method('replaceMigrated')->willThrowException(new CharacterConflictException(2));
        $migration = $this->migration($characters, $this->cleanSheets(), $this->slice(1, ['human']), $this->slice(2, ['human']));

        $this->expectException(CharacterConflictException::class);
        $migration->migrate($this->input(2));
    }

    /**
     * Та же ревизия отклоняется до среза.
     *
     * @return void
     */
    public function testSameRevisionIsInvalid(): void
    {
        $characters = $this->charactersNeverWrites();
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->expects(self::never())->method('get');
        $migration = new CharacterMigration(
            $this->access(),
            $characters,
            $slices,
            new CharacterSaveAssembly($slices, $this->cleanSheets(), new CharacterShopBalance(new CharacterSpecReader(), new CharacterDonorGrants())),
            $this->idleSessions(),
        );

        $this->expectException(CharacterInvalidException::class);
        $migration->migrate($this->input(1));
    }

    /**
     * Неполное тело продолжения не ремапит и не пишет.
     *
     * @return void
     */
    public function testIncompleteResumeIsInvalid(): void
    {
        $characters = $this->charactersNeverWrites();
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->expects(self::never())->method('get');
        $migration = new CharacterMigration(
            $this->access(),
            $characters,
            $slices,
            new CharacterSaveAssembly($slices, $this->cleanSheets(), new CharacterShopBalance(new CharacterSpecReader(), new CharacterDonorGrants())),
            $this->idleSessions(),
        );

        $this->expectException(CharacterInvalidException::class);
        $migration->migrate($this->input(2, ['name' => 'Hero']));
    }

    /**
     * Продолжение пишет тело как есть и не возвращает снятый код из actual.
     *
     * @return void
     */
    public function testResumeDoesNotRemapStoredChoices(): void
    {
        $written = null;
        $characters = $this->charactersWriting($written);
        $migration = $this->migration(
            $characters,
            $this->cleanSheets(),
            $this->slice(1, ['human', 'dropme']),
            $this->slice(2, ['human', 'keep']),
        );
        $result = $migration->migrate($this->input(2, [
            'name' => 'Hero',
            'limits' => ['os' => null, 'or' => null],
            'raceCode' => 'human',
            'abilities' => [['ruleCode' => 'keep', 'level' => 1]],
            'inventory' => [],
            'characteristicPurchases' => [],
            'customRules' => [],
        ]));

        self::assertSame('resolved', $result['kind']);
        self::assertSame('keep', $written['choices']['abilities'][0]['ruleCode']);
        self::assertCount(1, $written['choices']['abilities']);
    }

    /**
     * Валидатор без problems.
     *
     * @return ICharacterSheets Порт.
     */
    private function cleanSheets(): ICharacterSheets
    {
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->method('validate')->willReturn(new CharacterValidation([], [], [], 0, [], [], [], true));

        return $sheets;
    }

    /**
     * Сценарий с двумя срезами.
     *
     * @param ICharacters $characters Строки.
     * @param ICharacterSheets $sheets Валидатор.
     * @param CharacterRuleSlice $source Текущая ревизия.
     * @param CharacterRuleSlice $target Цель.
     *
     * @return CharacterMigration Сценарий.
     */
    private function migration(
        ICharacters $characters,
        ICharacterSheets $sheets,
        CharacterRuleSlice $source,
        CharacterRuleSlice $target,
    ): CharacterMigration {
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturnCallback(
            function (int $spaceId, int $revision) use ($source, $target): CharacterRuleSlice {
                self::assertSame(1, $spaceId);

                return $revision === $target->getRevision() ? $target : $source;
            },
        );

        return new CharacterMigration(
            $this->access(),
            $characters,
            $slices,
            new CharacterSaveAssembly($slices, $sheets, new CharacterShopBalance(new CharacterSpecReader(), new CharacterDonorGrants())),
            $this->idleSessions(),
        );
    }

    /**
     * Сессии нет.
     *
     * @return ICharacterSessionParticipants Порт.
     */
    private function idleSessions(): ICharacterSessionParticipants
    {
        $participants = $this->createMock(ICharacterSessionParticipants::class);
        $participants->method('isActiveSessionParticipant')->willReturn(false);

        return $participants;
    }

    /**
     * get без записи.
     *
     * @return ICharacters&MockObject Фасад.
     */
    private function charactersNeverWrites(): ICharacters
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->method('get')->willReturn($this->record());
        $characters->expects(self::never())->method('replaceMigrated');

        return $characters;
    }

    /**
     * Запись запоминает аргументы и возвращает строку цели.
     *
     * @param array<string, mixed>|null $written Аргументы.
     *
     * @return ICharacters&MockObject Фасад.
     */
    private function charactersWriting(mixed &$written): ICharacters
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->method('get')->willReturn($this->record());
        $characters->method('replaceMigrated')->willReturnCallback(
            function (
                int $id,
                string $name,
                bool $active,
                array $choices,
                array $sheet,
                int $rulesRevision,
                int $expectedVersion,
            ) use (&$written): CharacterRecord {
                $written = [
                    'choices' => $choices,
                    'sheet' => $sheet,
                    'rulesRevision' => $rulesRevision,
                    'expectedVersion' => $expectedVersion,
                ];

                return $this->record(2, 2);
            },
        );

        return $characters;
    }

    /**
     * Актор-владелец.
     *
     * @return IUserAccess Guard.
     */
    private function access(): IUserAccess
    {
        $access = $this->createMock(IUserAccess::class);
        $access->method('requireActor')->willReturn(new RequestActor(3, [], false));

        return $access;
    }

    /**
     * Вход без тела или с полями продолжения.
     *
     * @param int $revision Цель.
     * @param array<string, mixed> $present Ключи тела.
     *
     * @return MigrateCharacterInput JSON.
     */
    private function input(int $revision, array $present = []): MigrateCharacterInput
    {
        return new MigrateCharacterInput(
            4,
            $revision,
            1,
            $this->optionalString($present, 'name'),
            $this->optionalArray($present, 'limits'),
            $this->optionalString($present, 'raceCode'),
            OptionalInt::absent(),
            OptionalString::absent(),
            OptionalString::absent(),
            OptionalInt::absent(),
            $this->optionalArray($present, 'characteristicPurchases'),
            $this->optionalArray($present, 'abilities'),
            $this->optionalArray($present, 'inventory'),
            $this->optionalArray($present, 'customRules'),
            OptionalBool::absent(),
            OptionalArray::absent(),
        );
    }

    /**
     * Строка или absent.
     *
     * @param array<string, mixed> $present Ключи.
     * @param string $key Имя.
     *
     * @return OptionalString Поле.
     */
    private function optionalString(array $present, string $key): OptionalString
    {
        if (!array_key_exists($key, $present)) {
            return OptionalString::absent();
        }

        return OptionalString::present(is_string($present[$key]) ? $present[$key] : null);
    }

    /**
     * Массив или absent.
     *
     * @param array<string, mixed> $present Ключи.
     * @param string $key Имя.
     *
     * @return OptionalArray Поле.
     */
    private function optionalArray(array $present, string $key): OptionalArray
    {
        if (!array_key_exists($key, $present) || !is_array($present[$key])) {
            return OptionalArray::absent();
        }

        return OptionalArray::present($present[$key]);
    }

    /**
     * Срез ревизии.
     *
     * @param int $revision Номер.
     * @param array<int, string> $codes Живые коды.
     * @param array<string, array{0: string, 1: string}> $named Имя и описание.
     * @param array<string, true> $tombstones Снятые code.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(int $revision, array $codes, array $named = [], array $tombstones = []): CharacterRuleSlice
    {
        $live = [];
        foreach ($codes as $code) {
            $name = $named[$code][0] ?? $code;
            $description = $named[$code][1] ?? '';
            $type = $code === 'human' ? 'race' : ($code === 'sword' ? 'item' : 'ability');
            $live[] = new CharacterResolvedRule(
                new RuleVersionRecord(10, 1, $code, true, $type, $name, $description, [], [], [], 'needs_work', '', DateTime::now()),
                [],
            );
        }

        return new CharacterRuleSlice(1, $revision, 'world', $live, $tombstones);
    }

    /**
     * Actual с предметом, способностью и наличными.
     *
     * @param int $rulesRevision Ревизия.
     * @param int $actualVersion Версия.
     *
     * @return CharacterRecord Строка.
     */
    private function record(int $rulesRevision = 1, int $actualVersion = 1): CharacterRecord
    {
        $moment = DateTime::now();

        return CharacterRecord::fromNormalized([
            'id' => 4,
            'owner_id' => 3,
            'space_id' => 1,
            'rules_revision' => $rulesRevision,
            'name' => 'Hero',
            'active' => true,
            'actual_version' => $actualVersion,
            'choices' => [
                'name' => 'Hero',
                'raceCode' => 'human',
                'limits' => ['os' => null, 'or' => null, 'money' => 10],
                'abilities' => [['ruleCode' => 'bolt', 'level' => 1]],
                'inventory' => [[
                    'ruleCode' => 'sword',
                    'quantity' => 1,
                    'equipped' => false,
                    'modifiers' => ['gem'],
                ]],
                'characteristicPurchases' => [['characteristicCode' => 'str', 'cost' => 1]],
                'customRules' => [],
                'active' => true,
            ],
            'sheet' => ['money' => 7],
            'visibility_fields' => [],
            'is_public' => false,
            'owner_notes' => '',
            'created_at' => $moment,
            'updated_at' => $moment,
        ]);
    }
}
