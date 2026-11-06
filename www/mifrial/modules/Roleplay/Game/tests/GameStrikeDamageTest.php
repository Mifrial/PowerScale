<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameStrikeAttack;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameStrikeDamage;
use Mifrial\Roleplay\Rule\Dto\FormulaContext;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\ItemSpec;
use Mifrial\Roleplay\Rule\Dto\Spec\Item\WeaponProfile;
use Mifrial\Roleplay\Rule\Service\FormulaEvaluations;
use Mifrial\Roleplay\Rule\Value\CharacteristicNumber;
use PHPUnit\Framework\TestCase;

/**
 * Урон, пробитие и сила подготовленного удара.
 */
final class GameStrikeDamageTest extends TestCase
{
    /**
     * Урон читается из формулы профиля.
     *
     * @return void
     */
    public function testAmountUsesProfileFormula(): void
    {
        $attack = $this->attack(['type' => 'fixed', 'value' => 6], []);

        self::assertSame(6, $this->damage()->amount($attack)->getBase());
    }

    /**
     * Урон берёт характеристику действия из контекста.
     *
     * @return void
     */
    public function testAmountReadsActionCharacteristic(): void
    {
        $context = (new FormulaContext())
            ->withActionCharacteristic('strike', 'strength', new CharacteristicNumber(4, 0));
        $attack = $this->attack([
            'type' => 'actionCharacteristic',
            'action' => 'strike',
            'characteristic' => 'strength',
        ], [], $context);

        $amount = $this->damage()->amount($attack);

        self::assertSame(4, $amount->getBase());
        self::assertSame(0, $amount->getSize());
    }

    /**
     * Пробитие читается из формулы профиля.
     *
     * @return void
     */
    public function testPenetrationUsesProfileFormula(): void
    {
        $attack = $this->attack(
            ['type' => 'fixed', 'value' => 0],
            ['penetration' => ['type' => 'fixed', 'value' => 2]],
        );

        self::assertSame(2, $this->damage()->penetration($attack)->getBase());
    }

    /**
     * Сила профиля важнее общей характеристики.
     *
     * @return void
     */
    public function testStrengthPrefersActionCharacteristic(): void
    {
        $context = (new FormulaContext(['strength' => new CharacteristicNumber(5, 0)]))
            ->withActionCharacteristic('strike', 'strength', new CharacteristicNumber(4, 0));
        $attack = $this->attack(['type' => 'fixed', 'value' => 0], [], $context);

        self::assertSame(4, $this->damage()->strength($attack)->getBase());
    }

    /**
     * Без базы профиля сила берётся из характеристики.
     *
     * @return void
     */
    public function testStrengthFallsBackToCharacteristic(): void
    {
        $context = new FormulaContext(['strength' => new CharacteristicNumber(5, 0)]);
        $attack = $this->attack(['type' => 'fixed', 'value' => 0], [], $context);

        self::assertSame(5, $this->damage()->strength($attack)->getBase());
    }

    /**
     * Сдвиг карточки применяется к силе.
     *
     * @return void
     */
    public function testStrengthAppliesActionModifier(): void
    {
        $context = (new FormulaContext(['strength' => new CharacteristicNumber(4, 0)]))
            ->withActionCharacteristicModifier(1);
        $attack = $this->attack(['type' => 'fixed', 'value' => 0], [], $context);

        $strength = $this->damage()->strength($attack);

        self::assertSame(5, $strength->getBase());
        self::assertSame(0, $strength->getSize());
    }

    /**
     * Без силы удар не собирается.
     *
     * @return void
     */
    public function testStrengthRequiresCharacteristic(): void
    {
        $attack = $this->attack(['type' => 'fixed', 'value' => 0], []);

        $this->expectException(GameInvalidException::class);
        $this->damage()->strength($attack);
    }

    /**
     * Калькулятор на чистом обходе формулы.
     *
     * @return GameStrikeDamage Калькулятор.
     */
    private function damage(): GameStrikeDamage
    {
        return new GameStrikeDamage(new FormulaEvaluations());
    }

    /**
     * Снимок атаки по профилю.
     *
     * @param array<string, mixed> $damageFormula Формула урона.
     * @param array<string, mixed> $profile Дополнительные поля профиля.
     * @param FormulaContext|null $context Контекст.
     *
     * @return GameStrikeAttack Снимок.
     */
    private function attack(
        array $damageFormula,
        array $profile,
        ?FormulaContext $context = null,
    ): GameStrikeAttack {
        $slice = $this->slice([
            $this->item('sword', array_merge([
                'type' => 'strike',
                'distance' => [
                    'min' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 0]],
                    'max' => ['type' => 'ipari', 'value' => ['type' => 'fixed', 'value' => 2]],
                ],
                'damage' => ['damage_type_code' => 'slashing', 'formula' => $damageFormula],
            ], $profile)),
        ]);

        $spec = $slice->findLive('sword')?->getSpec();
        self::assertInstanceOf(ItemSpec::class, $spec);
        $weaponProfile = $spec->getWeapon()?->getWeaponProfiles()[0] ?? null;
        self::assertInstanceOf(WeaponProfile::class, $weaponProfile);

        return new GameStrikeAttack(
            $slice,
            ['sheet' => [], 'choices' => [], 'key' => null],
            $spec,
            $weaponProfile,
            $context ?? new FormulaContext(),
        );
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
     *
     * @return RuleVersionRecord Карточка.
     */
    private function item(string $code, array $profile): RuleVersionRecord
    {
        return $this->record('item', $code, [
            'category' => 'weapon',
            'weapon' => ['weapon_profiles' => [$profile]],
        ]);
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
