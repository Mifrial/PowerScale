<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameStrikeOptions;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\Effect\OptionalAfterStrikeCheckEffect;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Опции после удара берутся из купленных детей карточки, а не из клиента.
 */
final class GameStrikeOptionsTest extends TestCase
{
    /**
     * Купленный ребёнок карточки даёт опцию с серверной подписью.
     *
     * @return void
     */
    public function testAvailableListsOnlyOwnedChildrenOfCard(): void
    {
        $options = new GameStrikeOptions();
        $rows = $options->available($this->slice(), ['rush' => 1]);

        self::assertCount(1, $rows);
        self::assertSame('card', $rows[0]['actionCode']);
        self::assertSame('rush', $rows[0]['code']);
        self::assertSame('Яростный рывок', $rows[0]['name']);
        self::assertStringContainsString('Проверка на Силу воли против 3', $rows[0]['summary']);
        self::assertStringContainsString('внутренний дробящий урон по себе', $rows[0]['summary']);
        self::assertStringContainsString('помеха действия снимается', $rows[0]['summary']);
    }

    /**
     * Некулепленный ребёнок опцией не становится.
     *
     * @return void
     */
    public function testAvailableSkipsUnownedChild(): void
    {
        self::assertSame([], (new GameStrikeOptions())->available($this->slice(), ['rush' => 0]));
        self::assertSame([], (new GameStrikeOptions())->available($this->slice(), []));
    }

    /**
     * Код ищется только у своей карточки.
     *
     * @return void
     */
    public function testFindRejectsForeignCard(): void
    {
        $options = new GameStrikeOptions();
        $found = $options->find($this->slice(), 'card', ['rush' => 1], 'rush');
        self::assertSame('rush', $found['code']);
        self::assertInstanceOf(OptionalAfterStrikeCheckEffect::class, $found['effect']);

        $this->expectException(GameInvalidException::class);
        $options->find($this->slice(), 'other-card', ['rush' => 1], 'rush');
    }

    /**
     * Срез с карточкой, ребёнком, проверкой и типом урона.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', [
            $this->rule('card', 'ability', 'Размашистый удар', [
                'type' => 'action',
                'zones' => ['or' => ['kind' => 'automatic']],
            ]),
            $this->rule('rush', 'ability', 'Яростный рывок', [
                'type' => 'skill',
                'zones' => ['or' => ['kind' => 'automatic']],
                'parent_ability_code' => 'card',
                'action_effects' => [[
                    'type' => 'optional_after_strike_check',
                    'check_code' => 'will-check',
                    'difficulty' => 3,
                    'self_damage' => [
                        'size_delta' => -1,
                        'damage_type_code' => 'blunt',
                        'internal' => true,
                    ],
                    'skip_parent_pending' => true,
                ]],
            ]),
            $this->rule('will-check', 'check', 'Проверка на Силу воли', [
                'allow_characteristic_override' => false,
                'allowed_modes' => 'both',
                'ordinary_root' => false,
                'concentration_token' => false,
                'willpower' => true,
                'unstable_check' => false,
                'hit_check' => false,
                'initiative' => false,
                'difficulty_input' => ['kind' => 'none', 'state_code' => ''],
            ]),
            $this->rule('blunt', 'damage_type', 'Дробящий', [
                'forms' => ['genitive' => 'дробящего урона', 'dative' => 'дробящему урону'],
                'defense_ignored' => false,
                'modifies_spell_difficulty' => false,
            ]),
        ], []);
    }

    /**
     * Запись правила.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param string $name Имя.
     * @param array<string, mixed> $document Спека.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, string $name, array $document): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                $type,
                $name,
                '',
                $document,
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
