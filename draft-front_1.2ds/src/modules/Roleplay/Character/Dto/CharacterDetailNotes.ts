import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';

/** Заметки персонажа, открываемые из слайдера деталки: хост и контекст вставки ссылок. */
export interface CharacterDetailNotes {
  hostId: number;
  title: string;
  inlineContext: Record<string, unknown>;
  tokenSources: ITokenSource[];
}
