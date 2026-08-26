import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';
import type { CharacterPoints } from '@/modules/Roleplay/Character/Dto/CharacterPoints';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';

export interface Character {
  id: number;
  name: string;
  status: CharacterStatus;
  active: boolean;
  ownerId: number;
  ownerName: string;
  raceId: number | null;
  raceLabel: string | null;
  gameId: number | null;
  gameName: string | null;
  spaceId: number;
  spaceCode: string;
  rulesRevision: number;
  shortDescription: string | null;
  currentPoints: CharacterPoints;
  /** Зоны видимости листа (общая для везде; дефолт — полный лист). */
  visibility: SheetVisibility;
  /** Чат обсуждения листа; null — ещё не создан. На списке, чтобы резолв чипов не ходил в деталки. */
  discussionChatId: number | null;
}
