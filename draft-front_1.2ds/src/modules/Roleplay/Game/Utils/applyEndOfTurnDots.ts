import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { StateSpec } from '@/modules/Roleplay/Rule/Dto/State/StateSpec';
import { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';
import {
  EXHAUSTION_STATE_CODE,
  STUNNED_STATE_CODE,
  WOUND_STATE_CODE,
} from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { applyAttackDamage, enduranceOf } from '@/modules/Roleplay/Game/Utils/applyAttackDamage';
import { applyExhaustionCheck } from '@/modules/Roleplay/Game/Utils/applyExhaustionCheck';
import {
  applyInjuryCheck,
  injuryInputFromAttack,
  overlayStateTotal,
  shouldLaunchInjuryFromAttack,
} from '@/modules/Roleplay/Game/Utils/applyInjuryCheck';
import { advanceDotState } from '@/modules/Roleplay/Game/Utils/dotTickMath';
import { formatDotTickMessage, buildDotTickAttachment } from '@/modules/Roleplay/Game/Utils/dotTickMessage';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { mergeCombatOverlay } from '@/modules/Roleplay/Game/Utils/mergeCombatOverlay';
import { resolveDamageTypeHooks } from '@/modules/Roleplay/Game/Utils/resolveDamageTypeHooks';
import { asDamageTypeSpec } from '@/modules/Roleplay/Rule/Utils/damageTypeSpec';

export interface ApplyEndOfTurnDotsArgs {
  version: CharacterVersion;
  endurance: number;
  rng?: DiceRng;
  rules: Rule[];
  mechanics: Mechanic[];
  gameId: number;
  targetKey: CombatEntityKey;
  targetName: string;
  chatId: number | null;
  speaker: ChatSpeaker;
  sendMessage: (
    content: string,
    attachments: ChatAttachment[],
    chatId: number,
    speaker: ChatSpeaker,
  ) => Promise<boolean>;
}

async function addNumericState(
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
    return getGameApi().setCombatStateValue(
      args.gameId,
      args.targetKey,
      index,
      (version.states[index]?.value ?? 0) + amount,
    );
  }

  return getGameApi().addCombatState(args.gameId, args.targetKey, { stateRuleId: rule.id, value: amount });
}

export async function applyEndOfTurnDots(args: ApplyEndOfTurnDotsArgs): Promise<GameCombatOverlay | null> {
  let version = args.version;
  let overlay: GameCombatOverlay | null = null;
  const advances = version.states.map((state) => advanceDotState(state, args.rules));
  for (let index = advances.length - 1; index >= 0; index -= 1) {
    const step = advances[index];
    if (step.kind === 'skip') continue;
    if (step.kind === 'wait') {
      overlay = await getGameApi().replaceCombatState(args.gameId, args.targetKey, index, step.next);
    } else if (step.next) {
      overlay = await getGameApi().replaceCombatState(args.gameId, args.targetKey, index, step.next);
    } else {
      overlay = await getGameApi().removeCombatState(args.gameId, args.targetKey, index);
    }
    if (overlay) version = mergeCombatOverlay(version, overlay);
  }
  const fires = advances.filter((step) => step.kind === 'tick');
  for (const step of fires) {
    if (step.kind !== 'tick') continue;
    const overview = characterOverviewService.build(version, args.rules);
    const typeRule = args.rules.find((rule) => rule.code === step.damageTypeCode && rule.type === 'damage_type');
    const hooks = resolveDamageTypeHooks(step.damageTypeCode, args.rules, args.mechanics);
    const result = applyAttackDamage({
      weaponDamage: step.strength,
      sr: 1,
      damageTypeCode: step.damageTypeCode,
      defense: overview.defense ?? null,
      endurance: overview.characteristics.length ? enduranceOf(overview, args.rules) : Math.max(1, args.endurance),
      hooks,
      defenseIgnored: asDamageTypeSpec(typeRule)?.defense_ignored === true,
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
            asDamageTypeSpec(typeRule)?.defense_ignored === true,
          ),
        ],
        args.chatId,
        args.speaker,
      );
      if (!sent) throw new Error('Не удалось отправить сообщение о тике');
    }
    const exh = await addNumericState(args, version, EXHAUSTION_STATE_CODE, result.exhaustion);
    if (exh) {
      overlay = exh;
      version = mergeCombatOverlay(version, exh);
    }
    const wound = await addNumericState(
      args,
      version,
      WOUND_STATE_CODE,
      (result.wound ?? 0) + (result.cuttingWound ?? 0),
    );
    if (wound) {
      overlay = wound;
      version = mergeCombatOverlay(version, wound);
    }
    const stun = await addNumericState(args, version, STUNNED_STATE_CODE, result.stun ?? 0);
    if (stun) {
      overlay = stun;
      version = mergeCombatOverlay(version, stun);
    }
    if (result.exhaustion > 0) {
      const checked = await applyExhaustionCheck({
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
        version = mergeCombatOverlay(version, checked.overlay);
      }
    }
    if (
      shouldLaunchInjuryFromAttack({
        hpDamage: result.hpDamage,
        cuttingWound: result.cuttingWound,
        woundFromHit: result.wound,
      })
    ) {
      const applied = await applyInjuryCheck({
        input: injuryInputFromAttack({
          hpDamage: result.hpDamage,
          cuttingWound: result.cuttingWound,
          woundFromHit: result.wound,
          overlayExhaustion: overlayStateTotal(version, args.rules, EXHAUSTION_STATE_CODE),
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
        version = mergeCombatOverlay(version, applied.overlay);
      }
    }
  }

  return overlay;
}
