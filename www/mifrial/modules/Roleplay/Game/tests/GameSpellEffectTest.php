<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameActiveSpellRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameSpellEffect;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilitySpellSpec;
use PHPUnit\Framework\TestCase;

/**
 * Временные чувства из поддерживаемого заклинания.
 */
final class GameSpellEffectTest extends TestCase
{
    /**
     * Радиус — мощь поддержания, значение — разница Контроля и 4↑.
     *
     * @return void
     */
    public function testSenseEffectUsesSustainPowerAndControl(): void
    {
        $rows = (new GameSpellEffect())->runtimeSenses(
            $this->slice(),
            [$this->spell(['base' => 4, 'size' => -1], ['base' => 5, 'size' => 0])],
            $this->sheet(['base' => 4, 'size' => 0]),
        );

        self::assertCount(1, $rows);
        self::assertSame('magic-sensation', $rows[0]['ruleCode']);
        self::assertSame('magic-perception', $rows[0]['sourceRuleCode']);
        self::assertSame('imprecise', $rows[0]['status']);
        self::assertSame(['base' => 5, 'size' => 0], $rows[0]['radius']);
        self::assertSame(-3, $rows[0]['value']);
    }

    /**
     * Значение зависит от Контроля: 5↑ даёт +1 к 4↑.
     *
     * @return void
     */
    public function testSenseValueFollowsControl(): void
    {
        $rows = (new GameSpellEffect())->runtimeSenses(
            $this->slice(),
            [$this->spell(['base' => 4, 'size' => -1], ['base' => 4, 'size' => -1])],
            $this->sheet(['base' => 5, 'size' => 1]),
        );

        self::assertSame(1, $rows[0]['value']);
    }

    /**
     * Без активных строк эффектов нет.
     *
     * @return void
     */
    public function testNoActiveSpellsMeansNoEffects(): void
    {
        self::assertSame(
            [],
            (new GameSpellEffect())->runtimeSenses($this->slice(), [], $this->sheet(['base' => 4, 'size' => 0])),
        );
    }

    /**
     * Эффекты у мгновенного заклинания — отказ, а не молчаливая пустышка.
     *
     * @return void
     */
    public function testInstantSpellWithEffectsIsRejected(): void
    {
        $instant = new CharacterResolvedRule(
            new RuleVersionRecord(
                11,
                2,
                'magic-perception',
                true,
                'ability',
                'Восприятие магии',
                '',
                [
                    'type' => 'spell',
                    'spell' => [
                        'duration' => ['type' => 'instant'],
                        'effects' => [['type' => 'sense', 'sense_code' => 'magic-sensation']],
                    ],
                ],
                [],
                [],
                'needs_work',
                '',
                DateTime::now(),
            ),
            [],
        );
        $spec = $instant->getSpec();
        self::assertInstanceOf(AbilitySpellSpec::class, $spec);

        $this->expectException(GameInvalidException::class);

        (new GameSpellEffect())->assertSupported($spec);
    }

    /**
     * Срез с путём, чувством и заклинанием.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('magic-sensation', 'sense', [
                'type' => 'sense',
                'status' => 'imprecise',
                'radius' => ['base' => 0, 'size' => 0],
            ]),
            $this->rule('arcanist', 'magic_path', [
                'type' => 'magic_path',
                'check_code' => 'check-intellect',
                'cast_check_code' => 'check-spell-cast',
                'control_characteristic_code' => 'magic-control',
                'power_characteristic_code' => 'magic-power',
                'includes_path_codes' => [],
            ]),
            $this->rule('magic-perception', 'ability', [
                'type' => 'spell',
                'spell' => [
                    'power' => ['base' => 4, 'size' => -1],
                    'control' => ['base' => 4, 'size' => 0],
                    'duration' => ['type' => 'sustained', 'power' => ['base' => 4, 'size' => -1]],
                    'targeting' => 'self',
                    'effects' => [[
                        'type' => 'sense',
                        'sense_code' => 'magic-sensation',
                        'status' => 'imprecise',
                        'radius' => ['from' => 'sustain_power'],
                        'value' => ['from' => 'control', 'reference' => ['base' => 4, 'size' => 1]],
                    ]],
                ],
            ]),
        ], []);
    }

    /**
     * Строка активного заклинания.
     *
     * @param array{base: int, size: int} $usedPower Мощь каста.
     * @param array{base: int, size: int} $sustainPower Мощь поддержания.
     *
     * @return GameActiveSpellRecord Строка.
     */
    private function spell(array $usedPower, array $sustainPower): GameActiveSpellRecord
    {
        return new GameActiveSpellRecord(
            1,
            1,
            1,
            1,
            'character',
            1,
            'magic-perception',
            'arcanist',
            'core',
            'sustained',
            $usedPower,
            $sustainPower,
            [],
            [],
            1,
            'character:1',
            1,
            1,
        );
    }

    /**
     * Лист с Контролем.
     *
     * @param array{base: int, size: int} $control Контроль.
     *
     * @return array<string, mixed> Лист.
     */
    private function sheet(array $control): array
    {
        return [
            'characteristics' => [[
                'characteristicCode' => 'magic-control',
                'value' => $control,
            ]],
        ];
    }

    /**
     * Пункт среза.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $spec Спека.
     *
     * @return CharacterResolvedRule Пункт.
     */
    private function rule(string $code, string $type, array $spec): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                10,
                1,
                $code,
                true,
                $type,
                $code,
                '',
                $spec,
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
