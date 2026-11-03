import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Участник боя, который может поддержать удар: персонаж игрока или НПС. */
export interface SupportReactionActor {
  key: CombatEntityKey;
  kind: 'character' | 'npc';
  id: number;
  name: string;
  version: CharacterVersion;
}
