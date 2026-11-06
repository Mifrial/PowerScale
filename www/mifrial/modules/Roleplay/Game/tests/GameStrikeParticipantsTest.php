<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterRecord;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterFormulaContexts;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterRuleSlices;
use Mifrial\Roleplay\Character\Interface\Service\ICharacters;
use Mifrial\Roleplay\Character\Interface\Service\ICharacterSheets;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameStrikeParticipants;
use Mifrial\Roleplay\Game\Service\GameStrikeSlices;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Участники удара: снимок листа, документы и контекст формулы.
 */
final class GameStrikeParticipantsTest extends TestCase
{
    /**
     * Снимок персонажа считается один раз и отдаёт живые документы.
     *
     * @return void
     */
    public function testCharacterDocumentsUseSnapshotAndCacheSheet(): void
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->method('get')->with(4)->willReturn($this->record());
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->expects(self::once())->method('compute')->willReturn(['computed' => true]);
        $contexts = $this->createMock(ICharacterFormulaContexts::class);
        $contexts->method('build')->willReturn(new FormulaContext());
        $participants = $this->participants($characters, $sheets, $contexts, $this->slice());

        $first = $participants->attackerDocuments('character', 4, null, null);
        $second = $participants->attackerDocuments('character', 4, null, null);

        self::assertSame(['computed' => true], $first['sheet']);
        self::assertSame(['raceCode' => 'human'], $first['choices']);
        self::assertSame('character:4:3', $first['key']);
        self::assertSame($first, $second);
    }

    /**
     * NPC приходит снаружи, а ключ появляется только при известной версии.
     *
     * @return void
     */
    public function testNpcDocumentsUsePassedSheetAndVersion(): void
    {
        $participants = $this->participants(
            $this->createMock(ICharacters::class),
            $this->createMock(ICharacterSheets::class),
            $this->createMock(ICharacterFormulaContexts::class),
            $this->slice(),
        );

        $documents = $participants->attackerDocuments('npc', 9, ['hp' => 1], ['raceCode' => 'orc'], 5);
        self::assertSame(['hp' => 1], $documents['sheet']);
        self::assertSame(['raceCode' => 'orc'], $documents['choices']);
        self::assertSame('npc:9:5', $documents['key']);

        $withoutVersion = $participants->attackerDocuments('npc', 9, ['hp' => 1], []);
        self::assertNull($withoutVersion['key']);
    }

    /**
     * NPC без снимка листа не становится атакующим.
     *
     * @return void
     */
    public function testNpcAttackerRequiresSheet(): void
    {
        $participants = $this->participants(
            $this->createMock(ICharacters::class),
            $this->createMock(ICharacterSheets::class),
            $this->createMock(ICharacterFormulaContexts::class),
            $this->slice(),
        );

        $this->expectException(GameInvalidException::class);
        $participants->attackerDocuments('npc', 9, null, []);
    }

    /**
     * Цель-NPC без sheet или choices отклоняется.
     *
     * @return void
     */
    public function testNpcDefenderRequiresSheet(): void
    {
        $participants = $this->participants(
            $this->createMock(ICharacters::class),
            $this->createMock(ICharacterSheets::class),
            $this->createMock(ICharacterFormulaContexts::class),
            $this->slice(),
        );

        $this->expectException(GameInvalidException::class);
        $participants->defenderDocuments('npc', 9, null, []);
    }

    /**
     * Цель-NPC без choices отклоняется.
     *
     * @return void
     */
    public function testNpcDefenderRequiresChoices(): void
    {
        $participants = $this->participants(
            $this->createMock(ICharacters::class),
            $this->createMock(ICharacterSheets::class),
            $this->createMock(ICharacterFormulaContexts::class),
            $this->slice(),
        );

        $this->expectException(GameInvalidException::class);
        $participants->defenderDocuments('npc', 9, ['hp' => 1], null);
    }

    /**
     * Цель-персонаж читается по id и получает ключ версии листа.
     *
     * @return void
     */
    public function testCharacterDefenderDocumentsReadRecord(): void
    {
        $characters = $this->createMock(ICharacters::class);
        $characters->method('get')->with(4)->willReturn($this->record());
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->method('compute')->willReturn(['computed' => true]);
        $contexts = $this->createMock(ICharacterFormulaContexts::class);
        $contexts->method('build')->willReturn(new FormulaContext());
        $participants = $this->participants(
            $characters,
            $sheets,
            $contexts,
            $this->slice(),
        );

        $documents = $participants->defenderDocuments('character', 4, null, null);

        self::assertSame(['computed' => true], $documents['sheet']);
        self::assertSame(['raceCode' => 'human'], $documents['choices']);
        self::assertSame('character:4:3', $documents['key']);
    }

    /**
     * Контекст формулы кэшируется по ключу участника.
     *
     * @return void
     */
    public function testFormulaContextCachesByParticipantKey(): void
    {
        $context = new FormulaContext(['strength' => new DimensionalNumber(3, 0)]);
        $sheets = $this->createMock(ICharacterSheets::class);
        $sheets->expects(self::once())->method('compute')->willReturn(['computed' => true]);
        $contexts = $this->createMock(ICharacterFormulaContexts::class);
        $contexts->expects(self::once())->method('build')->willReturn($context);
        $participants = $this->participants(
            $this->createMock(ICharacters::class),
            $sheets,
            $contexts,
            $this->slice(),
        );

        $first = $participants->formulaContext($this->slice(), ['raw' => true], ['raceCode' => 'human'], 'npc:9:5');
        $second = $participants->formulaContext($this->slice(), ['raw' => true], ['raceCode' => 'human'], 'npc:9:5');

        self::assertSame($context, $first);
        self::assertSame($context, $second);
    }

    /**
     * Характеристика листа читается из построенного контекста.
     *
     * @return void
     */
    public function testCharacteristicOfReadsFormulaContext(): void
    {
        $contexts = $this->createMock(ICharacterFormulaContexts::class);
        $contexts->method('build')->willReturn(new FormulaContext(['strength' => new DimensionalNumber(3, 2)]));
        $participants = $this->participants(
            $this->createMock(ICharacters::class),
            $this->createMock(ICharacterSheets::class),
            $contexts,
            $this->slice(),
        );

        $value = $participants->characteristicOf(['raw' => true], 'strength');

        self::assertSame(3, $value->getBase());
        self::assertSame(2, $value->getSize());
    }

    /**
     * Отсутствующая характеристика отклоняется.
     *
     * @return void
     */
    public function testCharacteristicOfRejectsMissingCode(): void
    {
        $contexts = $this->createMock(ICharacterFormulaContexts::class);
        $contexts->method('build')->willReturn(new FormulaContext());
        $participants = $this->participants(
            $this->createMock(ICharacters::class),
            $this->createMock(ICharacterSheets::class),
            $contexts,
            $this->slice(),
        );

        $this->expectException(GameInvalidException::class);
        $participants->characteristicOf(['raw' => true], 'strength');
    }

    /**
     * Стойкость берётся из единственной характеристики с damage_endurance.
     *
     * @return void
     */
    public function testEnduranceOfUsesSingleDamageEnduranceCharacteristic(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->characteristic('stamina', true),
            $this->characteristic('grit', false),
        ], []);
        $contexts = $this->createMock(ICharacterFormulaContexts::class);
        $contexts->method('build')->willReturn(new FormulaContext(['stamina' => new DimensionalNumber(5, 0)]));
        $participants = $this->participants(
            $this->createMock(ICharacters::class),
            $this->createMock(ICharacterSheets::class),
            $contexts,
            $slice,
        );

        $value = $participants->enduranceOf(1, 1, 'npc', 9, ['hp' => 1], []);

        self::assertSame(5, $value->getBase());
    }

    /**
     * Две характеристики стойкости неоднозначны.
     *
     * @return void
     */
    public function testEnduranceOfRejectsAmbiguousCharacteristic(): void
    {
        $slice = new CharacterRuleSlice(1, 1, 'world', [
            $this->characteristic('stamina', true),
            $this->characteristic('grit', true),
        ], []);
        $contexts = $this->createMock(ICharacterFormulaContexts::class);
        $contexts->method('build')->willReturn(new FormulaContext());
        $participants = $this->participants(
            $this->createMock(ICharacters::class),
            $this->createMock(ICharacterSheets::class),
            $contexts,
            $slice,
        );

        $this->expectException(GameInvalidException::class);
        $participants->enduranceOf(1, 1, 'npc', 9, ['hp' => 1], []);
    }

    /**
     * Ключ участника — вид, id и версия через двоеточие.
     *
     * @return void
     */
    public function testMakeParticipantKeyFormat(): void
    {
        $participants = $this->participants(
            $this->createMock(ICharacters::class),
            $this->createMock(ICharacterSheets::class),
            $this->createMock(ICharacterFormulaContexts::class),
            $this->slice(),
        );

        self::assertSame('character:4:3', $participants->makeParticipantKey('character', 4, 3));
    }

    /**
     * Собирает участников поверх моков.
     *
     * @param ICharacters $characters Листы персонажей.
     * @param ICharacterSheets $sheets Пересчёт листа.
     * @param ICharacterFormulaContexts $contexts Контекст формулы.
     * @param CharacterRuleSlice $slice Срез ревизии.
     *
     * @return GameStrikeParticipants Участники.
     */
    private function participants(
        ICharacters $characters,
        ICharacterSheets $sheets,
        ICharacterFormulaContexts $contexts,
        CharacterRuleSlice $slice,
    ): GameStrikeParticipants {
        $source = $this->createMock(ICharacterRuleSlices::class);
        $source->method('get')->willReturn($slice);

        return new GameStrikeParticipants($characters, $contexts, $sheets, new GameStrikeSlices($source));
    }

    /**
     * Строка персонажа.
     *
     * @return CharacterRecord Запись.
     */
    private function record(): CharacterRecord
    {
        return CharacterRecord::fromNormalized([
            'id' => 4,
            'owner_id' => 1,
            'space_id' => 1,
            'rules_revision' => 1,
            'name' => 'Hero',
            'active' => true,
            'actual_version' => 3,
            'choices' => ['raceCode' => 'human'],
            'sheet' => ['raw' => true],
            'visibility_fields' => [],
            'is_public' => false,
            'owner_notes' => '',
            'created_at' => DateTime::now(),
            'updated_at' => DateTime::now(),
        ]);
    }

    /**
     * Пустой срез ревизии.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [], []);
    }

    /**
     * Карточка характеристики.
     *
     * @param string $code Код.
     * @param bool $endurance Стойкость к урону.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function characteristic(string $code, bool $endurance): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                'characteristic',
                $code,
                '',
                ['damage_endurance' => $endurance],
                [],
                [],
                'needs_work',
                '',
                DateTime::now(),
            ),
            [],
        );
    }
}
