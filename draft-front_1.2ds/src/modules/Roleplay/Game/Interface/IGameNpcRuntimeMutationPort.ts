import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';

export interface IGameNpcRuntimeMutationPort {
  capture(npcId: number): GameNpc;
  applyPatch(npcId: number, patch: CharacterPatch): GameNpc;
  restore(npcId: number, snapshot: GameNpc): void;
}
