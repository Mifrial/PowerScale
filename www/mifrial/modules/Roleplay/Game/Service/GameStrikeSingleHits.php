<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Game\Service;

use Mifrial\Roleplay\Game\Dto\GameStrikeSingleRun;
use Mifrial\Roleplay\Rule\Dto\Spec\Ability\PushSpec;
use Mifrial\Roleplay\Rule\Value\DimensionalNumber;

/**
 * Попадание, урон и эффекты одиночного удара.
 */
final class GameStrikeSingleHits
{
    /**
     * Создаёт расчёт исхода.
     *
     * @param GameStrikeRules $rules Правила.
     * @param GameStrikeSheets $sheets Листы.
     * @param GameStrikeResolution $resolution Расчёт.
     * @param GameStrikeAmounts $amounts Пары и нули.
     * @param GamePushRules $push Толчок.
     * @param GameStrikeSingleUnstable $unstable Неустойчивость.
     *
     * @return void
     */
    public function __construct(
        private readonly GameStrikeRules $rules,
        private readonly GameStrikeSheets $sheets,
        private readonly GameStrikeResolution $resolution,
        private readonly GameStrikeAmounts $amounts,
        private readonly GamePushRules $push,
        private readonly GameStrikeSingleUnstable $unstable,
    ) {
    }

    /**
     * Считает попадание, урон и эффекты.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    public function apply(GameStrikeSingleRun $run): void
    {
        $this->hit($run);
        $this->injury($run);
        $this->push($run);
        $consequence = $run->prepared['consequence'] ?? null;
        if (is_array($consequence)) {
            $run->result = array_merge($run->result, $consequence);
        }
    }

    /**
     * Попадание и его масштаб.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function hit(GameStrikeSingleRun $run): void
    {
        $autoFail = $run->maneuverFail || $this->rules->hitCheck->isHitAutoFail($run->attackerRoll['roll']);
        $pushRating = $this->pushRating($run, $autoFail);
        $success = $this->success($run, $autoFail, $pushRating);
        $run->autoFail = $autoFail;
        $run->pushRating = $pushRating;
        $run->success = $success;
        $this->bonus($run);
        $this->scales($run);
        $this->result($run);
    }

    /**
     * Рейтинг толчка.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param bool $autoFail Автопровал.
     *
     * @return int|null Рейтинг.
     */
    private function pushRating(GameStrikeSingleRun $run, bool $autoFail): ?int
    {
        if (!$run->isPush) {
            return null;
        }

        if ($autoFail) {
            return 0;
        }

        return is_int($run->attackerRoll['rating'] ?? null) ? $run->attackerRoll['rating'] : 0;
    }

    /**
     * Успех попадания.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param bool $autoFail Автопровал.
     * @param int|null $pushRating Рейтинг толчка.
     *
     * @return int Успех.
     */
    private function success(GameStrikeSingleRun $run, bool $autoFail, ?int $pushRating): int
    {
        if ($run->isPush) {
            return $pushRating ?? 0;
        }

        if ($autoFail) {
            return 0;
        }

        $ranged = $run->profileType === 'shoot' || $run->profileType === 'throw';
        $success = $ranged
            ? $this->resolution->successOfRanged($run->attackerRoll)
            : $this->resolution->successOf($run->attackerRoll, $run->defenderRoll, $run->choice['reaction']);

        return (new GameLastStrikeSnapshot())->boostSuccess(
            $run->slice,
            $run->actionRuleCode,
            $success,
            $run->previousSr,
        );
    }

    /**
     * Бонусные успехи карточки.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function bonus(GameStrikeSingleRun $run): void
    {
        $extra = $run->strikePackage['extraSuccesses'] ?? null;
        if ($run->isPush || $run->success <= 0 || !is_int($extra) || $extra <= 0) {
            return;
        }

        $rating = $this->rules->outcome->processBonusRating($run->attackerRoll, $extra);
        $run->success += $rating;
        $current = is_int($run->attackerRoll['rating'] ?? null) ? $run->attackerRoll['rating'] : 0;
        $run->attackerRoll['rating'] = $current + $rating;
        $run->attackerRoll['process_bonus'] = ['successes' => $extra, 'rating' => $rating];
    }

    /**
     * Потолок успехов и готовый урон.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function scales(GameStrikeSingleRun $run): void
    {
        $run->srCap = $this->rules->outcome->successRatingCap(
            $run->input->game->getSpaceId(),
            $run->input->game->getRulesRevision(),
            $run->damageTypeCode,
        );
        $run->pushCanceled = $run->isPush
            && ($run->autoFail || (($run->pushRating ?? 0) <= 0 && $run->choice['reaction'] !== 'block'));
        $run->damageSr = $this->damageSr($run);
        $run->damage = $run->autoFail
            ? $this->amounts->zero()
            : $this->resolution->damage->damageOf($run->input->game, $run->open, $run->isPush ? $run->damageSr : $run->success, 1, $run->metrics['drop'], $run->isPush ? $this->push->damageModifier($this->pushSpec($run)) : null);
    }

    /**
     * Успехи урона с потолком.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return int Успехи.
     */
    private function damageSr(GameStrikeSingleRun $run): int
    {
        if (!$run->isPush) {
            return $this->amounts->appliedSr($run->success, $run->srCap);
        }

        if ($run->pushCanceled) {
            return 0;
        }

        $rating = $run->pushRating ?? 0;

        return $rating > 0 ? $this->amounts->appliedSr($rating, $run->srCap) : 1;
    }

