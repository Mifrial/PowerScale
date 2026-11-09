<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameSpellCastUpgradeResolution;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameSpellCastUpgrades;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет применимость и расчёт улучшений каста.
 */
final class GameSpellCastUpgradesTest extends TestCase
{
    /**
     * Улучшение пути даёт ОД и преимущество.
     *
     * @return void
     */
    public function testAppliesPathUpgrade(): void
    {
        $resolution = $this->resolve(
            [$this->upgradeRule('careful', $this->pathUpgrade())],
            [$this->ability('careful', 'arcanist')],
            'arcanist',
            [['code' => 'careful', 'value' => null]],
        );

        self::assertFalse($resolution->isEmpty());
        self::assertSame(1, $resolution->getActionPointDelta());
        self::assertSame(0, $resolution->getRequiredPowerDelta());
        self::assertSame(0, $resolution->getResistancePenetration());
        self::assertCount(1, $resolution->getApplied());
        self::assertSame('careful', $resolution->getApplied()[0]->getRuleCode());
        self::assertSame('Name', $resolution->getApplied()[0]->getName());
        self::assertCount(1, $resolution->getAdvantages());
        self::assertSame('careful', $resolution->getAdvantages()[0]->getSourceCode());
        self::assertSame(1, $resolution->getAdvantages()[0]->getDelta());
    }

    /**
     * Улучшение чужого пути отклоняется.
     *
     * @return void
     */
    public function testRejectsAnotherPath(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->resolve(
            [$this->upgradeRule('careful', $this->pathUpgrade())],
            [$this->ability('careful', 'arcanist')],
            'psionics',
            [['code' => 'careful', 'value' => null]],
        );
    }

    /**
     * any_path снимает совпадение пути.
     *
     * @return void
     */
    public function testAllowsAnyPath(): void
    {
        $resolution = $this->resolve(
            [$this->upgradeRule('piercing', $this->anyPathUpgrade())],
            [$this->ability('piercing', 'arcanist')],
            'psionics',
            [['code' => 'piercing', 'value' => 2]],
        );

        self::assertSame(2, $resolution->getRequiredPowerDelta());
        self::assertSame(4, $resolution->getResistancePenetration());
    }

    /**
     * Родитель улучшения совпал с заклинанием.
     *
     * @return void
     */
    public function testAppliesParentUpgrade(): void
    {
        $resolution = $this->resolve(
            [$this->upgradeRule('parent', $this->parentUpgrade('lightning-strike'))],
            [$this->ability('parent', 'arcanist')],
            'arcanist',
            [['code' => 'parent', 'value' => null]],
            'lightning-strike',
        );

        self::assertSame(1, $resolution->getActionPointDelta());
    }

    /**
     * Чужое заклинание отклоняется.
     *
     * @return void
     */
    public function testRejectsParentOfAnotherSpell(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->resolve(
            [$this->upgradeRule('parent', $this->parentUpgrade('ice-lance'))],
            [$this->ability('parent', 'arcanist')],
            'arcanist',
            [['code' => 'parent', 'value' => null]],
            'lightning-strike',
        );
    }

    /**
     * Родитель улучшения чужого пути отклоняется.
     *
     * @return void
     */
    public function testRejectsParentUpgradeOfAnotherPath(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->resolve(
            [$this->upgradeRule('parent', $this->parentUpgrade('lightning-strike'))],
            [$this->ability('parent', 'arcanist')],
            'psionics',
            [['code' => 'parent', 'value' => null]],
            'lightning-strike',
        );
    }

    /**
     * Родитель улучшения с кодом пути каста разрешён.
     *
     * @return void
     */
    public function testAllowsParentUpgradeOnCastPath(): void
    {
        $resolution = $this->resolve(
            [$this->upgradeRule('parent', $this->parentUpgrade('lightning-strike'))],
            [$this->ability('parent', 'psionics')],
            'psionics',
            [['code' => 'parent', 'value' => null]],
            'lightning-strike',
        );

        self::assertSame(1, $resolution->getActionPointDelta());
    }

    /**
     * Улучшение без родителя и без домена отклоняется даже при any_path.
     *
     * @return void
     */
    public function testRejectsDomainlessUpgradeWithoutParent(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->resolve(
            [$this->upgradeRule('piercing', $this->anyPathUpgrade())],
            [$this->ability('piercing', '')],
            'arcanist',
            [['code' => 'piercing', 'value' => 1]],
        );
    }

