<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Exception\ActionException;
use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterAbilityChoice;
use Mifrial\Roleplay\Character\Dto\CharacterChoices;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationPlan;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameSpellCastSaturation;
use Mifrial\Roleplay\Game\Service\GameSpellCastSaturationSteps;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use PHPUnit\Framework\TestCase;

/**
 * Насыщение: источник, шаги, прирост Мощи и остаток успеха.
 */
final class GameSpellCastSaturationTest extends TestCase
{
    /**
     * Первый изученный источник насыщения.
     *
     * @return void
     */
    public function testFindsFirstLearnedSource(): void
    {
        $source = $this->service()->source(
            $this->slice([
                $this->rule('unlearned', $this->saturation(1, 2, 1)),
                $this->rule('saturation', $this->saturation(2, 2, 1)),
            ]),
            $this->model([
                $this->ability('unlearned', 0),
                $this->ability('saturation', 1),
            ]),
            ['unlearned' => 0, 'saturation' => 1],
        );

        self::assertNotNull($source);
        self::assertSame('saturation', $source->getRuleCode());
        self::assertSame(2, $source->getMinRating());
        self::assertSame(2, $source->getRatingPerStep());
        self::assertSame(1, $source->getPowerPerStep());
    }

    /**
     * Без изученного источника насыщения нет.
     *
     * @return void
     */
    public function testWithoutLearnedSource(): void
    {
        $source = $this->service()->source(
            $this->slice([$this->rule('saturation', $this->saturation(2, 2, 1))]),
            $this->model([$this->ability('saturation', 0)]),
            ['saturation' => 0],
        );

        self::assertNull($source);
    }

    /**
     * Карточка без формы насыщения не считается источником.
     *
     * @return void
     */
    public function testIgnoresCardWithoutSaturation(): void
    {
        $source = $this->service()->source(
            $this->slice([$this->rule('plain', ['type' => 'skill'])]),
            $this->model([$this->ability('plain', 1)]),
            ['plain' => 1],
        );

        self::assertNull($source);
    }

    /**
     * Успех делится на шаги по rating_per_step.
     *
     * @return void
     */
    public function testPlansMaximumSteps(): void
    {
        $source = $this->service()->source(
            $this->slice([$this->rule('saturation', $this->saturation(2, 2, 1))]),
            $this->model([$this->ability('saturation', 1)]),
            ['saturation' => 1],
        );
        self::assertNotNull($source);

        $plan = $this->service()->plan($source, $this->check(true, true, 5));

        self::assertSame(5, $plan->getRating());
        self::assertSame(2, $plan->getMaxSteps());
        self::assertTrue($plan->hasChoice());
    }

    /**
     * Ниже min_rating шагов нет даже при успехе.
     *
     * @return void
     */
    public function testBelowMinRatingHasNoSteps(): void
    {
        $plan = $this->service()->plan($this->source(), $this->check(true, true, 1));

        self::assertSame(0, $plan->getMaxSteps());
        self::assertFalse($plan->hasChoice());
    }

    /**
     * Провал проверки не даёт шагов.
     *
     * @return void
     */
    public function testFailedCastHasNoSteps(): void
    {
        $plan = $this->service()->plan($this->source(), $this->check(false, true, 5));

        self::assertSame(0, $plan->getMaxSteps());
    }

    /**
     * Пропущенная проверка не даёт шагов.
     *
     * @return void
     */
    public function testSkippedCheckHasNoSteps(): void
    {
        $plan = $this->service()->plan($this->source(), $this->check(true, false, 5));

        self::assertSame(0, $plan->getMaxSteps());
    }

    /**
     * Шаг тратит rating_per_step и даёт power_per_step.
     *
     * @return void
     */
    public function testAppliesSteps(): void
    {
        $result = $this->service()->apply($this->plan(5, 2), 1);

        self::assertSame(1, $result->getSteps());
        self::assertSame(2, $result->getRatingSpent());
        self::assertSame(1, $result->getPowerDelta());
        self::assertSame(3, $result->getRemainingRating());
        self::assertSame('saturation', $result->getSource()?->getRuleCode());
    }

    /**
     * Ноль шагов сохраняет весь успех.
     *
     * @return void
     */
    public function testZeroStepsKeepsRating(): void
    {
        $result = $this->service()->apply($this->plan(5, 2), 0);

        self::assertSame(0, $result->getRatingSpent());
        self::assertSame(0, $result->getPowerDelta());
        self::assertSame(5, $result->getRemainingRating());
    }

