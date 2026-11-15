import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';

/** Заметки в слайдере деталки: хост Notes, заголовок и контекст вставки ссылок. */
export interface CharacterDetailNotes {
  /** Тип хоста Notes (`character`, `npc`): от него зависит, к кому крепятся заметки. */
  hostType: string;
  hostId: number;
  title: string;
  inlineContext: Record<string, unknown>;
  tokenSources: ITokenSource[];
}
