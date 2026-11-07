<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Character\Dto\CharacterRuleSlice;
use Mifrial\Roleplay\Character\Dto\ResourceSpend;
use Mifrial\Roleplay\Game\Dto\GameRecord;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Реакция цели широкого удара: траты, ожидание и жетон концентрации.
 */
final class GameStrikeTargetReaction
{
    /**
     * Карта ожидания после последнего расчёта.
     *
     * @var array<string, mixed>
     */
    private array $pendingAfter = [];

    /**
     * Цикл жетонов после расчёта.
     *
     * @var array<string, mixed>
     */
    private array $cycleAfter = [];

    /**
     * Тратил ли хоть один защитник жетон концентрации.
     *
     * @var bool
     */
    private bool $tokenUsed = false;

    /**
     * Создаёт расчёт реакции.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeSpend $spend Траты реакции.
     * @param GameStrikeTargetChoices $choices Choices целей.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeTargetChoices $choices,
    ) {
    }

    /**
     * Начинает расчёт с карты боя.
     *
     * @param array<string, mixed> $pending Карта.
     * @param array<string, mixed> $cycle Цикл жетонов.
     *
     * @return void
     */
    public function begin(array $pending, array $cycle = []): void
    {
        $this->pendingAfter = $pending;
        $this->cycleAfter = $cycle;
        $this->tokenUsed = false;
    }

    /**
     * Карта ожидания после расчёта.
     *
     * @return array<string, mixed> Карта.
     */
    public function pendingAfter(): array
    {
        return $this->pendingAfter;
    }

    /**
     * Цикл жетонов после расчёта.
     *
     * @return array<string, mixed> Карта.
     */
    public function cycleAfter(): array
    {
        return $this->cycleAfter;
    }

    /**
     * Был ли потрачен жетон концентрации.
     *
     * @return bool Признак.
     */
    public function tokenUsed(): bool
    {
        return $this->tokenUsed;
    }

    /**
     * Траты и карта ожидания реакции цели.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $conditions Условия цели.
     * @param string $hitCode Карточка проверки попадания.
     *
     * @return array{spends: array<int, ResourceSpend>, pending: array<string, mixed>,
     *               slice: CharacterRuleSlice|null, reactionRuleCode: string|null, insufficient: bool,
     *               token: array{spend: ResourceSpend, advantage: RollAdvantage, cycle: array<string, mixed>}|null}
     *
     * @throws GameInvalidException Если поворот или карточка реакции битые.
     */
    public function reactionOf(
        GameRecord $game, array $target, array $choice, array $conditions, string $hitCode,
    ): array {
        [$pending, $targetChoices, $sheet, $spends] = $this->base($game, $target, $choice);
        [$slice, $reactionRuleCode, $spends, $pending] = $this->sliceAndDeclare(
            $game, $target, $choice, $targetChoices, $spends, $pending,
        );
        if (($conditions['turn'] ?? false) === true) {
            $spends = $this->turnSpends($game, $slice, $targetChoices, $sheet, $spends);
        }

        $token = $this->tokenOf($game, $target, $choice, $sheet, $hitCode);
        if ($token !== null) {
            $spends[] = $token['spend'];
        }

        $insufficient = $this->insufficient($game, $target, $sheet, $spends);
        if (!$insufficient && $token !== null) {
            $this->cycleAfter = $token['cycle'];
            $this->tokenUsed = true;
        }

        return [
            'spends' => $spends, 'pending' => $pending, 'slice' => $slice, 'reactionRuleCode' => $reactionRuleCode,
            'insufficient' => $insufficient, 'token' => $insufficient ? null : $token,
        ];
    }

    /**
     * Закрывает объявленные траты реакции.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $pending Карта до реакции.
     * @param array<int, ResourceSpend> $spends Траты.
     * @param string|null $reactionRuleCode Карточка реакции.
     * @param CharacterRuleSlice $slice Срез.
     *
     * @return void
     */
    public function complete(
        GameRecord $game,
        array $target,
        array $pending,
        array $spends,
        ?string $reactionRuleCode,
        CharacterRuleSlice $slice,
    ): void {
        if ($reactionRuleCode === null) {
            return;
        }

        $this->pendingAfter = (new GamePendingActionCosts())->complete(
            $slice,
            $this->choices->targetChoices($game, $target),
            $reactionRuleCode,
            $target['type'],
            $target['id'],
            $pending,
            $this->spend->getSpendAmounts($spends),
        );
    }

    /**
     * База реакции: карта, лист, choices и стартовые траты.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>,
     *               3: array<int, ResourceSpend>}
     */
    private function base(GameRecord $game, array $target, array $choice): array
    {
        $targetChoices = $this->choices->targetChoices($game, $target);
        $sheet = $this->sheets->getTargetSheet($game, $target);

        return [
            $this->pendingAfter,
            $targetChoices,
            $sheet,
            $this->spend->getReactionSpends($game, $choice, $targetChoices, $sheet),
        ];
    }

