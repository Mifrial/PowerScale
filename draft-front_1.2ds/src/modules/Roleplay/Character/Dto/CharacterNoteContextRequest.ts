import type { User } from '@/modules/Core/User/Dto/User';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Параметры контекста заметок персонажа: срез ревизии, зритель и роль ссылок. */
export interface CharacterNoteContextRequest {
  rules: Rule[];
  spaceId: number | null;
  spaceCode: string | null;
  rulesRevision: number | null;
  viewer: User | null;
  /** Игра, в контексте которой открывать ссылки; null — standalone-страница персонажа. */
  gameId: number | null;
  /** `game` — ссылки открывают игровую деталку, `standalone` — `/characters/:id`. */
  entityContext: 'game' | 'standalone';
}
