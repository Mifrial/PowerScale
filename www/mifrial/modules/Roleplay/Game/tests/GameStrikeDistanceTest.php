<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameStrikeAttack;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameStrikeDistance;
use Mifrial\Roleplay\Game\Service\GameStrikeMovement;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;
use PHPUnit\Framework\TestCase;

/**
 * Дистанция удара: границы профиля, шаг расы, дальнобойность и штраф силы.
 */
final class GameStrikeDistanceTest extends TestCase
{
    /**
     * Ипари-дистанция внутри границ профиля.
     *
     * @return void
     */
    public function testMetricsUseIpariRange(): void
    {
        $slice = $this->slice([$this->item('sword', $this->strikeProfile(2))]);
        $attack = $this->attack($slice, 'sword', []);

        $metrics = $this->distance()->metrics($attack, $this->ipari(1), '');

        self::assertSame(1, $metrics['distance']->getBase());
        self::assertSame(0, $metrics['min']->getBase());
        self::assertSame(2, $metrics['max']?->getBase());
        self::assertNull($metrics['falloff']);
        self::assertSame(1, $metrics['rangeSize']);
        self::assertSame(0, $metrics['drop']);
    }

    /**
     * Дистанция вне границ профиля отклоняется.
     *
     * @return void
     */
    public function testMetricsRejectOutOfRange(): void
    {
        $slice = $this->slice([$this->item('sword', $this->strikeProfile(2))]);
        $attack = $this->attack($slice, 'sword', []);

        $this->expectException(GameInvalidException::class);
        $this->distance()->metrics($attack, $this->ipari(3), '');
    }

    /**
     * Шаг расы переводит step-дистанцию в ипари.
     *
     * @return void
     */
    public function testStepDistanceUsesRaceStep(): void
    {
        $slice = $this->slice([
            $this->item('sword', $this->strikeProfile(10)),
            $this->race('dwarf', 2),
        ]);
        $attack = $this->attack($slice, 'sword', ['raceCode' => 'dwarf']);

        $metrics = $this->distance()->metrics($attack, $this->steps(1), '');

        self::assertSame(2, $metrics['distance']->getBase());
        self::assertSame(0, $metrics['distance']->getSize());
    }

    /**
     * Дальнобойность считает диапазоны и штраф силы броска.
     *
     * @return void
     */
    public function testThrowFalloffAndDrop(): void
    {
        $slice = $this->slice([$this->item('dart', $this->throwProfile())]);
        $attack = $this->attack($slice, 'dart', [], $this->context(4));

        $metrics = $this->distance()->metrics($attack, $this->ipari(11), '');

        self::assertSame(5, $metrics['falloff']?->getBase());
        self::assertSame(3, $metrics['rangeSize']);
        self::assertSame(2, $metrics['drop']);
    }

    /**
     * Слишком тяжёлый бросок не долетает.
     *
     * @return void
     */
    public function testThrowRejectsInsufficientStrength(): void
    {
        $profile = $this->throwProfile();
        $slice = $this->slice([
            $this->item('dart', $profile, ['min_strength' => ['base' => 5, 'size' => 0]]),
        ]);
        $attack = $this->attack($slice, 'dart', [], $this->context(4));

        $this->expectException(GameInvalidException::class);
        $this->distance()->metrics($attack, $this->ipari(11), '');
    }

    /**
     * «Выпад» расширяет максимум на полный шаг.
     *
     * @return void
     */
    public function testReachBonusAddsStepToStrikeMax(): void
    {
        $slice = $this->slice([
            $this->item('sword', $this->strikeProfile(1)),
            $this->action('lunge', 1),
        ]);
        $attack = $this->attack($slice, 'sword', []);

        $metrics = $this->distance()->metrics($attack, $this->ipari(2), 'lunge');

        self::assertSame(2, $metrics['max']?->getBase());
        self::assertSame(0, $metrics['max']?->getSize());
    }

    /**
     * Полшага «Выпада» читается по шкале.
     *
     * @return void
     */
    public function testReachBonusUsesHalfStep(): void
    {
        $slice = $this->slice([
            $this->item('sword', $this->strikeProfile(1)),
            $this->action('lunge', 0.5),
        ]);
        $attack = $this->attack($slice, 'sword', []);

        $metrics = $this->distance()->metrics($attack, $this->ipari(1), 'lunge');

        self::assertSame(3, $metrics['max']?->getBase());
        self::assertSame(-1, $metrics['max']?->getSize());
    }

    /**
     * Сервис дистанции на чистом обходе формулы.
     *
     * @return GameStrikeDistance Дистанция.
     */
    private function distance(): GameStrikeDistance
    {
        return new GameStrikeDistance(new FormulaEvaluations(), new GameStrikeMovement());
    }

