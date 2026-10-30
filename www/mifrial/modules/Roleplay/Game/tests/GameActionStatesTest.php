<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Tests;

use Mifrial\Core\Kernel\Value\DateTime;
use Mifrial\Roleplay\Character\Dto\CharacterResolvedRule;
use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Game\Service\GameActionStates;
use Mifrial\Roleplay\Rule\Dto\RuleVersionRecord;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\AbilityActionSpec;
use PHPUnit\Framework\TestCase;

/**
 * Действие пишет состояния по форме спеки: apply_state, posture и снятие устойчивости.
 */
final class GameActionStatesTest extends TestCase
{
    /**
     * apply_state с числом кладёт value.
     *
     * @return void
     */
    public function testApplyStateWritesNumber(): void
    {
        $slice = $this->slice($this->action([
            'action_effects' => [['type' => 'apply_state', 'state_code' => 'unstable', 'amount' => 2]],
        ]));

        self::assertSame(
            [['kind' => 'putState', 'stateRuleCode' => 'unstable', 'value' => 2]],
            (new GameActionStates())->operations($slice, [], $this->spec($slice)),
        );
    }

    /**
     * Флаг пишется без value, размерное значение — парой.
     *
     * @return void
     */
    public function testApplyStateWritesFlagAndDimensional(): void
    {
        $slice = $this->slice($this->action([
            'action_effects' => [
                ['type' => 'apply_state', 'state_code' => 'lying'],
                [
                    'type' => 'apply_state',
                    'state_code' => 'sized',
                    'amount' => ['base' => 3, 'size' => -1],
                ],
            ],
        ]));

        self::assertSame([
            ['kind' => 'putState', 'stateRuleCode' => 'lying'],
            ['kind' => 'putState', 'stateRuleCode' => 'sized', 'value' => ['base' => 3, 'size' => -1]],
        ], (new GameActionStates())->operations($slice, [], $this->spec($slice)));
    }

    /**
     * Чужой код состояния отвергается до записи.
     *
     * @return void
     */
    public function testApplyStateRejectsUnknownState(): void
    {
        $slice = $this->slice($this->action([
            'action_effects' => [['type' => 'apply_state', 'state_code' => 'missing']],
        ]));

        $this->expectException(GameInvalidException::class);
        (new GameActionStates())->operations($slice, [], $this->spec($slice));
    }

    /**
     * posture переключает лежачее положение.
     *
     * @return void
     */
    public function testPostureTogglesLying(): void
    {
        $slice = $this->slice($this->action([
            'operations' => [['type' => 'posture', 'posture' => 'standing_or_prone']],
        ]));
        $states = new GameActionStates();
        $spec = $this->spec($slice);

        self::assertSame(
            [['kind' => 'putState', 'stateRuleCode' => 'lying']],
            $states->operations($slice, [], $spec),
        );
        self::assertSame(
            [['kind' => 'removeState', 'id' => 4]],
            $states->operations($slice, ['states' => [['stateRuleCode' => 'lying', 'id' => 4]]], $spec),
        );
    }

    /**
     * Неизвестная поза отвергается.
     *
     * @return void
     */
    public function testPostureRejectsUnknownValue(): void
    {
        $slice = $this->slice($this->action([
            'operations' => [['type' => 'posture', 'posture' => 'sitting']],
        ]));

        $this->expectException(GameInvalidException::class);
        (new GameActionStates())->operations($slice, [], $this->spec($slice));
    }

    /**
     * Восстановление устойчивости снимает строки неустойчивости.
     *
     * @return void
     */
    public function testRecoverStabilityRemovesUnstableRows(): void
    {
        $slice = $this->slice($this->action(['combat_action' => 'recover-stability']));
        $states = new GameActionStates();
        $spec = $this->spec($slice);

        self::assertSame([], $states->operations($slice, [], $spec));
        self::assertSame(
            [['kind' => 'removeState', 'id' => 7]],
            $states->operations($slice, ['states' => [
                ['stateRuleCode' => 'unstable', 'value' => 2, 'id' => 7],
                ['stateRuleCode' => 'lying', 'id' => 8],
            ]], $spec),
        );

        $without = $this->slice(
            $this->action(['combat_action' => 'recover-stability']),
            [['code' => 'lying', 'spec' => ['value_type' => 'flag', 'lying' => true]]],
        );
        self::assertSame([], $states->operations(
            $without,
            ['states' => [['stateRuleCode' => 'unstable', 'id' => 7]]],
            $this->spec($without),
        ));
    }

    /**
     * Другая операция остаётся вне серверного сценария.
     *
     * @return void
     */
    public function testForeignOperationIsRejected(): void
    {
        $slice = $this->slice($this->action([
            'operations' => [['type' => 'turn', 'max_degrees' => 90]],
        ]));

        $this->expectException(GameInvalidException::class);
        (new GameActionStates())->operations($slice, [], $this->spec($slice));
    }

    /**
     * Нет однозначного лежачего состояния — отказ.
     *
     * @return void
     */
    public function testMissingLyingFlagIsRejected(): void
    {
        $slice = $this->slice(
            $this->action(['operations' => [['type' => 'posture', 'posture' => 'standing_or_prone']]]),
            [['code' => 'unstable', 'spec' => ['value_type' => 'number']]],
        );

        $this->expectException(GameInvalidException::class);
        (new GameActionStates())->operations($slice, [], $this->spec($slice));
    }

    /**
     * Спека действия из среза.
     *
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return AbilityActionSpec Спека.
     */
    private function spec(CharacterRuleSlice $slice): AbilityActionSpec
    {
        $spec = $slice->findLive('card')?->getSpec();
        self::assertInstanceOf(AbilityActionSpec::class, $spec);

        return $spec;
    }

    /**
     * Срез с карточкой и состояниями.
     *
     * @param CharacterResolvedRule $card Карточка действия.
     * @param array<int, array{code: string, spec: array<string, mixed>}> $states Состояния.
     *
     * @return CharacterRuleSlice Срез.
     */
    private function slice(CharacterResolvedRule $card, array $states = []): CharacterRuleSlice
    {
        $live = [$card];
        foreach ($states === [] ? $this->defaultStates() : $states as $state) {
            $live[] = $this->rule($state['code'], 'state', $state['spec']);
        }

        return new CharacterRuleSlice(1, 1, 'world', $live, []);
    }

    /**
     * Лежачее и неустойчивость по умолчанию.
     *
     * @return array<int, array{code: string, spec: array<string, mixed>}> Состояния.
     */
    private function defaultStates(): array
    {
        return [
            ['code' => 'lying', 'spec' => ['value_type' => 'flag', 'lying' => true]],
            ['code' => 'unstable', 'spec' => ['value_type' => 'number', 'unstable' => true]],
            ['code' => 'sized', 'spec' => ['value_type' => 'dimensional']],
        ];
    }

    /**
     * Действие.
     *
     * @param array<string, mixed> $fields Поля спеки.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function action(array $fields): CharacterResolvedRule
    {
        return $this->rule('card', 'ability', ['type' => 'action', ...$fields]);
    }

    /**
     * Запись правила.
     *
     * @param string $code Код.
     * @param string $type Тип.
     * @param array<string, mixed> $document Спека.
     *
     * @return CharacterResolvedRule Правило.
     */
    private function rule(string $code, string $type, array $document): CharacterResolvedRule
    {
        return new CharacterResolvedRule(
            new RuleVersionRecord(
                1,
                1,
                $code,
                true,
                $type,
                'Name',
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
