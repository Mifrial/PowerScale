<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Character\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Core\Kernel\Value\Optional\OptionalInt;
use Mifrial\Core\User\Interface\Service\IUserAccess;
use Mifrial\Roleplay\Character\Dto\Action\CreateCharacterInput;
use Mifrial\Roleplay\Character\Dto\Action\UpdateCharacterInput;
use Mifrial\Roleplay\Character\Dto\Action\ValidateCharacterInput;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\CharacterValidation;
use Mifrial\Roleplay\Character\Exception\CharacterInvalidException;
use Mifrial\Roleplay\Character\Exception\CharacterNotFoundException;
use Mifrial\Roleplay\Character\Exception\CharacterSaveRejectedException;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Character\Service\CharacterOsSteps;
use Mifrial\Roleplay\Character\Service\CharacterSave;
use Mifrial\Roleplay\Character\Service\CharacterSheets;
use Mifrial\Roleplay\Character\Service\Save\CharacterShopBalance;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterDonorGrants;
use Mifrial\Roleplay\Character\Service\Sheet\Spec\CharacterSpecReader;
use Mifrial\Roleplay\Mechanic\Dto\MechanicRecord;
use Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine;
use Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CharacterSaveTest extends TestCase
{
    /**
     * Битая ссылка, requirement, handler и budget не вызывают add.
     *
     * @return void
     */
    public function testProblemsDoNotAdd(): void
    {
        $this->assertRejectedStage($this->brokenRaceSlice(), 'reference', []);
        $this->assertRejectedStage($this->ambiguousGrantSlice(), 'requirement', [
            ['ruleCode' => 'lore', 'level' => 1],
            ['ruleCode' => 'lore-2', 'level' => 1],
        ]);
        $this->assertRejectedStage($this->badHandlerSlice(), 'mechanic', []);
        $characters = $this->charactersNeverWrites();
        $save = $this->save($characters, $this->sheetsWith($this->cleanValidation()), $this->itemSlice());
        try {
            $save->create($this->createInput(['limits' => ['money' => 1], 'inventory' => [[
                'ruleCode' => 'sword',
                'quantity' => 1,
                'equipped' => false,
            ]]]));
            self::fail('Overspend must reject');
        } catch (CharacterSaveRejectedException $exception) {
            self::assertSame('budget', $exception->getErrorDetails()['problems'][0]['stage']);
        }
    }

    /**
     * Упавший валидатор не пишет строку.
     *
     * @return void
     */
    public function testThrownSheetDoesNotAdd(): void
    {
        $characters = $this->charactersNeverWrites();
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->method('validate')->willThrowException(new RuntimeException('handler failed'));
        $save = $this->save($characters, $sheets, new CharacterRuleSlice(1, 1, 'world', [], []));
        $this->expectException(RuntimeException::class);
        $save->create($this->createInput());
    }

    /**
     * Расхождение expectedSheet: validate 200, create без записи.
     *
     * @return void
     */
    public function testExpectedSheetMismatch(): void
    {
        $characters = $this->charactersNeverWrites();
        $save = $this->save($characters, $this->sheetsWith($this->cleanValidation()), new CharacterRuleSlice(1, 1, 'world', [], []));
        $input = $this->validateInput(['expectedSheet' => ['money' => 4]]);
        $report = $save->validate($input);
        self::assertFalse($report['valid']);
        self::assertSame('derived', $report['problems'][0]['stage']);
        $this->expectException(CharacterSaveRejectedException::class);
        $save->create($this->createInput(['expectedSheet' => ['money' => 4]]));
    }

    /**
     * Успешный create отвечает версией 1 и листом сервера.
     *
     * @return void
     */
    public function testCreateReturnsServerSheet(): void
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->expects(self::once())->method('add')->willReturn(9);
        $characters->method('get')->willReturn($this->record(9, 1, ['money' => 0]));
        $save = $this->save($characters, $this->sheetsWith($this->cleanValidation()), new CharacterRuleSlice(1, 1, 'world', [], []));
        $view = $save->create($this->createInput());
        self::assertSame(1, $view['actualVersion']);
        self::assertTrue($view['validation']['valid']);
        self::assertSame(0, $view['sheet']['money']);
    }

    /**
     * Update отвечает версией строки после записи.
     *
     * @return void
     */
    public function testUpdateReturnsBumpedVersion(): void
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->method('get')->willReturn($this->record(4, 1, ['money' => 2]));
        $characters->expects(self::once())->method('replaceSaved')->willReturn($this->record(4, 1, ['money' => 2], 2));
        $save = $this->save($characters, $this->sheetsWith($this->cleanValidation()), new CharacterRuleSlice(1, 1, 'world', [], []));
        $view = $save->update($this->updateInput());
        self::assertSame(2, $view['actualVersion']);
        self::assertSame(2, $view['sheet']['money']);
    }

    /**
     * Validate с id не считает лист по чужой ревизии.
     *
     * @return void
     */
    public function testValidateRejectsRevisionChange(): void
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->method('get')->willReturn($this->record(4, 1, ['money' => 2]));
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->expects(self::never())->method('validate');
        $save = $this->save($characters, $sheets, new CharacterRuleSlice(1, 1, 'world', [], []));
        $this->expectException(CharacterInvalidException::class);
        $save->validate($this->validateInput(['id' => 4, 'revision' => 2]));
    }

    /**
     * Денежный грант уровня 2 не входит в остаток на уровне 1.
     *
     * @return void
     */
    public function testShopMoneyUsesPurchasedLevel(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('purse', 'ability', ['grants' => [[
                'level' => 2,
                'grants' => [['type' => 'money', 'fixed' => 40, 'percent' => 0, 'apply' => 'shop']],
            ]]]),
        ], []);
        $balance = (new CharacterShopBalance(new CharacterSpecReader(), new CharacterDonorGrants()))->getBalance(
            $slice,
            [new CharacterAbilityChoice('purse', 1, '', '', null, null, null)],
            [],
            0,
        );
        self::assertSame(0, $balance['money']);
        self::assertSame([], $balance['problems']);
    }

    /**
     * Update без ключа money сохраняет отрицательный остаток строки.
     *
     * @return void
     */
    public function testUpdateKeepsStoredNegativeMoney(): void
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->method('get')->willReturn($this->record(4, 1, ['money' => -3]));
        $characters->expects(self::once())->method('replaceSaved')->willReturnCallback(
            function (int $id, string $name, bool $active, array $choices, array $sheet, int $expectedVersion): CharacterRecord {
                self::assertSame(-3, $sheet['money']);
                self::assertSame(-3, $choices['money']);

                return $this->record($id, 1, $sheet, $expectedVersion + 1);
            },
        );
        $save = $this->save($characters, $this->sheetsWith($this->cleanValidation()), new CharacterRuleSlice(1, 1, 'world', [], []));
        $view = $save->update($this->updateInput());
        self::assertSame(-3, $view['sheet']['money']);
    }

    /**
     * Чужой id не получает problems.
     *
     * @return void
     */
    public function testForeignOwnerIsNotFound(): void
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->method('get')->willReturn($this->record(3, 1, []));
        $characters->expects(self::never())->method('replaceSaved');
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->expects(self::never())->method('validate');
        $save = $this->save($characters, $sheets, new CharacterRuleSlice(1, 1, 'world', [], []), 8);
        $this->expectException(CharacterNotFoundException::class);
        $save->validate($this->validateInput(['id' => 3]));
    }

    /**
     * Отказ настоящего валидатора и без add.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $stage Стадия.
     * @param array<int, array<string, mixed>> $abilities Способности.
     *
     * @return void
     */
    private function assertRejectedStage(CharacterRuleSlice $slice, string $stage, array $abilities): void
    {
        $characters = $this->charactersNeverWrites();
        $save = $this->save($characters, $this->realSheets(), $slice);
        try {
            $save->create($this->createInput(['abilities' => $abilities]));
            self::fail('Problems must reject');
        } catch (CharacterSaveRejectedException $exception) {
            self::assertSame($stage, $exception->getErrorDetails()['problems'][0]['stage']);
        }
    }

    /**
     * Фасад, который не пишет.
     *
     * @return ICharacters&MockObject Фасад.
     */
    private function charactersNeverWrites(): ICharacters
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->expects(self::never())->method('add');
        $characters->expects(self::never())->method('replaceSaved');

        return $characters;
    }

    /**
     * Сценарий с актором.
     *
     * @param ICharacters $characters Фасад.
     * @param ICharacterSheets $sheets Валидатор.
     * @param CharacterRuleSlice $slice Срез.
     * @param int $actorUserId Актор.
     *
     * @return CharacterSave Сценарий.
     */
    private function save(ICharacters $characters, ICharacterSheets $sheets, CharacterRuleSlice $slice, int $actorUserId = 1): CharacterSave
    {
        $access = $this->createMock(IUserAccess::class);
        $actor = new RequestActor($actorUserId, ['character.create'], false);
        $access->method('requireKey')->willReturn($actor);
        $access->method('requireActor')->willReturn($actor);
        $slices = $this->createMock(ICharacterRuleSlices::class);
        $slices->method('get')->willReturn($slice);

        return new CharacterSave($access, $characters, $slices, $sheets, new CharacterShopBalance(new CharacterSpecReader(), new CharacterDonorGrants()));
    }

    /**
     * Валидатор с фиксированным результатом.
     *
     * @param CharacterValidation $validation Результат.
     *
     * @return ICharacterSheets Валидатор.
     */
    private function sheetsWith(CharacterValidation $validation): ICharacterSheets
    {
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->method('validate')->willReturn($validation);

        return $sheets;
    }

    /**
     * Пустой успешный снимок.
     *
     * @return CharacterValidation Результат.
     */
    private function cleanValidation(): CharacterValidation
    {
        return new CharacterValidation([], [], [], 0, [], [], [], true);
    }

    /**
     * Предмет дороже бюджета.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function itemSlice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [
            new CharacterResolvedRule(
                new RuleVersionRecord(10, 1, 'sword', true, 'item', 'sword', '', ['cost_gm' => 10], [], [], 'needs_work', '', DateTime::now()),
                [],
            ),
        ], []);
    }

    /**
     * Валидатор C4 с движком из контейнера.
     *
     * @return ICharacterSheets Валидатор.
     */
    private function realSheets(): ICharacterSheets
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $engine = $application->getLocator()->get(IMechanicContainer::class)->get(IMechanicEngine::class);
        self::assertInstanceOf(IMechanicEngine::class, $engine);
        $mechanics = $this->createMock(IMechanics::class);
        $mechanics->method('get')->willReturn(MechanicRecord::fromNormalized([
            'id' => 4,
            'code' => 'other',
            'name' => 'Чужое',
            'description' => '',
            'handler_version' => '9.0.0',
        ]));

        return new CharacterSheets(new CharacterOsSteps($engine, $mechanics), $mechanics);
    }

    /**
     * Расы в срезе нет.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function brokenRaceSlice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [], []);
    }

    /**
     * Два денежных гранта без указателя.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function ambiguousGrantSlice(): CharacterRuleSlice
    {
        $grant = [
            'level' => 1,
            'grants' => [['type' => 'money', 'fixed' => 1, 'percent' => 0, 'apply' => 'shop']],
        ];

        return new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('human', 'race', ['abilities' => []]),
            $this->rule('lore', 'ability', ['grants' => [$grant]]),
            $this->rule('lore-2', 'ability', ['grants' => [$grant]]),
        ], []);
    }

    /**
     * Поставка не purchase_surcharge 1.0.0.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function badHandlerSlice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('human', 'race', ['abilities' => []]),
            $this->rule('priced', 'ability', [], [[
                'mechanic_id' => 4,
                'mechanic_payload' => [
                    'type' => 'purchase_surcharge',
                    'filter' => [],
                    'free_count' => 1,
                    'surcharge' => 1,
                ],
            ]]),
        ], []);
    }

    /**
     * Правило среза.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Spec.
     * @param array<int, array<string, mixed>> $mechanics Механики.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $spec, array $mechanics = []): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(10, 1, $code, true, $type, $code, '', $spec, [], $mechanics, 'needs_work', '', DateTime::now()),
            [],
        );
    }

    /**
     * Минимальный create.
     *
     * @param array<string, mixed> $overrides Поля.
     *
     * @return CreateCharacterInput Вход.
     */
    private function createInput(array $overrides = []): CreateCharacterInput
    {
        return new CreateCharacterInput(
            1,
            1,
            'Hero',
            $overrides['limits'] ?? ['os' => null, 'or' => null],
            'human',
            '',
            '',
            null,
            [],
            $overrides['abilities'] ?? [],
            $overrides['inventory'] ?? [],
            [],
            true,
            $overrides['expectedSheet'] ?? null,
        );
    }

    /**
     * Update той же ревизии.
     *
     * @return UpdateCharacterInput Вход.
     */
    private function updateInput(): UpdateCharacterInput
    {
        return new UpdateCharacterInput(4, 1, 1, 'Hero', ['os' => null, 'or' => null], 'human', OptionalInt::absent(), 1);
    }

    /**
     * Validate без записи.
     *
     * @param array<string, mixed> $overrides Поля.
     *
     * @return ValidateCharacterInput Вход.
     */
    private function validateInput(array $overrides = []): ValidateCharacterInput
    {
        return new ValidateCharacterInput(
            1,
            $overrides['revision'] ?? 1,
            'Hero',
            ['os' => null, 'or' => null],
            'human',
            OptionalInt::absent(),
            $overrides['id'] ?? null,
            '',
            '',
            null,
            [],
            [],
            [],
            [],
            true,
            $overrides['expectedSheet'] ?? null,
        );
    }

    /**
     * Строка персонажа.
     *
     * @param int $id Идентификатор.
     * @param int $ownerId Владелец.
     * @param array<string, mixed> $sheet Снимок.
     * @param int $actualVersion Версия строки.
     *
     * @return CharacterRecord Запись.
     */
    private function record(int $id, int $ownerId, array $sheet, int $actualVersion = 1): CharacterRecord
    {
        $moment = DateTime::now();

        return CharacterRecord::fromNormalized([
            'id' => $id,
            'owner_id' => $ownerId,
            'space_id' => 1,
            'rules_revision' => 1,
            'name' => 'Hero',
            'active' => true,
            'actual_version' => $actualVersion,
            'choices' => [],
            'sheet' => $sheet,
            'visibility_fields' => [],
            'is_public' => false,
            'owner_notes' => '',
            'created_at' => $moment,
            'updated_at' => $moment,
        ]);
    }
}