    /**
     * Снимок атаки по предмету и выбору.
     *
     * @param CharacterRuleSlice $slice Срез.
     * @param string $itemCode Предмет.
     * @param array<string, mixed> $choices Выборы.
     * @param FormulaContext|null $context Контекст.
     *
     * @return GameStrikeAttack Снимок.
     */
    private function attack(
        CharacterRuleSlice $slice,
        string $itemCode,
        array $choices,
        ?FormulaContext $context = null,
    ): GameStrikeAttack {
        $spec = $slice->findLive($itemCode)?->getSpec();
        self::assertInstanceOf(ItemSpec::class, $spec);
        $profile = $spec->getWeapon()?->getWeaponProfiles()[0] ?? null;
        self::assertInstanceOf(WeaponProfile::class, $profile);

        return new GameStrikeAttack(
            $slice,
            ['sheet' => ['racialAbilityCodes' => []], 'choices' => $choices, 'key' => null],
            $spec,
            $profile,
            $context ?? new FormulaContext(),
        );
    }

    /**
     * Контекст с силой.
     *
     * @param int $strength Сила.
     *
     * @return FormulaContext Контекст.
     */
    private function context(int $strength): FormulaContext
    {
        return new FormulaContext(['strength' => new DimensionalNumber($strength, 0)]);
    }

    /**
     * Условия с ипари-дистанцией.
     *
     * @param int $distance Дистанция.
     *
     * @return array<string, mixed> Условия.
     */
    private function ipari(int $distance): array
    {
        return [
            'distance' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => $distance]],
        ];
    }

    /**
     * Условия с дистанцией в шагах.
     *
     * @param int $distance Дистанция.
     *
     * @return array<string, mixed> Условия.
     */
    private function steps(int $distance): array
    {
        return [
            'distance' => ['type' => 'step', 'value' => ['type' => 'fixed', 'value' => $distance]],
        ];
    }

    /**
     * Срез с переданными правилами.
     *
     * @param array<int, RuleVersionRecord> $records Карточки.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $records): CharacterRuleSlice
    {
        $rules = [];
        foreach ($records as $record) {
            $rules[] = new CharacterResolvedRule($record, []);
        }

        return new CharacterRuleSlice(1, 1, 'world', $rules, []);
    }

    /**
     * Карточка предмета.
     *
     * @param string $code Код.
     * @param array<string, mixed> $profile Профиль.
     * @param array<string, mixed> $weapon Дополнительные поля оружия.
     *
     * @return RuleVersionRecord Карточка.
     */
    private function item(string $code, array $profile, array $weapon = []): RuleVersionRecord
    {
        return $this->record('item', $code, [
            'category' => 'weapon',
            'weapon' => array_merge($weapon, ['weapon_profiles' => [$profile]]),
        ]);
    }

    /**
     * Карточка расы.
     *
     * @param string $code Код.
     * @param int $step Шаг.
     *
     * @return RuleVersionRecord Карточка.
     */
    private function race(string $code, int $step): RuleVersionRecord
    {
        return $this->record('race', $code, [
            'cost_os' => 0,
            'movement_step' => ['base' => $step, 'size' => 0],
        ]);
    }

    /**
     * Карточка действия с досягаемостью.
     *
     * @param string $code Код.
     * @param int|float $fraction Доля шага.
     *
     * @return RuleVersionRecord Карточка.
     */
    private function action(string $code, int|float $fraction): RuleVersionRecord
    {
        return $this->record('ability', $code, [
            'type' => 'action',
            'action_components' => [],
            'action_effects' => [[
                'type' => 'current_action_attack_reach',
                'step_fraction' => $fraction,
                'scope' => ['components' => ['strike'], 'hit_count' => 1],
            ]],
            'zones' => ['or' => ['kind' => 'automatic']],
            'distinct_weapons' => false,
            'same_weapon' => false,
            'lift_parent_max_weapons' => false,
            'peak_concentration' => false,
            'will_focus' => false,
            'long_tension' => false,
            'multiple' => false,
        ]);
    }

    /**
     * Профиль ближнего боя.
     *
     * @param int $max Максимум.
     *
     * @return array<string, mixed> Профиль.
     */
    private function strikeProfile(int $max): array
    {
        return [
            'type' => 'strike',
            'distance' => [
                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
                'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => $max]],
            ],
            'damage' => ['damage_type_code' => 'slashing'],
        ];
    }

    /**
     * Профиль броска.
     *
     * @return array<string, mixed> Профиль.
     */
    private function throwProfile(): array
    {
        return [
            'type' => 'throw',
            'distance' => [
                'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
                'max' => null,
            ],
            'falloff' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 5]],
            'damage' => ['damage_type_code' => 'piercing'],
        ];
    }

    /**
     * Карточка правила.
     *
     * @param string $type Тип.
     * @param string $code Код.
     * @param array<string, mixed> $spec Документ spec.
     *
     * @return RuleVersionRecord Карточка.
     */
    private function record(string $type, string $code, array $spec): RuleVersionRecord
    {
        return new RuleVersionRecord(
            1,
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
        );
    }
}