    /**
     * Неизученный код отклоняется.
     *
     * @return void
     */
    public function testRejectsUnknownCode(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->resolve([], [], 'arcanist', [['code' => 'unknown', 'value' => null]]);
    }

    /**
     * Повтор кода отклоняется.
     *
     * @return void
     */
    public function testRejectsDuplicate(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->resolve(
            [$this->upgradeRule('careful', $this->pathUpgrade())],
            [$this->ability('careful', 'arcanist')],
            'arcanist',
            [['code' => 'careful', 'value' => null], ['code' => 'careful', 'value' => null]],
        );
    }

    /**
     * Значение шага считается по формуле.
     *
     * @param int|null $value Значение.
     * @param int $requiredPowerDelta Добавка мощи.
     * @param int $penetration Пробитие.
     *
     * @return void
     */
    #[DataProvider('valueProvider')]
    public function testComputesPenetrationValue(
        ?int $value,
        int $requiredPowerDelta,
        int $penetration,
    ): void {
        $resolution = $this->resolve(
            [$this->upgradeRule('piercing', $this->anyPathUpgrade())],
            [$this->ability('piercing', 'arcanist')],
            'arcanist',
            [['code' => 'piercing', 'value' => $value]],
        );

        self::assertSame($requiredPowerDelta, $resolution->getRequiredPowerDelta());
        self::assertSame($penetration, $resolution->getResistancePenetration());
    }

    /**
     * Значения шага.
     *
     * @return iterable<string, array{int|null, int, int}> Наборы.
     */
    public static function valueProvider(): iterable
    {
        yield 'default' => [null, 1, 2];
        yield 'zero' => [0, 0, 0];
        yield 'one' => [1, 1, 2];
        yield 'two' => [2, 2, 4];
    }

    /**
     * Неподдержанные примитивы отклоняются по форме.
     *
     * @param array<string, mixed> $upgrade Улучшение.
     *
     * @return void
     */
    #[DataProvider('unsupportedProvider')]
    public function testRejectsUnsupportedPrimitives(array $upgrade): void
    {
        $this->expectException(GameInvalidException::class);
        $this->resolve(
            [$this->upgradeRule('synthetic', $this->skill(['spell_upgrade' => $upgrade]))],
            [$this->ability('synthetic', 'arcanist')],
            'arcanist',
            [['code' => 'synthetic', 'value' => null]],
        );
    }

    /**
     * Неподдержанные примитивы.
     *
     * @return iterable<string, array{array<string, mixed>}> Наборы.
     */
    public static function unsupportedProvider(): iterable
    {
        yield 'chain' => [[
            'action_point_delta' => 0,
            'chain' => [
                'damage_size_per_hop' => 1,
                'min' => ['base' => 1, 'size' => 0],
                'retarget' => 'nearest',
                'same_target' => 'forbidden',
            ],
        ]];
        yield 'charge cap' => [[
            'action_point_delta' => 0,
            'charge_cap' => ['base' => 1, 'experience_keyword_code' => 'magic'],
        ]];
    }

    /**
     * Права даёт только экземпляр нужного пути.
     *
     * @return void
     */
    public function testUsesOnlyMatchingDomainInstance(): void
    {
        $resolution = $this->resolve(
            [$this->upgradeRule('careful', $this->pathUpgrade())],
            [$this->ability('careful', 'arcanist'), $this->ability('careful', 'psionics')],
            'psionics',
            [['code' => 'careful', 'value' => null]],
        );

        self::assertSame(1, $resolution->getActionPointDelta());
    }

    /**
     * Значение у улучшения без пробития отклоняется.
     *
     * @return void
     */
    public function testRejectsValueForNonPenetration(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->resolve(
            [$this->upgradeRule('careful', $this->pathUpgrade())],
            [$this->ability('careful', 'arcanist')],
            'arcanist',
            [['code' => 'careful', 'value' => 1]],
        );
    }