    /**
     * Больше maxSteps потратить нельзя.
     *
     * @return void
     */
    public function testRejectsOverMaximum(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->service()->apply($this->plan(5, 2), 3);
    }

    /**
     * Отрицательные шаги отклоняются.
     *
     * @return void
     */
    public function testRejectsNegativeSteps(): void
    {
        $this->expectException(GameInvalidException::class);
        $this->service()->apply($this->plan(5, 2), -1);
    }

    /**
     * Парсер принимает целое неотрицательное.
     *
     * @return void
     */
    public function testParsesSteps(): void
    {
        $parser = new GameSpellCastSaturationSteps();

        self::assertSame(0, $parser->parse(0));
        self::assertSame(2, $parser->parse(2));
    }

    /**
     * Парсер отклоняет дробное, строку и отрицательное.
     *
     * @param mixed $value Значение.
     *
     * @return void
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidStepsProvider')]
    public function testRejectsInvalidSteps(mixed $value): void
    {
        $this->expectException(ActionException::class);
        (new GameSpellCastSaturationSteps())->parse($value);
    }

    /**
     * Неверные значения шагов.
     *
     * @return iterable<string, array{mixed}> Наборы.
     */
    public static function invalidStepsProvider(): iterable
    {
        yield 'negative' => [-1];
        yield 'fraction' => [1.5];
        yield 'string' => ['2'];
        yield 'null' => [null];
    }

    /**
     * Сервис.
     *
     * @return GameSpellCastSaturation Сервис.
     */
    private function service(): GameSpellCastSaturation
    {
        return new GameSpellCastSaturation();
    }

    /**
     * План с источником.
     *
     * @param int $rating Успех.
     * @param int $maxSteps Максимум шагов.
     *
     * @return GameSpellCastSaturationPlan План.
     */
    private function plan(int $rating, int $maxSteps): GameSpellCastSaturationPlan
    {
        return new GameSpellCastSaturationPlan($this->source(), $rating, $maxSteps);
    }

    /**
     * Источник из контента.
     *
     * @return \Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationSource Источник.
     */
    private function source(): \Mifrial\Roleplay\Game\Dto\GameSpellCastSaturationSource
    {
        $source = $this->service()->source(
            $this->slice([$this->rule('saturation', $this->saturation(2, 2, 1))]),
            $this->model([$this->ability('saturation', 1)]),
            ['saturation' => 1],
        );
        self::assertNotNull($source);

        return $source;
    }

    /**
     * Итог проверки.
     *
     * @param bool $castOk Успех.
     * @param bool $needsCheck Бросок нужен.
     * @param int $rating Успех.
     *
     * @return array<string, mixed> Итог.
     */
    private function check(bool $castOk, bool $needsCheck, int $rating): array
    {
        return [
            'castOk' => $castOk,
            'needsCheck' => $needsCheck,
            'roll' => $needsCheck ? ['success' => $castOk, 'rating' => $rating] : null,
        ];
    }

    /**
     * Срез.
     *
     * @param array<int, CharacterResolvedRule> $rules Карточки.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(array $rules): CharacterRuleSlice
    {
        return new CharacterRuleSlice(1, 1, 'world', $rules, []);
    }

    /**
     * Выборы.
     *
     * @param array<int, CharacterAbilityChoice> $abilities Способности.
     *
     * @return CharacterChoices Выборы.
     */
    private function model(array $abilities): CharacterChoices
    {
        return new CharacterChoices('Caster', null, $abilities, [], [], [], null);
    }

    /**
     * Карточка.
     *
     * @param string $code Код.
     * @param array<string, mixed> $spec Spec.
     *
     * @return CharacterResolvedRule Карточка.
     */
    private function rule(string $code, array $spec): CharacterResolvedRule
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
     * Форма насыщения.
     *
     * @param int $minRating Минимум.
     * @param int $ratingPerStep Успех на шаг.
     * @param int $powerPerStep Мощь на шаг.
     *
     * @return array<string, mixed> Spec.
     */
    private function saturation(int $minRating, int $ratingPerStep, int $powerPerStep): array
    {
        return [
            'type' => 'skill',
            'spell_saturation' => [
                'min_rating' => $minRating,
                'rating_per_step' => $ratingPerStep,
                'power_per_step' => $powerPerStep,
            ],
        ];
    }

    /**
     * Экземпляр способности.
     *
     * @param string $code Код.
     * @param int $level Уровень.
     *
     * @return CharacterAbilityChoice Экземпляр.
     */
    private function ability(string $code, int $level): CharacterAbilityChoice
    {
        return new CharacterAbilityChoice($code, $level, 'arcanist', 'arcanist', null, null, null);
    }
}
