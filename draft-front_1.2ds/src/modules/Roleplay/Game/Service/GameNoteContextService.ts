import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { NoteHostContext } from '@/modules/Messages/Notes/Dto/NoteHostContext';
import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameNoteContextRequest } from '@/modules/Roleplay/Game/Dto/GameNoteContextRequest';
import type { GameNpcListQuery } from '@/modules/Roleplay/Game/Dto/GameNpcListQuery';
import type { GameNpcListResult } from '@/modules/Roleplay/Game/Dto/GameNpcListResult';
import type { GameChatRulesContextService } from '@/modules/Roleplay/Game/Service/GameChatRulesContextService';

/** Сколько вариантов показывать в пикере до ввода запроса. */
const SUGGESTION_LIMIT = 10;

/**
 * Контекст заметок игры: подписи правил ревизии и источники «Вставить ссылку», ограниченные
 * персонажами и активными НПС этой игры.
 */
export class GameNoteContextService {
  constructor(
    private readonly loadMemberships: (gameId: number) => Promise<GameCharacterMembership[]>,
    private readonly loadNpcs: (query: GameNpcListQuery) => Promise<GameNpcListResult>,
    private readonly rulesContext: GameChatRulesContextService,
  ) {}

  build(request: GameNoteContextRequest): NoteHostContext {
    const context = this.rulesContext.buildChatRulesContext(
      request.rules,
      request.mechanics,
      request.spaceId,
      request.rulesRevision,
      request.spaceCode,
    );
    const tokenSources: ITokenSource[] = [
      ...context.tokenSources.filter((source) => source.type === 'rule'),
      this.buildCharacterSource(request.gameId),
      this.buildNpcSource(request.gameId),
      ...context.tokenSources.filter(
        (source) => source.type !== 'rule' && source.type !== 'character' && source.type !== 'npc',
      ),
    ];

    return {
      inlineContext: {
        tokenLabels: context.tokenLabels,
        spaceId: context.spaceId,
        spaceCode: context.spaceCode,
        rulesRevision: context.rulesRevision,
        gameId: request.gameId,
        entityContext: 'game',
      },
      tokenSources,
    };
  }

  private buildCharacterSource(gameId: number): ITokenSource {
    let cached: Promise<GameCharacterMembership[]> | null = null;
    const load = (): Promise<GameCharacterMembership[]> => {
      cached ??= this.loadMemberships(gameId).catch(() => []);

      return cached;
    };

    return {
      type: 'character',
      label: 'Персонаж',
      icon: 'mdi-account',
      search: async (query) => {
        const normalized = query.trim().toLowerCase();
        const memberships = (await load())
          .filter((membership) => membership.membershipStatus !== 'left')
          .sort((left, right) => left.characterName.localeCompare(right.characterName));
        const matched = normalized
          ? memberships.filter((membership) => membership.characterName.toLowerCase().includes(normalized))
          : memberships.slice(0, SUGGESTION_LIMIT);

        return matched.map((membership) => ({
          value: String(membership.characterId),
          label: membership.characterName,
        }));
      },
    };
  }

  private buildNpcSource(gameId: number): ITokenSource {
    return {
      type: 'npc',
      label: 'НПС',
      icon: 'mdi-account-alert',
      search: async (query) => {
        const result = await this.loadNpcs({
          gameId,
          status: 'active',
          query: query.trim() || undefined,
          limit: SUGGESTION_LIMIT,
        }).catch(() => ({ items: [], nextCursor: null }));

        return result.items.map((npc) => ({ value: String(npc.id), label: npc.name }));
      },
    };
  }
}
