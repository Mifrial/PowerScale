import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import { characterOverviewService } from '@/modules/Roleplay/Character/init';
import {
  EXHAUSTION_STATE_CODE,
  STUNNED_STATE_CODE,
  SHOCK_STATE_CODE,
  WOUND_STATE_CODE,
} from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';

import { exhaustionCheckService } from '@/modules/Roleplay/Game/Service/Instance/exhaustionCheckService';

import { injuryCheckService } from '@/modules/Roleplay/Game/Service/Instance/injuryCheckService';

import { dotTickMathService } from '@/modules/Roleplay/Game/Service/Instance/dotTickMathService';

import { formatDotTickMessage, buildDotTickAttachment } from '@/modules/Roleplay/Game/Utils/dotTickMessage';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';

import { damageTypeHooksService } from '@/modules/Roleplay/Game/Service/Instance/damageTypeHooksService';
import { woundInstanceService } from '@/modules/Roleplay/Game/Service/Instance/woundInstanceService';

import { damageTypeSpecService } from '@/modules/Roleplay/Rule/init';
import { ACCUMULATED_DAMAGE_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';

import type { ApplyEndOfTurnDotsArgs } from '@/modules/Roleplay/Game/Dto/ApplyEndOfTurnDotsArgs';
export class EndOfTurnDotsService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  private async writeAccumulatedDamage(
    args: ApplyEndOfTurnDotsArgs,
    version: CharacterVersion,
    amount: number,
  ): Promise<void> {
    const rule = args.rules.find((item) => item.code === ACCUMULATED_DAMAGE_STATE_CODE && item.type === 'state');
    if (!rule) return;
    const index = version.states.findIndex((state) => state.stateRuleCode === rule.code);
    if (amount <= 0) {
      if (index >= 0) await this.resolveGameApi().removeCombatState(args.gameId, args.targetKey, index);

      return;
    }

    const state = { stateRuleCode: rule.code, dimensionalValue: { base: amount, size: 0 } };

    if (index >= 0) {
      await this.resolveGameApi().replaceCombatState(args.gameId, args.targetKey, index, state);
    } else {
      await this.resolveGameApi().addCombatState(args.gameId, args.targetKey, state);
    }
  }

  private async addNumericState(
    args: ApplyEndOfTurnDotsArgs,
    version: CharacterVersion,
    code: string,
    amount: number,
  ): Promise<void> {
    if (amount <= 0) return;
    const rule = args.rules.find((item) => item.code === code && item.type === 'state');
    if (!rule) return;
    if (code === WOUND_STATE_CODE) {
      const added = woundInstanceService.addWound(amount);
      if (!added) return;

      await this.resolveGameApi().addCombatState(args.gameId, args.targetKey, added);

      return;
    }
    const independent = (rule.spec as StateSpec | undefined)?.aggregation === 'independent';
    const index = version.states.findIndex((state) => state.stateRuleCode === rule.code);
    if (!independent && index >= 0) {
      await this.resolveGameApi().setCombatStateValue(
        args.gameId,
        args.targetKey,
        index,
        (version.states[index]?.value ?? 0) + amount,
      );

      return;
    }

    await this.resolveGameApi().addCombatState(args.gameId, args.targetKey, {
      stateRuleCode: rule.code,
      value: amount,
    });
  }

  async applyEndOfTurnDots(args: ApplyEndOfTurnDotsArgs): Promise<GameCombatOverlay | null> {
    const version = args.version;
    let overlay: GameCombatOverlay | null = null;
    const advances = version.states.map((state) => dotTickMathService.advanceDotState(state, args.rules));
    for (let index = advances.length - 1; index >= 0; index -= 1) {
      const step = advances[index];
      if (step.kind === 'skip') continue;
      if (step.kind === 'wait') {
        await this.resolveGameApi().replaceCombatState(args.gameId, args.targetKey, index, step.next);
      } else if (step.next) {
        await this.resolveGameApi().replaceCombatState(args.gameId, args.targetKey, index, step.next);
      } else {
        await this.resolveGameApi().removeCombatState(args.gameId, args.targetKey, index);
      }
    }
    const fires = advances.filter((step) => step.kind === 'tick');
    for (const step of fires) {
      if (step.kind !== 'tick') continue;
      const overview = characterOverviewService.build(version, args.rules);
      const typeRule = args.rules.find((rule) => rule.code === step.damageTypeCode && rule.type === 'damage_type');
      const hooks = damageTypeHooksService.resolveDamageTypeHooks(step.damageTypeCode, args.rules, args.mechanics);
      const typeSpec = damageTypeSpecService.asDamageTypeSpec(typeRule);
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
        defenseIgnored: typeSpec?.defense_ignored === true,
        maxSuccessRating: typeSpec?.max_success_rating ?? null,
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
      await this.writeAccumulatedDamage(args, version, result.remainingHpDamage);
      await this.addNumericState(args, version, EXHAUSTION_STATE_CODE, result.exhaustion);
      await this.addNumericState(
        args,
        version,
        WOUND_STATE_CODE,
        (result.wound ?? 0) + (result.cuttingWound ?? 0),
      );
      await this.addNumericState(args, version, STUNNED_STATE_CODE, result.stun ?? 0);
      await this.addNumericState(args, version, SHOCK_STATE_CODE, result.shock ?? 0);
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
          askTokenSpend: args.askTokenSpend,
          overlay: args.overlay ?? overlay,
        });
        if (checked.overlay) {
          overlay = checked.overlay;
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
        }
      }
    }

    return overlay;
  }
}