    /**
     * Базовый итог, сопротивление и поглощение.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function result(GameStrikeSingleRun $run): void
    {
        $run->result = $this->closeResult(
            $run,
            $run->isPush ? $run->damageSr : $run->success,
            $run->damage ?? $this->amounts->zero(),
        );
        $run->result['appliedSr'] = $run->damageSr;
        $run->result['srCap'] = $run->srCap;
        $this->harm($run);
    }

    /**
     * Стойкость, проникновение, сопротивление и поглощение.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function harm(GameStrikeSingleRun $run): void
    {
        $this->durability($run);
        $this->penetration($run);
        $this->resistance($run);
        $this->soak($run);
        $this->harmResult($run);
    }

    /**
     * Проникновение урона.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function penetration(GameStrikeSingleRun $run): void
    {
        $run->penetration = $run->damageSr === 0 ? $this->amounts->zero() : $this->resolution->defense->penetrationOf(
            $run->input->game,
            $run->open,
            $run->damageSr,
            1,
            $run->metrics['drop'],
        );
    }

    /**
     * Сопротивление урону.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function resistance(GameStrikeSingleRun $run): void
    {
        $run->resistance = $run->damageSr === 0 ? $this->amounts->zero() : $this->resolution->defense->resistanceOf(
            $run->input->game,
            $run->targetOpen,
            $this->resolution->defense->resistanceChoice($run->choice, $run->result['defenderRoll'] ?? null),
            $run->damageSr,
            $run->penetration,
            $run->durabilityShave,
        );
    }

    /**
     * Поглощение урона.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function soak(GameStrikeSingleRun $run): void
    {
        $run->soak = $this->resolution->damage->soakOf(
            $run->input->game,
            $run->targetOpen,
            $run->choice['reaction'],
            $run->damageSr,
            $run->pending,
        );
    }

    /**
     * Стойкость карточки действия.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function durability(GameStrikeSingleRun $run): void
    {
        $game = $run->input->game;
        $run->durabilityShave = $run->damageSr === 0 ? 0 : $this->rules->outcome->durabilityShave(
            $game->getSpaceId(),
            $game->getRulesRevision(),
            $run->actionRuleCode,
            is_string($run->open['item_rule_code'] ?? null) ? $run->open['item_rule_code'] : '',
            is_string($run->open['profile_type'] ?? null) ? $run->open['profile_type'] : '',
            is_int($run->open['profile_index'] ?? null) ? $run->open['profile_index'] : 0,
            $run->attackerRoll,
            $run->damageSr,
        );
    }

    /**
     * Переносит сопротивление и поглощение в итог.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function harmResult(GameStrikeSingleRun $run): void
    {
        $run->result['resistance'] = $this->amounts->view($run->resistance ?? $this->amounts->zero());
        if ($run->soak !== null) {
            $run->result['S'] = $this->amounts->view($run->soak);
        }

        if ($run->durabilityShave > 0) {
            $run->result['durabilityShave'] = $run->durabilityShave;
        }
    }

    /**
     * Пакеты урона и подготовленные операции листа.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function injury(GameStrikeSingleRun $run): void
    {
        [$packetDamages, $packetInjuries] = $this->packets($run);
        $run->injury = $packetInjuries[0];
        $run->packetDamages = $packetDamages;
        $run->packetInjuries = $packetInjuries;
        $run->result['damage'] = $this->amounts->view($packetDamages[0]);
        $run->result['injury'] = $this->amounts->view($run->injury);
        $run->result['packetDamages'] = array_map($this->amounts->view(...), $packetDamages);
        $run->result['packetInjuries'] = array_map($this->amounts->view(...), $packetInjuries);
        $run->prepared = ($run->isPush ? $run->damageSr > 0 : !$run->autoFail)
            ? $this->preparation($run, $packetInjuries)
            : null;
    }

    /**
     * Урон и травмы по пакетам.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array{0: list<DimensionalNumber>, 1: list<DimensionalNumber>} Пакеты.
     */
    private function packets(GameStrikeSingleRun $run): array
    {
        $packetDamages = [];
        $packetInjuries = [];
        for ($packet = 1; $packet <= $run->packets; ++$packet) {
            $packetDamage = $run->autoFail ? $this->amounts->zero() : $this->resolution->damage->damageOf($run->input->game, $run->open, $run->isPush ? $run->damageSr : $run->success, $packet, $run->metrics['drop'], $run->isPush ? $this->push->damageModifier($this->pushSpec($run)) : null);
            $packetDamages[] = $packetDamage;
            $packetInjuries[] = $this->amounts->injury(
                $run->damageSr === 0 ? $this->amounts->zero() : $packetDamage,
                $run->resistance ?? $this->amounts->zero(),
                $run->damageSr,
                $run->soak,
            );
        }

        return [$packetDamages, $packetInjuries];
    }

