import type { NoteHostContext } from '@/modules/Messages/Notes/Dto/NoteHostContext';
import type { GameDetail } from '@/modules/Roleplay/Game/Dto/GameDetail';
import type { GameNpcRef } from '@/modules/Roleplay/Game/Dto/GameNpcRef';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { GameNoteContextService } from '@/modules/Roleplay/Game/Service/GameNoteContextService';

/** Ревизия правил игры для подписей чипов и источников заметок. */
export interface NpcNoteRevision {
  rules: Rule[];
}

/** Пустой контекст: заметка открывается, но без ссылок на домен. */
const EMPTY_CONTEXT: NoteHostContext = { inlineContext: {}, tokenSources: [] };

/**
 * Хост заметок НПС: по одному npcId находит игру, маршрут карточки и контекст ссылок.
 * Кэширует поиск, потому что в `/notes` маршрут и контекст запрашиваются отдельно.
 */
export class NpcNoteHostService {
  private readonly refs = new Map<number, Promise<GameNpcRef | null>>();

  constructor(
    private readonly findNpc: (npcId: number) => Promise<GameNpcRef>,
    private readonly loadGame: (gameId: number) => Promise<GameDetail>,
    private readonly loadRevision: (spaceId: number, rulesRevision: number) => Promise<NpcNoteRevision>,
    private readonly notesContext: GameNoteContextService,
  ) {}

  /** Маршрут карточки НПС; null — НПС не найден или недоступен. */
  async resolveRoute(npcId: number): Promise<string | null> {
    const npc = await this.refOf(npcId);

    return npc ? `/games/${npc.gameId}/npcs/${npc.id}` : null;
  }

  /** Контекст ссылок в контексте игры; без игры — пустой. */
  async resolveContext(npcId: number): Promise<NoteHostContext> {
    const npc = await this.refOf(npcId);
    if (!npc) return EMPTY_CONTEXT;
    const detail = await this.loadGame(npc.gameId);
    const revision = await this.revisionOf(detail);

    return this.notesContext.build({
      rules: revision.rules,
      mechanics: [],
      spaceId: detail.game.spaceId,
      spaceCode: detail.game.spaceCode,
      rulesRevision: detail.game.rulesRevision,
      gameId: detail.game.id,
    });
  }

  private refOf(npcId: number): Promise<GameNpcRef | null> {
    const cached = this.refs.get(npcId);
    if (cached) return cached;
    const request = this.findNpc(npcId).catch(() => null);
    this.refs.set(npcId, request);

    return request;
  }

  private async revisionOf(detail: GameDetail): Promise<NpcNoteRevision> {
    try {
      return await this.loadRevision(detail.game.spaceId, detail.game.rulesRevision);
    } catch {
      return { rules: [] };
    }
  }
}
