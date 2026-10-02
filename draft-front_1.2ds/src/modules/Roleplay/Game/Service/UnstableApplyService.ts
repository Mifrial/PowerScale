import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRng } from '@/modules/Roleplay/Game/Dto/DiceRng';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { checkResolutionService } from '@/modules/Roleplay/Rule/init';
import { checkRollService } from '@/modules/Roleplay/Game/Service/Instance/checkRollService';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/init';
import { addFlagState, removeStatesByCodes, setNumericState } from '@/modules/Roleplay/Game/Utils/combatStateWrite';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { ROLL_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Roll/ROLL_ATTACHMENT_TYPE';

/** Прирост Неустойчивости и проверка Ловкости на падение. */
export class UnstableApplyService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  current(version: CharacterVersion, rules: Rule[]): number {
    const code = attackDamageService.unstableRule(rules)?.code;
    if (!code) return 0;

    return version.states
      .filter((state) => state.stateRuleCode === code)
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
  }): Promise<null> {
    if (input.amount <= 0) return null;
    const lyingCode = attackDamageService.lyingRule(input.rules)?.code;
    if (lyingCode && input.version.states.some((state) => state.stateRuleCode === lyingCode)) return null;
    const unstableCode = attackDamageService.unstableRule(input.rules)?.code;
    if (!unstableCode) return null;
    const next = this.current(input.version, input.rules) + input.amount;
    await setNumericState(
      this.resolveGameApi(),
      input.gameId,
      input.targetKey,
      input.version,
      input.rules,
      unstableCode,
      next,
    );
    const version = input.version;
    const checkCode = checkResolutionService.firstCheckCode(input.rules, 'unstable_check');
    const characteristic = attackDamageService.unstableRollRule(input.rules);
    if (!checkCode || !characteristic) return null;
    const characteristicValue = stateRuntimeEffectsService
      .effectiveCharacteristicValues(version, input.rules)
      .get(characteristic.code) ?? {
      base: 3,
      size: 0,
    };
    const adv = stateRuntimeEffectsService.checkAdvantageFromStates(version, input.rules, {
      kind: 'characteristic',
      code: characteristic.code,
    });
    const roll = checkRollService.rollNamedCheck(
      checkRollService.namedCheckSpec(
        `${characteristic.name} (неустойчивость)`,
        characteristicValue,
        adv,
        input.rules,
        input.targetKey,
      ),
      checkCode,
      { base: next, size: 0 },
      input.rng ?? Math.random,
      input.rules,
      input.mechanics,
    );
    if (input.chatId !== null) {
      await input.sendMessage(
        `${input.targetName} проходит проверку на ${characteristic.name} против Неустойчивости ${next}.`,
        [{ type: ROLL_ATTACHMENT_TYPE, payload: roll }],
        input.chatId,
        input.speaker,
      );
    }
    if ((roll.check?.rating ?? 0) > 0) return null;
    await removeStatesByCodes(this.resolveGameApi(), input.gameId, input.targetKey, version, input.rules, [
      unstableCode,
    ]);
    if (lyingCode) {
      await addFlagState(this.resolveGameApi(), input.gameId, input.targetKey, input.rules, lyingCode);
    }

    return null;
  }
}