    /**
     * Код карточки и domain_ref ни на что не влияют.
     *
     * @return void
     */
    public function testIgnoresCardCodeAndDomainRef(): void
    {
        $resolution = $this->resolve(
            [$this->upgradeRule('arbitrary-code', $this->pathUpgrade(['domain_ref' => 'weapon-family']))],
            [$this->ability('arbitrary-code', 'arcanist')],
            'arcanist',
            [['code' => 'arbitrary-code', 'value' => null]],
        );

        self::assertSame(1, $resolution->getActionPointDelta());
    }

    /**
     * Карточка с «контентным» кодом без формы улучшения отклоняется.
     *
     * @return void
     */
    public function testRejectsKnownCodeWithoutForm(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->resolve(
            [$this->upgradeRule('careful', $this->skill(['type' => 'skill']))],
            [$this->ability('careful', 'arcanist')],
            'arcanist',
            [['code' => 'careful', 'value' => null]],
        );
    }

    /**
     * Пустой выбор — пустое разрешение.
     *
     * @return void
     */
    public function testEmptySelection(): void
    {
        $resolution = $this->resolve([], [], 'arcanist', []);

        self::assertTrue($resolution->isEmpty());
        self::assertSame(0, $resolution->getActionPointDelta());
        self::assertSame([], $resolution->getAdvantages());
    }

    /**
     * Разрешает выбор.
     *
     * @param array<int, CharacterResolvedRule> $rules Карточки.
     * @param array<int, CharacterAbilityChoice> $abilities Экземпляры.
     * @param string $pathCode Путь каста.
     * @param array<int, array{code: string, value: int|null}> $selected Выбор.
     * @param string $spellCode Заклинание.
     *
     * @return GameSpellCastUpgradeResolution Разрешение.
     */
    private function resolve(
        array $rules,
        array $abilities,
        string $pathCode,
        array $selected,
        string $spellCode = 'lightning-strike',
    ): GameSpellCastUpgradeResolution {
        $slice = new CharacterRuleSlice(1, 1, 'world', $rules, []);
        $model = new CharacterChoices('Caster', null, $abilities, [], [], [], null);
        $levels = [];
        foreach ($abilities as $ability) {
            $levels[$ability->getRuleCode()] = $ability->getLevel();
        }

        return (new GameSpellCastUpgrades())->resolve(
            $slice,
            $model,
            $levels,
            $spellCode,
            $pathCode,
            $selected,
        );
    }

    /**
     * Карточка улучшения.
     *
     * @param string $code Код.
     * @param array<string, mixed> $spec Spec.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function upgradeRule(string $code, array $spec): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                'ability',
                'Name',
                '',
                $spec,
                [],
                [],
                'ready',
                '',
                DateTime::now(),
            ),
            [],
        );
    }

    /**
     * Форма навыка.
     *
     * @param array<string, mixed> $extra Поля.
     *
     * @return array<string, mixed> Spec.
     */
    private function skill(array $extra): array
    {
        return array_merge(['type' => 'skill'], $extra);
    }

    /**
     * Улучшение пути.
     *
     * @param array<string, mixed> $extra Поля.
     *
     * @return array<string, mixed> Spec.
     */
    private function pathUpgrade(array $extra = []): array
    {
        return $this->skill(array_merge([
            'spell_upgrade' => ['action_point_delta' => 1, 'check_advantage' => 1],
        ], $extra));
    }

    /**
     * Улучшение любого пути.
     *
     * @return array<string, mixed> Spec.
     */
    private function anyPathUpgrade(): array
    {
        return $this->skill([
            'spell_upgrade' => [
                'action_point_delta' => 0,
                'any_path' => true,
                'resistance_penetration_per_step' => 2,
            ],
        ]);
    }

    /**
     * Улучшение конкретного заклинания.
     *
     * @param string $parent Код заклинания.
     *
     * @return array<string, mixed> Spec.
     */
    private function parentUpgrade(string $parent): array
    {
        return $this->skill([
            'parent_ability_code' => $parent,
            'spell_upgrade' => ['action_point_delta' => 1],
        ]);
    }

    /**
     * Экземпляр способности.
     *
     * @param string $code Код.
     * @param string $domainCode Путь.
     *
     * @return CharacterAbilityChoice Экземпляр.
     */
    private function ability(string $code, string $domainCode): CharacterAbilityChoice
    {
        return new CharacterAbilityChoice($code, 1, $domainCode, $domainCode, null, null, null);
    }
}
