<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Game\Dto\GameStrikeTarget;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;

/**
 * Подготовка защитника и его трат.
 */
final class GameStrikeSingleDefender
{
    /**
     * Создаёт подготовку защитника.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeSpend $spend Траты.
     * @param GameStrikeTargetChoices $choices Choices целей.
     * @param GameStrikeDeclaredSpend $declaredSpend Заявленные траты.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeSpend $spend,
        private readonly GameStrikeTargetChoices $choices,
        private readonly GameStrikeDeclaredSpend $declaredSpend,
    ) {
    }

    /**
     * Проверяет лист, считает снимок атаки и траты защиты.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     *
     * @throws GameInvalidException Если лист или реакция битые.
     */
    public function prepare(GameStrikeSingleRun $run): void
    {
        $game = $run->input->game;
        $this->sheets->conflicts->assertDefenderSheet(
            $game,
            $run->targetOpen,
            $run->input->expectedSheetVersion,
            $run->scope->actorUserId,
            $run->scope->viewAll,
        );
        $run->slice = $this->rules->data->ruleSlice($game->getSpaceId(), $game->getRulesRevision());
        $run->row = $this->declaredSpend->spend($game, $run->slice, [$run->row])[0];
        $this->profile($run);
        $this->attack($run);
        $this->pending($run);
        $this->reaction($run);
        $this->spends($run);
    }

    /**
     * Снимок ожидания боя.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function pending(GameStrikeSingleRun $run): void
    {
        $run->pending = $run->battle->getPendingActionEffects();
        $run->defenderPending = $run->pending;
    }

    /**
     * Профиль и контекст атаки из строки.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function profile(GameStrikeSingleRun $run): void
    {
        $open = $run->open;
        $run->profileType = is_string($open['profile_type'] ?? null) ? $open['profile_type'] : '';
        $run->profileIndex = is_int($open['profile_index'] ?? null) ? $open['profile_index'] : 0;
        $run->itemInventoryId = is_int($open['item_inventory_id'] ?? null) ? $open['item_inventory_id'] : 0;
        $run->itemRuleCode = is_string($open['item_rule_code'] ?? null) ? $open['item_rule_code'] : '';
        $run->attackerChoices = $this->choices->attackerChoices($run->input->game, $open);
        $run->attackContext = $this->rules->outcome->attackFilterContext(
            $run->input->game->getSpaceId(),
            $run->input->game->getRulesRevision(),
            $run->itemRuleCode,
            $run->profileType,
            $run->profileIndex,
            $run->attackerChoices,
            $run->itemInventoryId,
        );
    }

    /**
     * Снимок атаки и её метрики.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function attack(GameStrikeSingleRun $run): void
    {
        $game = $run->input->game;
        $npc = $this->npc($run);
        $run->modifier = $this->rules->modifiers->actionCharacteristicModifier(
            $run->slice,
            $run->actionRuleCode,
            $run->profileType,
            1,
            $run->attackContext,
            $this->abilityLevels($run),
        );
        $run->metrics = $this->rules->distance->metrics(
            $this->rules->damage->attack($this->strikeTarget($run, $npc)),
            $run->conditions,
            $run->actionRuleCode,
        );
    }

    /**
     * Снимок атаки по цели.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param array{sheet: mixed, choices: mixed, version: mixed} $npc Снимок NPC.
     *
     * @return GameStrikeTarget Снимок.
     */
    private function strikeTarget(GameStrikeSingleRun $run, array $npc): GameStrikeTarget
    {
        return GameStrikeTarget::of(
            $run->input->game->getSpaceId(),
            $run->input->game->getRulesRevision(),
            $run->itemRuleCode,
            $run->profileType,
            $run->profileIndex,
            $run->itemInventoryId,
            $run->attackerKind,
            $run->attackerId,
            $npc['sheet'],
            $npc['choices'],
            $npc['version'],
            $run->modifier,
            0,
            0,
        );
    }

