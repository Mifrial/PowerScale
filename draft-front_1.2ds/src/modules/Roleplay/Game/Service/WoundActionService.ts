import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';
import { ACTION_POINTS_CODE } from '@/modules/Roleplay/Game/Constant/Combat/ACTION_POINTS_CODE';
import { WOUND_ACTION_OD } from '@/modules/Roleplay/Game/Constant/Wound/WOUND_ACTION_OD';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { combatCardModelService } from '@/modules/Roleplay/Game/Service/Instance/combatCardModelService';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';
import { woundActionLaunchService } from '@/modules/Roleplay/Game/Service/Instance/woundActionLaunchService';

/**
 * Боевые действия по ране: списание ОД исполнителя, зажим и перевязка цели.
 */
export class WoundActionService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  actionPointsOf(version: CharacterVersion, rules: Rule[]): number {
    return combatCardModelService.combatActionPoints(version, rules)?.current ?? 0;
  }

  spendCost(current: DimensionalNumberValue, cost: number): DimensionalNumberValue {
    return attackDamageService.spendActionPoints(current, cost);
  }

  async spendActionPoints(
    gameId: number,
    actorKey: CombatEntityKey,
    version: CharacterVersion,
    rules: Rule[],
    cost: number,
  ): Promise<GameCombatOverlay> {
    const ap = combatCardModelService.combatActionPoints(version, rules);
    if (!ap || ap.current < cost) throw new Error('Недостаточно ОД');
    const resource = version.resources.find((item) => item.ruleCode === ACTION_POINTS_CODE);
    if (!resource) throw new Error('Нет ресурса ОД');

    return this.resolveGameApi().setCombatResource(
      gameId,
      actorKey,
      ACTION_POINTS_CODE,
      this.spendCost(resource.current, cost),
    );
  }

  squeezeCost(count: number): number {
    return WOUND_ACTION_OD.squeeze * Math.max(0, count);
  }

  canPay(version: CharacterVersion, rules: Rule[], cost: number): boolean {
    return this.actionPointsOf(version, rules) >= cost;
  }

  async applyCommittedWound(
    gameId: number,
    actorKey: CombatEntityKey,
    actorVersion: CharacterVersion,
    session: CommittedActionSession,
  ): Promise<void> {
    const target = session.targetKey;
    if (!target || !woundActionLaunchService.isWoundAction(session.actionRuleCode)) return;
    const overlays = await this.resolveGameApi().getCombatOverlays(gameId);
    const overlay = overlays.find((item) => item.entityKey === target);
    if (!overlay) return;
    if (woundActionLaunchService.isBandage(session.actionRuleCode)) {
      const index = session.stateIndices[0];
      const state = index != null ? overlay.states[index] : undefined;
      if (state == null) return;
      await this.resolveGameApi().replaceCombatState(
        gameId,
        target,
        index,
        woundInstanceService.applyBandage(state, woundInstanceService.medicHasAid(actorVersion)),
      );
      if (!overlay.woundBandagedOnce) {
        await this.resolveGameApi().setCombatWoundBandagedOnce(gameId, target, true);
      }

      return;
    }
    for (const index of session.stateIndices) {
      const latest = (await this.resolveGameApi().getCombatOverlays(gameId)).find((item) => item.entityKey === target);
      const state = latest?.states[index];
      if (!state) continue;
      await this.resolveGameApi().replaceCombatState(
        gameId,
        target,
        index,
        woundInstanceService.applySqueeze(state, actorKey),
      );
    }
  }
}