    /**
     * Подготовленные операции листа по травмам.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param list<DimensionalNumber> $injuries Травмы.
     *
     * @return array<string, mixed> Операции.
     */
    private function preparation(GameStrikeSingleRun $run, array $injuries): array
    {
        return $this->resolution->damage->prepareSheet(
            $run->input->game,
            $run->targetOpen,
            $this->amounts->sum($injuries),
            $run->damageSr,
        );
    }

    /**
     * Устойчивость толчка и его эффекты.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return void
     */
    private function push(GameStrikeSingleRun $run): void
    {
        if (!$run->isPush) {
            return;
        }

        $state = $this->pushState($run);
        if ($state['unstableCode'] !== null && is_array($run->prepared)) {
            $this->unstable->apply($run, $state['unstableCode'], $state['unstable']);
        }

        $knockback = $this->push->knockback($state['unstable']);
        $run->result['push'] = [
            'rating' => $run->pushRating,
            'stability' => $this->amounts->view($state['stability']),
            'unstable' => $state['unstable'],
            'unstableCode' => $state['unstableCode'],
            'knockback' => $knockback instanceof DimensionalNumber ? $this->amounts->view($knockback) : null,
            'canceled' => $run->pushCanceled,
        ];
    }

    /**
     * Спецификация толчка строки.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return PushSpec Спецификация.
     *
     * @throws \LogicException Если спецификации нет.
     */
    private function pushSpec(GameStrikeSingleRun $run): PushSpec
    {
        return $run->pushSpec ?? throw new \LogicException('Game push spec is missing');
    }

    /**
     * Устойчивость и неустойчивость толчка.
     *
     * @param GameStrikeSingleRun $run Прогон.
     *
     * @return array{stability: DimensionalNumber, unstable: int, unstableCode: string|null} Состояние.
     */
    private function pushState(GameStrikeSingleRun $run): array
    {
        $game = $run->input->game;
        $attackerSheet = $this->sheets->getTargetSheet(
            $game,
            ['type' => $run->attackerKind, 'id' => $run->attackerId],
        );
        $defenderSheet = $this->sheets->getTargetSheet($game, $run->target);
        $pushSpec = $this->pushSpec($run);
        $stability = $this->push->stability(
            $pushSpec,
            $run->choice['reaction'],
            $run->pushRating ?? 0,
            $this->rules->data->characteristicOf($defenderSheet, 'strength'),
            $this->push->stabilityFloor($defenderSheet),
        );
        $unstable = $run->pushCanceled ? 0 : $this->push->unstable(
            $pushSpec,
            $this->rules->data->characteristicOf($attackerSheet, 'strength'),
            $stability,
            $run->damageTypeCode,
        );

        return [
            'stability' => $stability,
            'unstable' => $unstable,
            'unstableCode' => $unstable > 0 ? $this->push->unstableCode($game) : null,
        ];
    }

    /**
     * Базовый итог одиночного закрытия.
     *
     * @param GameStrikeSingleRun $run Прогон.
     * @param int $success Успех.
     * @param DimensionalNumber $damage Урон.
     *
     * @return array<string, mixed> Итог.
     */
    private function closeResult(GameStrikeSingleRun $run, int $success, DimensionalNumber $damage): array
    {
        $effectiveDamage = $success === 0 ? $this->amounts->zero() : $damage;
        $result = [
            'battleId' => $run->input->battleId,
            'strikeId' => $run->strikeId,
            'version' => $run->input->expectedBattleVersion + 1,
            'attackerRoll' => $run->attackerRoll,
            'success' => $success,
            'damage' => $this->amounts->view($effectiveDamage),
            'resistance' => $this->amounts->view($this->amounts->zero()),
            'sheetVersion' => null,
        ];
        if ($run->defenderRoll !== null) {
            $result['defenderRoll'] = $run->defenderRoll;
        }

        return $result;
    }
}
