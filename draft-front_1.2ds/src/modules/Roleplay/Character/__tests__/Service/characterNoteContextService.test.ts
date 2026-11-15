import { describe, expect, it } from 'vitest';
import { characters } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { users } from '@/modules/Core/User/Mock/mockUsers';
import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { ITokenSource } from '@/modules/Messages/Inline/Interface/ITokenSource';
import { characterAccessService } from '@/modules/Roleplay/Character/Service/Instance/characterAccessService';
import { characterChatRulesContextService } from '@/modules/Roleplay/Character/Service/Instance/characterChatRulesContextService';
import { CharacterNoteContextService } from '@/modules/Roleplay/Character/Service/CharacterNoteContextService';

const viewer = users.find((user) => user.id === 6);

if (!viewer) throw new Error('Нет тестового пользователя 6');

const visibleCharacter: Character = {
  ...characters[0],
  id: 101,
  name: 'Видимый Персонаж',
  ownerId: 99,
  visibility: [{ audience: [6], sections: ['shortDescription'] }],
};

const hiddenCharacter: Character = {
  ...characters[0],
  id: 102,
  name: 'Скрытый Персонаж',
  ownerId: 99,
  visibility: [],
};

function sourceOf(sources: ITokenSource[], type: string): ITokenSource {
  const source = sources.find((candidate) => candidate.type === type);

  if (!source) throw new Error(`Нет источника ${type}`);

  return source;
}

function serviceWith(charactersList: Character[] | Promise<Character[]>): CharacterNoteContextService {
  return new CharacterNoteContextService(
    () => Promise.resolve(charactersList),
    characterChatRulesContextService,
    characterAccessService,
  );
}

describe('CharacterNoteContextService', () => {
  it('помечает ссылки standalone-контекстом и отдаёт игру для НПС', () => {
    const context = serviceWith([visibleCharacter]).build({
      rules: [],
      spaceId: 1,
      rulesRevision: 2,
      viewer,
      gameId: 5,
      entityContext: 'standalone',
    });

    expect(context.inlineContext.gameId).toBe(5);
    expect(context.inlineContext.entityContext).toBe('standalone');
    expect(context.inlineContext.spaceId).toBe(1);
    expect(context.inlineContext.rulesRevision).toBe(2);
  });

  it('предлагает только видимые зрителю листы и ищет по имени', async () => {
    const source = sourceOf(
      serviceWith([visibleCharacter, hiddenCharacter]).build({
        rules: [],
        spaceId: 1,
        rulesRevision: 1,
        viewer,
        gameId: null,
        entityContext: 'standalone',
      }).tokenSources,
      'character',
    );

    const all = await source.search('');
    expect(all.map((option) => option.value)).toEqual(['101']);

    const matched = await source.search('видим');
    expect(matched).toEqual([{ value: '101', label: 'Видимый Персонаж' }]);

    expect(await source.search('скрыт')).toEqual([]);
  });

  it('не ломает пикер при ошибке загрузки списка', async () => {
    const service = new CharacterNoteContextService(
      () => Promise.reject(new Error('network')),
      characterChatRulesContextService,
      characterAccessService,
    );
    const source = sourceOf(
      service.build({
        rules: [],
        spaceId: null,
        rulesRevision: null,
        viewer,
        gameId: null,
        entityContext: 'standalone',
      }).tokenSources,
      'character',
    );

    expect(await source.search('')).toEqual([]);
  });
});
