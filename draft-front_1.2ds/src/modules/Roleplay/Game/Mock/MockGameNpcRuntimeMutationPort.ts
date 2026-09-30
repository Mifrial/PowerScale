import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import { characterPatchService } from '@/modules/Roleplay/Character/init';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';
import type { IGameNpcRuntimeMutationPort } from '@/modules/Roleplay/Game/Interface/IGameNpcRuntimeMutationPort';
import {
  captureNpcRuntimeState,
  getStoredNpcVersion,
  replaceNpcRuntimeVersion,
  restoreNpcRuntimeState,
} from '@/modules/Roleplay/Game/Mock/mockGameNpcs';

/**
 * Адаптирует typed Character patch к authoritative NPC version в mock Game.
 * NPC не проходит player moderation flow, но использует optimistic CAS.
 */
export class MockGameNpcRuntimeMutationPort implements IGameNpcRuntimeMutationPort {
  capture(npcId: number): GameNpc {
    return captureNpcRuntimeState(npcId);
  }

  applyPatch(npcId: number, patch: CharacterPatch): GameNpc {
    const current = getStoredNpcVersion(npcId);
    if (current === null) throw new Error('У НПС нет authoritative листа');
    const next = characterPatchService.applyPatch(current, patch.operations);

    return replaceNpcRuntimeVersion(npcId, next, patch.expectedActualVersion);
  }

  restore(npcId: number, snapshot: GameNpc): void {
    restoreNpcRuntimeState(npcId, snapshot);
  }
}
