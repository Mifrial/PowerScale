<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Game\Exception\GameInvalidException;
use Mifrial\Roleplay\Mechanic\Dto\RollAdvantage;

/**
 * Броски защиты и проверка ресурсов.
 */
final class GameStrikeSingleRound
{
    /**
     * Создаёт раунд защиты.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeResolution $resolution Расчёт.
     * @param GameStrikeAmounts $amounts Пары и нули.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeResolution $resolution,
        private readonly GameStrikeAmounts $amounts,
    ) {
    }

    /**
     * Бросает защиту и проверяет ресурсы реакции.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed>|null Итог автоматического отказа.
     *
     * @throws GameInvalidException Если защитник без сознания.
     */
    public function resolve(GameStrikeSingleRun $run): ?array
    {
        $this->assertConscious($run);
        $this->defend($run);
        $run->damageTypeCode = $this->damageType($run);
        if (!$this->hasResources($run)) {
            return $this->ignoredResult($run);
        }

        return null;
    }

    /**
     * Защитник в сознании.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     *
     * @throws GameInvalidException Если защитник без сознания.
     */
    private function assertConscious(GameStrikeSingleRun $run): void
    {
        $game = $run->input->game;
        if ($run->choice['reaction'] === 'ignore') {
            return;
        }

        if ($this->rules->hitCheck->isUnconscious(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $this->sheets->getTargetSheet($game, $run->target),
        )) {
            throw new GameInvalidException('Game strike defender is unconscious');
        }
    }

    /**
     * Преимущества и броски защиты.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function defend(GameStrikeSingleRun $run): void
    {
        $defenderAdvantages = $this->defenderAdvantages($run);
        if (!$run->defenseRolled && in_array($run->choice['reaction'], ['dodge', 'block'], true)) {
            $markDefense = (new GameBattleMarks())->forDefense(
                $run->battle->getMarks(),
                $run->target,
                ['type' => $run->attackerKind, 'id' => $run->attackerId],
            );
            $defenderAdvantages = array_merge($defenderAdvantages, $markDefense['advantages']);
            $run->markKeys = array_merge($run->markKeys, $markDefense['keys']);
        }

        $this->dodge($run, $defenderAdvantages);
        $this->block($run, $defenderAdvantages);
        $this->ignored($run);
    }

    /**
     * Преимущества защитника.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return list<RollAdvantage> Преимущества.
     */
    private function defenderAdvantages(GameStrikeSingleRun $run): array
    {
        $supports = $run->supports['defender'] ?? null;
        $advantages = is_array($supports) ? $supports : [];
        if ($run->flank !== null) {
            $advantages[] = $run->flank;
        }

        $token = $run->defenderToken['advantage'] ?? null;
        if ($token instanceof RollAdvantage) {
            $advantages[] = $token;
        }

        return $advantages;
    }

    /**
     * Бросок уклонения и пересчёт попадания.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param list<RollAdvantage> $advantages Преимущества защиты.
     *
     * @return void
     */
    private function dodge(GameStrikeSingleRun $run, array $advantages): void
    {
        if ($run->defenseRolled || $run->choice['reaction'] !== 'dodge') {
            return;
        }

        $game = $run->input->game;
        $shotDistance = $run->profileType === 'shoot' ? $run->metrics['distance'] : null;
        $run->defenderRoll = $this->resolution->rolls->dodgeRollOf(
            $game,
            $run->targetOpen,
            $run->hitCode,
            $run->attackerRoll,
            $run->defenderPending,
            $run->reactionRuleCode,
            $run->slice,
            $advantages,
            $shotDistance,
        );
        $run->attackerRoll = $run->profileType === 'shoot' || $run->profileType === 'throw'
            ? $this->resolution->defense->rerateRangedHit(
                $game,
                $run->open,
                $run->conditions,
                $run->attackerRoll,
                $run->defenderRoll,
                'dodge',
            )
            : $this->rules->hitCheck->rerate($run->attackerRoll, $run->defenderRoll);
    }

    /**
     * Бросок блока и пересчёт попадания.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param list<RollAdvantage> $advantages Преимущества защиты.
     *
     * @return void
     */
    private function block(GameStrikeSingleRun $run, array $advantages): void
    {
        if ($run->defenseRolled || $run->choice['reaction'] !== 'block') {
            return;
        }
        $game = $run->input->game;
        $run->defenderRoll = $this->resolution->rolls->blockRollOf(
            $game,
            $run->targetOpen,
            $run->choice,
            $run->hitCode,
            $run->attackerRoll,
            $run->defenderPending,
            $run->reactionRuleCode,
            $run->slice,
            $advantages,
        );
        if ($run->isPush) {
            $run->attackerRoll = $this->rules->hitCheck->rerate($run->attackerRoll, $run->defenderRoll);
        } elseif ($run->profileType === 'shoot' || $run->profileType === 'throw') {
            $run->attackerRoll = $this->resolution->defense->rerateRangedHit(
                $game,
                $run->open,
                $run->conditions,
                $run->attackerRoll,
                $run->defenderRoll,
                'block',
            );
        }
    }

    /**
     * Пересчёт дистанционного попадания при игнорировании.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function ignored(GameStrikeSingleRun $run): void
    {
        if ($run->defenseRolled || $run->choice['reaction'] !== 'ignore') {
            return;
        }

        if ($run->profileType !== 'shoot' && $run->profileType !== 'throw') {
            return;
        }

        $run->attackerRoll = $this->resolution->defense->rerateRangedHit(
            $run->input->game,
            $run->open,
            $run->conditions,
            $run->attackerRoll,
            null,
            'ignore',
        );
    }

    /**
     * Код типа повреждения.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return string|null Код.
     */
    private function damageType(GameStrikeSingleRun $run): ?string
    {
        $game = $run->input->game;
        if ($run->isPush) {
            return $this->rules->outcome->pushDamageTypeCode(
                $game->getSpaceId(),
                $game->getRulesRevision(),
                $run->itemRuleCode,
                $run->profileIndex,
            );
        }

        return $this->rules->outcome->damageTypeCode(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $run->itemRuleCode,
            $run->profileType,
            $run->profileIndex,
        );
    }

    /**
     * Хватает ли ресурсов на объявленную защиту.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return bool Признак.
     */
    private function hasResources(GameStrikeSingleRun $run): bool
    {
        if ($run->choice['reaction'] === 'ignore' || $run->spends === []) {
            return true;
        }

        $game = $run->input->game;

        return $this->rules->spends->hasSufficientResources(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $this->sheets->getTargetSheet($game, $run->target),
            $run->spends,
        );
    }

    /**
     * Формирует result envelope для automatic ignore.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array<string, mixed> Итог.
     */
    private function ignoredResult(GameStrikeSingleRun $run): array
    {
        $zero = $this->amounts->view($this->amounts->zero());

        return [
            'battleId' => $run->input->battleId,
            'strikeId' => $run->strikeId,
            'version' => $run->input->expectedBattleVersion,
            'attackerRoll' => $run->attackerRoll,
            'defenderRoll' => $run->defenderRoll,
            'success' => 0,
            'damage' => $zero,
            'resistance' => $zero,
            'injury' => $zero,
            'sheetVersion' => null,
        ];
    }
}