    /**
     * Срез реакции и объявление её трат.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $targetChoices Choices цели.
     * @param array<int, ResourceSpend> $spends Траты.
     * @param array<string, mixed> $pending Карта.
     *
     * @return array{0: CharacterRuleSlice|null, 1: string|null, 2: array<int, ResourceSpend>,
     *               3: array<string, mixed>}
     */
    private function sliceAndDeclare(
        GameRecord $game, array $target, array $choice, array $targetChoices, array $spends, array $pending,
    ): array {
        $slice = $this->sliceOf($game, $choice);
        $reactionRuleCode = $slice === null
            ? null
            : $this->rules->cards->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), $choice['reaction']);
        if ($slice !== null && $reactionRuleCode !== null) {
            [$spends, $pending] = $this->declare($target, $slice, $reactionRuleCode, $targetChoices, $spends, $pending);
        }

        return [$slice, $reactionRuleCode, $spends, $pending];
    }

    /**
     * Срез реакции или null для игнора.
     *
     * @param GameRecord $game Игра.
     * @param array<string, mixed> $choice Защита.
     *
     * @return CharacterRuleSlice|null Срез.
     */
    private function sliceOf(GameRecord $game, array $choice): ?CharacterRuleSlice
    {
        if ($choice['reaction'] === 'ignore') {
            return null;
        }

        return $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
    }

    /**
     * Объявление трат реакции.
     *
     * @param array{type: string, id: int} $target Цель.
     * @param CharacterRuleSlice $slice Срез.
     * @param string $reactionRuleCode Карточка реакции.
     * @param array<string, mixed> $targetChoices Choices цели.
     * @param array<int, ResourceSpend> $spends Траты.
     * @param array<string, mixed> $pending Карта.
     *
     * @return array{0: array<int, ResourceSpend>, 1: array<string, mixed>} Траты и карта.
     */
    private function declare(
        array $target,
        CharacterRuleSlice $slice,
        string $reactionRuleCode,
        array $targetChoices,
        array $spends,
        array $pending,
    ): array {
        $declared = (new GamePendingActionCosts())->declare(
            $slice,
            $targetChoices,
            $reactionRuleCode,
            $target['type'],
            $target['id'],
            $pending,
            $this->spend->getSpendAmounts($spends),
            false,
        );

        return [$this->spend->getSpendRows($declared['amounts']), $declared['pending']];
    }

    /**
     * Траты поворота.
     *
     * @param GameRecord $game Игра.
     * @param CharacterRuleSlice|null $slice Срез.
     * @param array<string, mixed> $targetChoices Choices цели.
     * @param array<string, mixed> $sheet Лист цели.
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return array<int, ResourceSpend> Траты.
     *
     * @throws GameInvalidException Если поворот или его карточка битые.
     */
    private function turnSpends(
        GameRecord $game, ?CharacterRuleSlice $slice, array $targetChoices, array $sheet, array $spends,
    ): array {
        if ($slice === null) {
            throw new GameInvalidException('Game wide strike turn is invalid');
        }

        $turnCode = $this->rules->cards->reactionRuleCode(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            'turn',
        );
        if ($turnCode === null) {
            throw new GameInvalidException('Game wide strike turn action is missing');
        }

        return array_merge($spends, $this->rules->spends->resolveReactionResourceSpends(
            $game->getSpaceId(), $game->getRulesRevision(), 'turn', null, '', $targetChoices, $sheet,
        ));
    }

    /**
     * Жетон концентрации защитника.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $choice Защита.
     * @param array<string, mixed> $sheet Лист цели.
     * @param string $hitCode Карточка проверки попадания.
     *
     * @return array{spend: ResourceSpend, advantage: RollAdvantage, cycle: array<string, mixed>}|null
     */
    private function tokenOf(
        GameRecord $game,
        array $target,
        array $choice,
        array $sheet,
        string $hitCode,
    ): ?array {
        if ($choice['reaction'] === 'ignore') {
            return null;
        }

        return $this->spend->defenderToken(
            $game,
            $choice,
            $sheet,
            $hitCode,
            $target['type'] . ':' . $target['id'],
            $this->cycleAfter,
        );
    }

    /**
     * Хватает ли ресурсов на траты.
     *
     * @param GameRecord $game Игра.
     * @param array{type: string, id: int} $target Цель.
     * @param array<string, mixed> $sheet Лист цели.
     * @param array<int, ResourceSpend> $spends Траты.
     *
     * @return bool true, если ресурсов не хватает.
     */
    private function insufficient(GameRecord $game, array $target, array $sheet, array $spends): bool
    {
        return $spends !== [] && !$this->rules->spends->hasSufficientResources(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $sheet,
            $spends,
        );
    }
}