    /**
     * Снимок NPC атакующего или пустые значения.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array{sheet: mixed, choices: mixed, version: mixed} Снимок.
     */
    private function npc(GameStrikeSingleRun $run): array
    {
        if ($run->attackerKind !== 'npc') {
            return ['sheet' => null, 'choices' => null, 'version' => null];
        }

        $gameId = $run->input->game->getId();

        return [
            'sheet' => $this->sheets->getNpcSheet($gameId, $run->attackerId),
            'choices' => $this->sheets->getNpcChoices($gameId, $run->attackerId),
            'version' => $this->sheets->getNpcVersion($gameId, $run->attackerId),
        ];
    }

    /**
     * Коды реакций и choices защитника.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     *
     * @throws GameInvalidException Если карточки поворота нет.
     */
    private function reaction(GameStrikeSingleRun $run): void
    {
        $game = $run->input->game;
        $reaction = $run->choice['reaction'];
        $run->reactionRuleCode = $reaction === 'ignore'
            ? null
            : $this->rules->cards->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), $reaction);
        $run->turnRuleCode = $run->turn
            ? $this->rules->cards->reactionRuleCode($game->getSpaceId(), $game->getRulesRevision(), 'turn')
            : null;
        if ($run->turn && $run->turnRuleCode === null) {
            throw new GameInvalidException('Game strike turn action is missing');
        }

        if ($run->reactionRuleCode !== null || $reaction === 'block' || $run->turn) {
            $run->defenderChoices = $this->choices->targetChoices($game, $run->target);
        }
    }

    /**
     * Траты реакции, поворота и жетона.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function spends(GameStrikeSingleRun $run): void
    {
        $game = $run->input->game;
        $target = $run->target;
        $run->spends = $run->choice['reaction'] === 'ignore'
            ? []
            : $this->spend->getReactionSpends(
                $game,
                $run->choice,
                $run->defenderChoices,
                $this->sheets->getTargetSheet($game, $target),
            );
        $this->declareReaction($run);
        $this->turnSpend($run);
        $this->token($run);
        if ($run->resumeState !== null && $run->resumeState['prepaid']) {
            $run->spends = [];
            $run->defenderToken = null;
        }
    }

    /**
     * Жетон концентрации защитника.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function token(GameStrikeSingleRun $run): void
    {
        $game = $run->input->game;
        $target = $run->target;
        $run->defenderToken = $run->choice['reaction'] === 'ignore'
            ? null
            : $this->spend->defenderToken(
                $game,
                $run->choice,
                $this->sheets->getTargetSheet($game, $target),
                $run->hitCode,
                $target['type'] . ':' . $target['id'],
                $run->battle->getConcentrationCycle(),
            );
        if ($run->defenderToken !== null) {
            $run->spends[] = $run->defenderToken['spend'];
        }
    }

    /**
     * Объявляет стоимость реакции защитника.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function declareReaction(GameStrikeSingleRun $run): void
    {
        if ($run->reactionRuleCode === null) {
            return;
        }

        $target = $run->target;
        $declared = (new GamePendingActionCosts())->declare(
            $run->slice,
            $run->defenderChoices,
            $run->reactionRuleCode,
            $target['type'],
            $target['id'],
            $run->defenderPending,
            $this->spend->getSpendAmounts($run->spends),
            false,
        );
        $run->spends = $this->spend->getSpendRows($declared['amounts']);
        $run->defenderPending = $declared['pending'];
        $key = $target['type'] . ':' . $target['id'];
        if (array_key_exists($key, $run->defenderPending)) {
            $run->pending[$key] = $run->defenderPending[$key];
        } else {
            unset($run->pending[$key]);
        }
    }

    /**
     * Добавляет траты поворота.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function turnSpend(GameStrikeSingleRun $run): void
    {
        if ($run->turnRuleCode === null) {
            return;
        }

        $game = $run->input->game;
        $spends = $this->rules->spends->resolveReactionResourceSpends(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            'turn',
            null,
            '',
            $run->defenderChoices,
            $this->sheets->getTargetSheet($game, $run->target),
        );
        $run->spends = array_merge($run->spends, $spends);
    }

    /**
     * Уровни способностей атакующего.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, int> Уровни.
     */
    private function abilityLevels(GameStrikeSingleRun $run): array
    {
        return $this->sheets->abilityLevels($run->input->game, $run->attackerKind, $run->attackerId);
    }
}
