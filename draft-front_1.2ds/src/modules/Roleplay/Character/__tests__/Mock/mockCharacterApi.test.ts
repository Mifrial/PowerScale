import { describe, it, expect } from 'vitest';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import {
  createCharacter,
  updateCharacter,
  fetchCharacters,
  fetchCharacter,
  addCustomRule,
  updateCustomRule,
  updateOwnerNotes,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { mockLogin, mockLogout } from '@/modules/Core/Auth/Mock/mockAuth';
import { mockGetChats } from '@/modules/Messages/Chat/Mock/mockChat';
import { commitDraft } from '@/modules/Roleplay/Space/Mock/mockSpaces';

const version: CharacterVersion = {
  name: 'Новичок',
  shortDescription: 'Только начал путь.',
  fullDescription: null,
  spaceCode: 'razrabotka',
  rulesRevision: 5,
  raceRuleId: 'rule-6',
  characteristics: [],
  resources: [],
  abilities: [{ ruleId: 'rule-25', level: 1 }],
  points: { osSpent: 10, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: 12 },
  money: 0,
  ageYears: null,
  inventory: [],
  states: [],
  senses: [],
};

const createData: CreateCharacterData = { spaceId: 1, spaceCode: 'razrabotka', rulesRevision: 5, version };

// Свежая версия на каждый вызов: createCharacter сохраняет versions[id] по ссылке на data.version,
// а addCustomRule/updateCustomRule мутируют versions[id]. Без клона все персонажи делили бы один
// объект version (общая константа) и записи кастомных правил накапливались бы между тестами.
function freshCreateData(): CreateCharacterData {
  return { spaceId: 1, spaceCode: 'razrabotka', rulesRevision: 5, version: structuredClone(version) };
}

describe('mockCharacterApi: create/update', () => {
  it('createCharacter добавляет персонажа, чат обсуждения и возвращает деталку', async () => {
    const detail = await createCharacter(createData);

    expect(detail.character.name).toBe('Новичок');
    expect(detail.character.raceLabel).toBe('Человек');
    expect(detail.character.spaceCode).toBe('razrabotka');
    // Без переданного статуса — дефолт «Черновик».
    expect(detail.character.status).toBe('draft');
    // Новому персонажу создаётся character_discussion-чат (владелец — участник).
    expect(detail.discussionChatId).toEqual(expect.any(Number));
    const chats = await mockGetChats();
    const chat = chats.find((c) => c.id === detail.discussionChatId);
    expect(chat?.type).toBe('character_discussion');
    expect(chat?.name).toBe('Новичок');
    expect(chat?.members.some((m) => m.userId === detail.character.ownerId)).toBe(true);
    expect(detail.version.abilities).toEqual([{ ruleId: 'rule-25', level: 1 }]);

    const list = await fetchCharacters();
    expect(list.some((c) => c.id === detail.character.id)).toBe(true);
  });

  it('createCharacter со status ready сохраняет «Готов» (редактор вне игры)', async () => {
    const detail = await createCharacter({ ...createData, status: 'ready' });

    expect(detail.character.status).toBe('ready');
  });

  it('updateCharacter применяет переданный статус листа', async () => {
    const created = await createCharacter({ ...createData, status: 'ready' });
    const updatedVersion: CharacterVersion = { ...version, name: 'Новичок II', raceRuleId: 'rule-126' };

    const detail = await updateCharacter(created.character.id, { version: updatedVersion, status: 'ready' });
    expect(detail.character.status).toBe('ready');
    expect(detail.character.name).toBe('Новичок II');
    expect(detail.character.raceLabel).toBe('Арилет');
    expect(detail.character.currentPoints.or).toBe(12);

    const fetched = await fetchCharacter(created.character.id);
    expect(fetched.version.name).toBe('Новичок II');
  });

  it('updateCharacter без статуса сохраняет текущий', async () => {
    const created = await createCharacter(createData); // draft
    await updateCharacter(created.character.id, { version });

    expect((await fetchCharacter(created.character.id)).character.status).toBe('draft');
  });

  it('updateCharacter несуществующего персонажа бросает ошибку', async () => {
    await expect(updateCharacter(999, { version })).rejects.toThrow('not found');
  });

  it('createCharacter ставит владельцем текущего пользователя сессии', async () => {
    const admin = await mockLogin('admin@test.com', 'test');
    try {
      const detail = await createCharacter(createData);

      expect(detail.character.ownerId).toBe(admin.id);
      expect(detail.character.ownerName).toBe('Администратор');
    } finally {
      await mockLogout();
    }
  });
});

describe('mockCharacterApi: custom rules («Уникальные правила»)', () => {
  it('addCustomRule добавляет активную текстовую запись в версию', async () => {
    const created = await createCharacter(freshCreateData());
    const detail = await addCustomRule(created.character.id, {
      kind: 'item',
      name: 'Амулет дракона',
      description: 'Дарует жаркое дыхание раз в день.',
    });

    expect(detail.version.customRules).toHaveLength(1);
    const entry = detail.version.customRules![0];
    expect(entry.kind).toBe('item');
    expect(entry.name).toBe('Амулет дракона');
    expect(entry.description).toBe('Дарует жаркое дыхание раз в день.');
    expect(entry.status).toBe('active');
    expect(entry.replacedWithRuleId).toBeUndefined();
    // Персист: повторная загрузка отдаёт запись.
    const fetched = await fetchCharacter(created.character.id);
    expect(fetched.version.customRules?.[0]?.name).toBe('Амулет дракона');
  });

  it('addCustomRule без description хранит null и не ломает прочие поля версии', async () => {
    const created = await createCharacter(freshCreateData());
    const detail = await addCustomRule(created.character.id, {
      kind: 'ability',
      name: 'Мастерство ярости',
      description: null,
    });

    expect(detail.version.customRules![0].description).toBeNull();
    expect(detail.version.name).toBe('Новичок');
    expect(detail.version.rulesRevision).toBe(5);
  });

  it('updateCustomRule заменяет запись (deprecated + replacedWithRuleId)', async () => {
    const created = await createCharacter(freshCreateData());
    const detail = await addCustomRule(created.character.id, {
      kind: 'ability',
      name: 'Ярость',
      description: 'Временная.',
    });
    const entryId = detail.version.customRules![0].id;

    const updated = await updateCustomRule(created.character.id, entryId, {
      status: 'deprecated',
      replacedWithRuleId: 'rule-25',
    });

    expect(updated.version.customRules![0].status).toBe('deprecated');
    expect(updated.version.customRules![0].replacedWithRuleId).toBe('rule-25');
    expect(updated.version.customRules![0].name).toBe('Ярость');
  });

  it('updateCustomRule правит текст, не трогая статус', async () => {
    const created = await createCharacter(freshCreateData());
    const detail = await addCustomRule(created.character.id, { kind: 'item', name: 'Меч', description: null });
    const entryId = detail.version.customRules![0].id;

    const updated = await updateCustomRule(created.character.id, entryId, {
      name: 'Меч правосудия',
      description: 'Светится в темноте.',
    });

    expect(updated.version.customRules![0].name).toBe('Меч правосудия');
    expect(updated.version.customRules![0].description).toBe('Светится в темноте.');
    expect(updated.version.customRules![0].status).toBe('active');
  });

  it('updateCustomRule несуществующей записи бросает ошибку', async () => {
    const created = await createCharacter(freshCreateData());
    await expect(updateCustomRule(created.character.id, 999, { status: 'deprecated' })).rejects.toThrow('not found');
  });

  it('addCustomRule несуществующего персонажа бросает ошибку', async () => {
    await expect(addCustomRule(999, { kind: 'item', name: 'X', description: null })).rejects.toThrow('not found');
  });

  it('замена на правило-итем материализует предмет в инвентаре без списания денег', async () => {
    const created = await createCharacter(freshCreateData());
    const moneyBefore = created.version.money;
    const inventoryBefore = created.version.inventory.length;
    const detail = await addCustomRule(created.character.id, {
      kind: 'item',
      name: 'Булочка',
      description: 'С корицей.',
    });
    const entryId = detail.version.customRules![0].id;

    const updated = await updateCustomRule(created.character.id, entryId, {
      status: 'deprecated',
      replacedWithRuleId: 'rule-407',
    });

    expect(updated.version.customRules![0].status).toBe('deprecated');
    expect(updated.version.customRules![0].replacedWithRuleId).toBe('rule-407');
    const item = updated.version.inventory.find((entry) => entry.ruleId === 'rule-407');
    expect(item).toBeDefined();
    expect(item?.quantity).toBe(1);
    expect(item?.equipped).toBe(false);
    expect(updated.version.inventory.length).toBe(inventoryBefore + 1);
    // Выдача ГМ — не покупка: деньги не списываются.
    expect(updated.version.money).toBe(moneyBefore);
  });

  it('повторная замена на тот же итем инкрементирует quantity, а не плодит дубль', async () => {
    const created = await createCharacter(freshCreateData());
    const detail = await addCustomRule(created.character.id, { kind: 'item', name: 'Меч', description: null });
    const entryId = detail.version.customRules![0].id;

    await updateCustomRule(created.character.id, entryId, {
      status: 'deprecated',
      replacedWithRuleId: 'rule-407',
    });
    const second = await updateCustomRule(created.character.id, entryId, {
      status: 'deprecated',
      replacedWithRuleId: 'rule-407',
    });

    const items = second.version.inventory.filter((entry) => entry.ruleId === 'rule-407');
    expect(items).toHaveLength(1);
    expect(items[0].quantity).toBe(2);
  });

  it('замена на правило не-итем (ability) инвентарь не трогает', async () => {
    const created = await createCharacter(freshCreateData());
    const inventoryBefore = created.version.inventory.length;
    const detail = await addCustomRule(created.character.id, { kind: 'ability', name: 'Ярость', description: null });
    const entryId = detail.version.customRules![0].id;

    const updated = await updateCustomRule(created.character.id, entryId, {
      status: 'deprecated',
      replacedWithRuleId: 'rule-25',
    });

    expect(updated.version.customRules![0].replacedWithRuleId).toBe('rule-25');
    expect(updated.version.inventory.length).toBe(inventoryBefore);
  });

  it('замена на итем из черновика (коммит → ревизия) материализует предмет', async () => {
    // Персонаж в space 2 ('actual') на ревизии, куда закоммичен итем «Палка» из черновика —
    // его нет в ruleCatalog, но есть в срезе ревизии (Баг A).
    const version13 = { ...structuredClone(version), spaceCode: 'actual', rulesRevision: 12 };
    const created = await createCharacter({ spaceId: 2, spaceCode: 'actual', rulesRevision: 12, version: version13 });
    const detail = await addCustomRule(created.character.id, {
      kind: 'item',
      name: 'Палка',
      description: 'Копалка',
    });
    const entryId = detail.version.customRules![0].id;

    // Коммитим «Палку» в space 2 → появляется в ревизии 13 (id rule-N, вне ruleCatalog).
    const rev = await commitDraft(2, [
      {
        id: 'draft-x',
        code: 'palochka',
        type: 'item',
        name: 'Палка',
        description: 'Копалка',
        spaceId: 2,
        createdAt: new Date().toISOString(),
      },
    ]);
    const palka = rev.rules.find((r) => r.code === 'palochka');
    expect(palka).toBeDefined();
    const palkaId = palka!.id;

    // Персонаж на ревизии 12, а правило в 13 — переведём на 13 через прямое обновление версии,
    // чтобы updateCustomRule резолвил тип из ревизии 13. Черновик редактора несёт кастом-записи
    // (копия версии), поэтому bump сохраняет customRules.
    await updateCharacter(created.character.id, {
      version: { ...version13, rulesRevision: 13, customRules: detail.version.customRules },
    });

    const updated = await updateCustomRule(created.character.id, entryId, {
      status: 'deprecated',
      replacedWithRuleId: palkaId,
    });

    const item = updated.version.inventory.find((entry) => entry.ruleId === palkaId);
    expect(item).toBeDefined();
    expect(item?.quantity).toBe(1);
    expect(item?.equipped).toBe(false);
  });
});

describe('mockCharacterApi: owner notes', () => {
  it('владелец читает и пишет заметки вне версии листа', async () => {
    const created = await createCharacter(freshCreateData());
    expect(created.ownerNotes).toBeNull();
    expect(created.version).not.toHaveProperty('ownerNotes');

    const saved = await updateOwnerNotes(created.character.id, '  секрет  ');
    expect(saved.ownerNotes).toBe('секрет');
    expect((await fetchCharacter(created.character.id)).ownerNotes).toBe('секрет');
  });

  it('чужой пользователь не получает и не пишет заметки', async () => {
    await updateOwnerNotes(1, 'только Иван');
    await mockLogin('admin', 'test');
    const asAdmin = await fetchCharacter(1);
    expect(asAdmin.character.ownerId).not.toBe(2);
    expect(asAdmin.ownerNotes).toBeUndefined();
    await expect(updateOwnerNotes(1, 'взлом')).rejects.toThrow('Forbidden');
    await mockLogout();
    expect((await fetchCharacter(1)).ownerNotes).toBe('только Иван');
  });
});
