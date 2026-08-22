import type { GameMember } from '@/modules/Roleplay/Game/Dto/GameMember';

/** Контекст игр персонажа (для расширения «Видимость листа» на карточке персонажа). */
export interface CharacterGameContext {
  gameId: number;
  gameName: string;
  members: GameMember[];
}
