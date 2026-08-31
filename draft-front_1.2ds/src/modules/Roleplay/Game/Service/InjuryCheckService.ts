import type { CharacterStateValue } from '@/modules/Roleplay/Character/Dto/CharacterStateValue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { InjuryRollInput } from '@/modules/Roleplay/Game/Dto/InjuryRollInput';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { INJURY_DETAILS_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Injury/INJURY_DETAILS_ATTACHMENT_TYPE';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import { MAIM_STATE_CODE } from '@/modules/Roleplay/Rule/init';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { formatInjuryReceivedMessage } from '@/modules/Roleplay/Game/Utils/injuryCheckMessage';
import { injuryRollService } from '@/modules/Roleplay/Game/Service/Instance/injuryRollService';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';

import type { InjuryInputFromAttack } from '@/modules/Roleplay/Game/Dto/InjuryInputFromAttack';
import type { ApplyInjuryCheckArgs } from '@/modules/Roleplay/Game/Dto/ApplyInjuryCheckArgs';
import type { ApplyInjuryCheckResult } from '@/modules/Roleplay/Game/Dto/ApplyInjuryCheckResult';
export class InjuryCheckService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  overlayStateTotal(version: CharacterVersion | null | undefined, rules: Rule[], code: string): number {
    const rule = rules.find((item) => item.code === code && item.type === 'state');
    if (!rule || !version) return 0;

    return version.states
      .filter((state) => state.stateRuleCode === rule.code)
      .reduce((sum, state) => sum + (state.value ?? 0), 0);
  }

  /** Автоувечье только если этот удар дал повреждения или рану (не сырой урон / не один колющий бонус РУ). */
  shouldLaunchInjuryFromAttack(
    source: Pick<InjuryInputFromAttack, 'hpDamage' | 'cuttingWound' | 'woundFromHit'>,
  ): boolean {
    return source.hpDamage > 0 || (source.cuttingWound ?? 0) > 0 || (source.woundFromHit ?? 0) > 0;
  }

  /** Повреждения и рана — этого удара; истощение — с оверлея после записи (накопленное). */
  injuryInputFromAttack(source: InjuryInputFromAttack): InjuryRollInput {
    return {
      leftoverDamage: Math.max(0, source.hpDamage),
      woundStrength: Math.max(0, source.woundFromHit ?? 0) + Math.max(0, source.cuttingWound ?? 0),
      endurance: Math.max(1, source.endurance),
      exhaustion: Math.max(0, source.overlayExhaustion),
      attackSr: Math.max(0, source.remainingSr),
      damageTypeCode: source.damageTypeCode,
      actorKey: source.actorKey,
    };
  }

  async applyInjuryCheck(args: ApplyInjuryCheckArgs): Promise<ApplyInjuryCheckResult> {
    const extras = stateRuntimeEffectsService.checkAdvantageModifiers(args.targetVersion, args.rules);
    const input = extras.length
      ? { ...args.input, advantages: [...(args.input.advantages ?? []), ...extras] }
      : args.input;
    const roll = injuryRollService.rollInjury(input, args.rng ?? Math.random, args.rules, args.mechanics);
    const injury = roll.injury;
    if (!injury) throw new Error('Нет исхода увечья');
    if (args.skipIfNoRoll && roll.rolls.length === 0) {
      return { roll, overlay: null, skipped: true };
    }
    let overlay: GameCombatOverlay | null = null;
    if (injury.strength > 0) {
      const maimRule = args.rules.find((rule) => rule.code === MAIM_STATE_CODE && rule.type === 'state');
      if (maimRule) {
        overlay = await this.resolveGameApi().addCombatState(args.gameId, args.targetKey, {
          stateRuleCode: maimRule.code,
          value: injury.strength,
          maim: {
            permanent: injury.permanent,
            healTotal: injury.heal?.total,
            healUnit: injury.heal?.unit,
            lethal: injury.lethal,
            disfiguring: injury.disfiguring,
          },
        } as CharacterStateValue);
      }
    }
    if (args.chatId !== null) {
      if (roll.rolls.length > 0) {
        const sentRoll = await args.sendMessage(
          '',
          [{ type: ROLL_ATTACHMENT_TYPE, payload: roll }],
          args.chatId,
          args.speaker,
        );
        if (!sentRoll) throw new Error('Не удалось отправить бросок увечья');
      }
      const typeCode = args.input.damageTypeCode;
      const typeRule = typeCode ? args.rules.find((rule) => rule.code === typeCode) : undefined;
      const details =
        roll.rolls.length > 0
          ? [
              {
                type: INJURY_DETAILS_ATTACHMENT_TYPE,
                payload: { injury, damageTypeName: typeRule?.name ?? null },
              },
            ]
          : [];
      const sent = await args.sendMessage(
        [args.chatPrefix, formatInjuryReceivedMessage(args.targetName, injury, args.targetKey)]
          .filter(Boolean)
          .join('\n'),
        details,
        args.chatId,
        args.speaker,
      );
      if (!sent) throw new Error('Не удалось отправить проверку на увечье');
    }

    return { roll, overlay, skipped: false };
  }
}
