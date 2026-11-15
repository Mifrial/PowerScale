import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { CharacterNoteContextRequest } from '@/modules/Roleplay/Character/Dto/CharacterNoteContextRequest';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { NoteHostContext } from '@/modules/Messages/Notes/Dto/NoteHostContext';
import type { CharacterAccessService } from '@/modules/Roleplay/Character/Service/CharacterAccessService';
import type { CharacterChatRulesContextService } from '@/modules/Roleplay/Character/Chat/CharacterChatRulesContextService';

/** Сколько вариантов показывать в пикере до ввода запроса. */
const SUGGESTION_LIMIT = 10;

/**
 * Контекст заметок персонажа: подписи правил ревизии и источники «Вставить ссылку».
 * Ссылки на персонажей ограничены листами, видимыми текущему зрителю; НПС сюда не входят,
 * потому что Character не знает Game.
 */
export class CharacterNoteContextService {
  constructor(
    private readonly loadCharacters: () => Promise<Character[]>,
    private readonly rulesContext: CharacterChatRulesContextService,
    private readonly access: CharacterAccessService,
  ) {}

  build(request: CharacterNoteContextRequest): NoteHostContext {
    const context = this.rulesContext.build(request.rules, request.spaceId, request.rulesRevision, request.spaceCode);
    const tokenSources: ITokenSource[] = [
      ...context.tokenSources.filter((source) => source.type === 'rule'),
      this.buildCharacterSource(request),
      ...context.tokenSources.filter((source) => source.type !== 'rule' && source.type !== 'character'),
    ];

    return {
      inlineContext: {
        tokenLabels: context.tokenLabels,
        spaceId: context.spaceId,
        spaceCode: context.spaceCode,
        rulesRevision: context.rulesRevision,
        gameId: request.gameId,
        entityContext: request.entityContext,
      },
      tokenSources,
    };
  }

  private buildCharacterSource(request: CharacterNoteContextRequest): ITokenSource {
    let cached: Promise<Character[]> | null = null;
    const load = (): Promise<Character[]> => {
      cached ??= this.loadCharacters().catch(() => []);

      return cached;
    };

    return {
      type: 'character',
      label: 'Персонаж',
      icon: 'mdi-account',
      search: async (query) => {
        const viewer = request.viewer;
        if (!viewer) return [];
        const normalized = query.trim().toLowerCase();
        const visible = (await load())
          .filter((character) => this.access.canViewCharacter(viewer, character))
          .sort((left, right) => left.name.localeCompare(right.name));
        const matched = normalized
          ? visible.filter((character) => character.name.toLowerCase().includes(normalized))
          : visible.slice(0, SUGGESTION_LIMIT);

        return matched.map((character) => ({ value: String(character.id), label: character.name }));
      },
    };
  }
}
