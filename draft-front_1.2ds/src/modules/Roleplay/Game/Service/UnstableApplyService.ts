import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import { CHECK_DEXTERITY_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import { LYING_STATE_CODE, UNSTABLE_STATE_CODE } from '@/modules/Roleplay/Rule/Constant/State/STATE_CODES';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
import { combatOverlayService } from '@/modules/Roleplay/Game/Service/Instance/combatOverlayService';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import { addFlagState, removeStatesByCodes, setNumericState } from '@/modules/Roleplay/Game/Utils/combatStateWrite';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';

/** Прирост Неустойчивости и проверка Ловкости на падение. */
export class UnstableApplyService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  current(version: CharacterVersion): number {
    return version.states
      .filter((state) => state.stateRuleCode === UNSTABLE_STATE_CODE)
      .reduce((sum, state) => sum + (state.value ?? 0), 0);
  }

  async apply(input: {
    gameId: number;
    targetKey: CombatEntityKey;
    version: CharacterVersion;
    rules: Rule[];
    mechanics: Mechanic[];
    amount: number;
    targetName: string;
    rng?: DiceRng;
    chatId: number | null;
    speaker: ChatSpeaker;
    sendMessage: (
      content: string,
      attachments: ChatAttachment[],
      chatId: number,
      speaker: ChatSpeaker,
    ) => Promise<unknown>;
  }): Promise<GameCombatOverlay | null> {
    if (input.amount <= 0) return null;
    if (input.version.states.some((state) => state.stateRuleCode === LYING_STATE_CODE)) return null;
    const next = this.current(input.version) + input.amount;
    let overlay = await setNumericState(
      this.resolveGameApi(),
      input.gameId,
      input.targetKey,
      input.version,
      input.rules,
      UNSTABLE_STATE_CODE,
      next,
    );
    const version = overlay ? combatOverlayService.mergeCombatOverlay(input.version, overlay) : input.version;
    const dexterity =
      stateRuntimeEffectsService.effectiveCharacteristicValues(version, input.rules).get('dexterity') ?? {
        base: 3,
        size: 0,
      };
    const adv = stateRuntimeEffectsService.checkAdvantageFromStates(version, input.rules, {
      kind: 'characteristic',
      code: 'dexterity',
    });
    const roll = checkRollService.rollNamedCheck(
      checkRollService.namedCheckSpec('Ловкость (неустойчивость)', dexterity, adv, input.rules, input.targetKey),
      CHECK_DEXTERITY_CODE,
      { base: next, size: 0 },
      input.rng ?? Math.random,
      input.rules,
      input.mechanics,
    );
    if (input.chatId !== null) {
      await input.sendMessage(
        `${input.targetName} проходит проверку на Ловкость против Неустойчивости ${next}.`,
        [{ type: ROLL_ATTACHMENT_TYPE, payload: roll }],
        input.chatId,
        input.speaker,
      );
    }
    if ((roll.check?.rating ?? 0) > 0) return overlay;
    overlay =
      (await removeStatesByCodes(
        this.resolveGameApi(),
        input.gameId,
        input.targetKey,
        version,
        input.rules,
        [UNSTABLE_STATE_CODE],
      )) ?? overlay;
    overlay =
      (await addFlagState(this.resolveGameApi(), input.gameId, input.targetKey, input.rules, LYING_STATE_CODE)) ??
      overlay;

    return overlay;
  }
}
