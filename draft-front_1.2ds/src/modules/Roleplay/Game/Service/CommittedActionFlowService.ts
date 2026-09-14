import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { ChatSpeaker } from '@/modules/Messages/Chat/Dto/ChatSpeaker';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { CommittedActionSession } from '@/modules/Roleplay/Game/Dto/CommittedActionSession';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { committedActionService } from '@/modules/Roleplay/Game/Service/Instance/committedActionService';
import { woundActionService } from '@/modules/Roleplay/Game/Service/Instance/woundActionService';
import { actionExecutionService } from '@/modules/Roleplay/Game/Service/Instance/actionExecutionService';
import { findRuleByRef } from '@/modules/Roleplay/Game/Utils/combatActions';

/**
 * Старт, продолжение и срыв действия с долгом ОД; эффект только на последнем платеже.
 */
export class CommittedActionFlowService {
  constructor(private readonly resolveGameApi: () => IGameApi) {}

  async begin(input: {
    gameId: number;
    actorKey: CombatEntityKey;
    version: CharacterVersion;
    rules: Rule[];
    action: CombatActionOption;
    totalOd: number;
    available: number;
    targetKey: CombatEntityKey | null;
    stateIndices: number[];
    chatId: number | null;
    speaker: ChatSpeaker;
    sendChat: (
      content: string,
      attachments: ChatAttachment[],
      chatId: number,
      speaker: ChatSpeaker,
    ) => Promise<unknown>;
  }): Promise<CommittedActionSession> {
    const paid = committedActionService.pay(input.totalOd, input.available);
    if (paid.spent <= 0) throw new Error('Нет ОД, чтобы начать действие');
    await woundActionService.spendActionPoints(input.gameId, input.actorKey, input.version, input.rules, paid.spent);
    const session = committedActionService.start({
      gameId: input.gameId,
      entityKey: input.actorKey,
      actionRuleCode: input.action.code,
      totalOd: input.totalOd,
      spent: paid.spent,
      targetKey: input.targetKey,
      stateIndices: input.stateIndices,
    });
    await this.resolveGameApi().setCommittedActionSession(input.gameId, input.actorKey, session);
    if (input.chatId !== null) {
      await input.sendChat(
        `${input.action.name} начат · потрачено ${paid.spent} ОД, осталось ${session.remainingOd}.`,
        [],
        input.chatId,
        input.speaker,
      );
    }

    return session;
  }

  async continueTurn(input: {
    gameId: number;
    session: CommittedActionSession;
    version: CharacterVersion;
    rules: Rule[];
    mechanics: Mechanic[];
    available: number;
    action: CombatActionOption;
    chatId: number | null;
    speaker: ChatSpeaker;
    attackerName: string;
    sendChat: (
      content: string,
      attachments: ChatAttachment[],
      chatId: number,
      speaker: ChatSpeaker,
    ) => Promise<unknown>;
    pendingEffects: PendingActionEffect[];
  }): Promise<CommittedActionSession | null> {
    const paid = committedActionService.pay(input.session.remainingOd, input.available);
    if (paid.spent <= 0) return input.session;
    const next = committedActionService.continueWith(input.session, paid.spent);
    if (!committedActionService.isComplete(next)) {
      await woundActionService.spendActionPoints(
        input.gameId,
        input.session.entityKey,
        input.version,
        input.rules,
        paid.spent,
      );
      await this.resolveGameApi().setCommittedActionSession(input.gameId, input.session.entityKey, next);
      if (input.chatId !== null) {
        const name = findRuleByRef(input.rules, input.session.actionRuleCode)?.name ?? input.session.actionRuleCode;
        await input.sendChat(
          `${name} продолжен · потрачено ${paid.spent} ОД, осталось ${next.remainingOd}.`,
          [],
          input.chatId,
          input.speaker,
        );
      }

      return next;
    }
    const rule = findRuleByRef(input.rules, input.session.actionRuleCode);
    if (!rule) throw new Error('Правило действия не найдено в текущей ревизии');
    await actionExecutionService.execute({
      gameId: input.gameId,
      entityKey: input.session.entityKey,
      version: input.version,
      rule,
      action: input.action,
      rules: input.rules,
      pendingEffects: input.pendingEffects,
      actionPointCost: paid.spent,
      attackerName: input.attackerName,
      chatId: input.chatId,
      speaker: input.speaker,
      sendChat: input.sendChat,
    });
    await woundActionService.applyCommittedWound(input.gameId, input.session.entityKey, input.version, input.session);
    await this.resolveGameApi().setCommittedActionSession(input.gameId, input.session.entityKey, null);

    return null;
  }

  async abort(
    gameId: number,
    session: CommittedActionSession,
    rules: Rule[],
    chatId: number | null,
    speaker: ChatSpeaker,
    sendChat: (
      content: string,
      attachments: ChatAttachment[],
      chatId: number,
      speaker: ChatSpeaker,
    ) => Promise<unknown>,
  ): Promise<void> {
    await this.resolveGameApi().setCommittedActionSession(gameId, session.entityKey, null);
    if (chatId === null) return;
    const name = findRuleByRef(rules, session.actionRuleCode)?.name ?? session.actionRuleCode;
    await sendChat(`${name} сорван · уже потраченные ОД не возвращаются.`, [], chatId, speaker);
  }
}
