import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import { getGameApi } from '@/modules/Roleplay/Game/init';
import { attackDamageService } from '@/modules/Roleplay/Game/Service/Instance/attackDamageService';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';
import { formatAttackActionMessage } from '@/modules/Roleplay/Game/Utils/attackDamageMessage';

export class ActionExecutionService {
  async execute(
    input: {
      gameId: number;
      entityKey: CombatEntityKey;
      version: CharacterVersion;
      rule: Rule;
      action: CombatActionOption;
      rules: Rule[];
      pendingEffects: PendingActionEffect[];
      actionPointCost: number;
      attackerName: string;
      chatId: number | null;
      speaker: ChatSpeaker;
      sendChat: (content: string, attachments: ChatAttachment[], chatId: number, speaker: ChatSpeaker) => Promise<unknown>;
    },
  ): Promise<{ overlay: GameCombatOverlay; effects: PendingActionEffect[]; spent: number }> {
    const resource = attackDamageService.actionPointsResource(input.version, input.rules);
    if (!resource) throw new Error('ОД не найдено');
    if (input.actionPointCost <= 0) throw new Error('Укажите количество ОД');
    if (input.actionPointCost > resource.current.base) throw new Error('Недостаточно ОД для действия');

    const resolved = actionEffectService.resolveForNextAction(input.pendingEffects, {
      isAttack: false,
      component: 'strike',
      baseCost: input.actionPointCost,
    });
    const nextResource = attackDamageService.spendActionPoints(resource.current, input.actionPointCost);
    const overlay = await getGameApi().setCombatResource(
      input.gameId,
      input.entityKey,
      resource.ruleId,
      nextResource,
    );
    const effects = [
      ...actionEffectService.consumeResource(resolved.remainingEffects, 'action-points', input.actionPointCost),
      ...actionEffectService.effectsAfterAction(input.rule),
    ];
    await getGameApi().setCombatActionEffects(input.gameId, input.entityKey, effects);
    if (input.chatId !== null) {
      await input.sendChat(
        formatAttackActionMessage({
          attackerKey: input.entityKey,
          attackerName: input.attackerName,
          action: input.action,
          attackerAp: input.actionPointCost,
          rules: input.rules,
        }),
        [],
        input.chatId,
        input.speaker,
      );
    }

    return { overlay, effects, spent: input.actionPointCost };
  }
}
