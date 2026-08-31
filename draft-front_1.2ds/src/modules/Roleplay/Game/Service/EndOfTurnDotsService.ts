import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import { characterOverviewService } from '@/modules/Roleplay/Character/init';
import { EXHAUSTION_STATE_CODE, STUNNED_STATE_CODE, WOUND_STATE_CODE } from '@/modules/Roleplay/Rule/init';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';

import { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';

import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';

import { dotTickMathService } from '@/modules/Roleplay/Game/Service/Instance/dotTickMathService';

import { formatDotTickMessage, buildDotTickAttachment } from '@/modules/Roleplay/Game/Utils/dotTickMessage';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';

import { damageTypeHooksService } from '@/modules/Roleplay/Game/Service/Instance/damageTypeHooksService';

import { damageTypeSpecService } from '@/modules/Roleplay/Rule/init';
import { ACCUMULATED_DAMAGE_STATE_CODE } from '@/modules/Roleplay/Rule/init';

import type { ApplyEndOfTurnDotsArgs } from '@/modules/Roleplay/Game/Dto/ApplyEndOfTurnDotsArgs';
export class EndOfTurnDotsService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  private async writeAccumulatedDamage(
    args: ApplyEndOfTurnDotsArgs,
    version: CharacterVersion,
    amount: number,
  ): Promise<GameCombatOverlay | null> {
    const rule = args.rules.find((item) => item.code === ACCUMULATED_DAMAGE_STATE_CODE && item.type === 'state');
    if (!rule) return null;
    const index = version.states.findIndex((state) => state.stateRuleId === rule.id);
    if (amount <= 0) {
      return index >= 0 ? this.resolveGameApi().removeCombatState(args.gameId, args.targetKey, index) : null;
    }

    const state = { stateRuleId: rule.id, dimensionalValue: { base: amount, size: 0 } };

    return index >= 0
      ? this.resolveGameApi().replaceCombatState(args.gameId, args.targetKey, index, state)
      : this.resolveGameApi().addCombatState(args.gameId, args.targetKey, state);
  }

  private async addNumericState(
    args: ApplyEndOfTurnDotsArgs,
    version: CharacterVersion,
    code: string,
    amount: number,
  ): Promise<GameCombatOverlay | null> {
    if (amount <= 0) return null;
    const rule = args.rules.find((item) => item.code === code && item.type === 'state');
    if (!rule) return null;
    const independent = (rule.spec as StateSpec | undefined)?.aggregation === 'independent';
    const index = version.states.findIndex((state) => state.stateRuleId === rule.id);
    if (!independent && index >= 0) {
      return this.resolveGameApi().setCombatStateValue(
        args.gameId,
        args.targetKey,
        index,
        (version.states[index]?.value ?? 0) + amount,
      );
    }

    return this.resolveGameApi().addCombatState(args.gameId, args.targetKey, { stateRuleId: rule.id, value: amount });
  }

  async applyEndOfTurnDots(args: ApplyEndOfTurnDotsArgs): Promise<GameCombatOverlay | null> {
    let version = args.version;
    let overlay: GameCombatOverlay | null = null;
    const advances = version.states.map((state) => dotTickMathService.advanceDotState(state, args.rules));
    for (let index = advances.length - 1; index >= 0; index -= 1) {
      const step = advances[index];
      if (step.kind === 'skip') continue;
      if (step.kind === 'wait') {
        overlay = await this.resolveGameApi().replaceCombatState(args.gameId, args.targetKey, index, step.next);
      } else if (step.next) {
        overlay = await this.resolveGameApi().replaceCombatState(args.gameId, args.targetKey, index, step.next);
      } else {
        overlay = await this.resolveGameApi().removeCombatState(args.gameId, args.targetKey, index);
      }
      if (overlay) version = combatOverlayService.mergeCombatOverlay(version, overlay);
    }
    const fires = advances.filter((step) => step.kind === 'tick');
    for (const step of fires) {
      if (step.kind !== 'tick') continue;
      const overview = characterOverviewService.build(version, args.rules);
      const typeRule = args.rules.find((rule) => rule.code === step.damageTypeCode && rule.type === 'damage_type');
      const hooks = damageTypeHooksService.resolveDamageTypeHooks(step.damageTypeCode, args.rules, args.mechanics);
      const result = attackDamageService.applyAttackDamage({
        weaponDamage: step.strength,
        sr: 1,
        damageTypeCode: step.damageTypeCode,
        defense: overview.defense ?? null,
        endurance: overview.characteristics.length
          ? attackDamageService.enduranceValueOf(overview, args.rules)
          : { base: Math.max(1, args.endurance), size: 0 },
        accumulatedDamage: attackDamageService.accumulatedDamageOf(version.states, args.rules),
        hooks,
        defenseIgnored: damageTypeSpecService.asDamageTypeSpec(typeRule)?.defense_ignored === true,
      });
      if (args.chatId !== null) {
        const sent = await args.sendMessage(
          formatDotTickMessage(
            args.targetName,
            args.targetKey,
            step.damageTypeCode,
            result.hpDamage,
            result.exhaustion,
            args.rules,
          ),
          [
            buildDotTickAttachment(
              step.label,
              step.strength,
              step.damageTypeCode,
              result,
              args.rules,
              damageTypeSpecService.asDamageTypeSpec(typeRule)?.defense_ignored === true,
            ),
          ],
          args.chatId,
          args.speaker,
        );
        if (!sent) throw new Error('Не удалось отправить сообщение о тике');
      }
      const damageOverlay = await this.writeAccumulatedDamage(args, version, result.remainingHpDamage);
      if (damageOverlay) {
        overlay = damageOverlay;
        version = combatOverlayService.mergeCombatOverlay(version, damageOverlay);
      }
      const exh = await this.addNumericState(args, version, EXHAUSTION_STATE_CODE, result.exhaustion);
      if (exh) {
        overlay = exh;
        version = combatOverlayService.mergeCombatOverlay(version, exh);
      }
      const wound = await this.addNumericState(
        args,
        version,
        WOUND_STATE_CODE,
        (result.wound ?? 0) + (result.cuttingWound ?? 0),
      );
      if (wound) {
        overlay = wound;
        version = combatOverlayService.mergeCombatOverlay(version, wound);
      }
      const stun = await this.addNumericState(args, version, STUNNED_STATE_CODE, result.stun ?? 0);
      if (stun) {
        overlay = stun;
        version = combatOverlayService.mergeCombatOverlay(version, stun);
      }
      if (result.exhaustion > 0) {
        const checked = await exhaustionCheckService.applyExhaustionCheck({
          version,
          rng: args.rng,
          rules: args.rules,
          mechanics: args.mechanics,
          gameId: args.gameId,
          targetKey: args.targetKey,
          targetName: args.targetName,
          chatId: args.chatId,
          speaker: args.speaker,
          change: 'increase',
          sendMessage: args.sendMessage,
        });
        if (checked.overlay) {
          overlay = checked.overlay;
          version = combatOverlayService.mergeCombatOverlay(version, checked.overlay);
        }
      }
      if (
        injuryCheckService.shouldLaunchInjuryFromAttack({
          hpDamage: result.hpDamage,
          cuttingWound: result.cuttingWound,
          woundFromHit: result.wound,
        })
      ) {
        const applied = await injuryCheckService.applyInjuryCheck({
          input: injuryCheckService.injuryInputFromAttack({
            hpDamage: result.hpDamage,
            cuttingWound: result.cuttingWound,
            woundFromHit: result.wound,
            overlayExhaustion: injuryCheckService.overlayStateTotal(version, args.rules, EXHAUSTION_STATE_CODE),
            endurance: Math.max(1, args.endurance),
            remainingSr: result.remainingSr,
            damageTypeCode: step.damageTypeCode,
            actorKey: args.targetKey,
          }),
          rng: args.rng,
          rules: args.rules,
          mechanics: args.mechanics,
          gameId: args.gameId,
          targetKey: args.targetKey,
          targetName: args.targetName,
          chatId: args.chatId,
          speaker: args.speaker,
          skipIfNoRoll: true,
          targetVersion: version,
          sendMessage: args.sendMessage,
        });
        if (applied.overlay) {
          overlay = applied.overlay;
          version = combatOverlayService.mergeCombatOverlay(version, applied.overlay);
        }
      }
    }

    return overlay;
  }
}
