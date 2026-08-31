import { describe, it, expect, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { CHARACTER_DRAFT_STORAGE_KEY } from '@/modules/Roleplay/Character/Constant/characterDraftConfig';

const config: CharacterCreationConfig = { osTotal: 20, orTotal: 10, moneyBudget: 100 };

function makeBuild(name = 'Тест'): CharacterBuild {
  return {
    name,
    shortDescription: null,
    fullDescription: null,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    spaceId: 1,
    raceRuleCode: null,
    characteristicPurchases: [],
    abilities: [],
    resources: [],
    inventory: [],
    states: [],
    money: 0,
    ageYears: null,
    olTotal: 0,
  };
}

describe('characterDraft store', () => {
  beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('initDraft создаёт единый черновик; hasDraft и draftOf читают его', () => {
    const store = useCharacterDraftStore();
    expect(store.hasDraft('character:1')).toBe(false);

    store.initDraft('character:1', makeBuild('Оригинал'), config);
    expect(store.hasDraft('character:1')).toBe(true);
    expect(store.draftOf('character:1')?.build.name).toBe('Оригинал');
  });

  it('patchBuild иммутабельно обновляет build и помечает черновик грязным', () => {
    const store = useCharacterDraftStore();
    store.initDraft('character:1', makeBuild(), config);

    store.patchBuild('character:1', { raceRuleCode: 'human' });
    const entry = store.draftOf('character:1');
    expect(entry?.build.raceRuleCode).toBe('human');
    expect(entry?.dirty).toBe(true);
  });

  it('initDraft перезаписывает существующий черновик (единый черновик)', () => {
    const store = useCharacterDraftStore();
    store.initDraft('character:1', makeBuild('Первый'), config);
    store.initDraft('character:1', makeBuild('Второй'), config);

    expect(store.drafts.length).toBe(1);
    expect(store.draftOf('character:1')?.build.name).toBe('Второй');
  });

  it('patchBuild инвентаря помечает черновик грязным (экип с карточки)', () => {
    const store = useCharacterDraftStore();
    const withItem = makeBuild();
    withItem.inventory = [{ id: 1, ruleCode: 'rule-404', quantity: 1, equipped: false }];
    store.initDraft('character:1', withItem, config, {
      inventory: [{ id: 1, ruleCode: 'rule-404', quantity: 1, equipped: false }],
      money: 0,
    });
    expect(store.draftOf('character:1')?.dirty).toBe(false);

    store.patchBuild('character:1', {
      inventory: [{ id: 1, ruleCode: 'rule-404', quantity: 1, equipped: true }],
    });
    expect(store.draftOf('character:1')?.dirty).toBe(true);
    expect(store.draftOf('character:1')?.build.inventory[0]?.equipped).toBe(true);
  });

  it('markSaved снимает грязный флаг', () => {
    const store = useCharacterDraftStore();
    store.initDraft('character:1', makeBuild(), config);
    store.patchBuild('character:1', { name: 'Изменён' });
    store.markSaved('character:1');

    expect(store.draftOf('character:1')?.dirty).toBe(false);
  });

  it('discard и clearAll удаляют черновики', () => {
    const store = useCharacterDraftStore();
    store.initDraft('character:1', makeBuild(), config);
    store.initDraft(null, makeBuild(), config);

    store.discard('character:1');
    expect(store.hasDraft('character:1')).toBe(false);
    expect(store.hasDraft(null)).toBe(true);

    store.clearAll();
    expect(store.drafts.length).toBe(0);
  });

  it('сохраняет черновики в localStorage и восстанавливает после пересоздания стора', () => {
    const store = useCharacterDraftStore();
    store.initDraft('character:1', makeBuild('Из localStorage'), config);
    store.patchBuild('character:1', { name: 'Изменён' });

    const fresh = useCharacterDraftStore();
    expect(fresh.hasDraft('character:1')).toBe(true);
    expect(fresh.draftOf('character:1')?.build.name).toBe('Изменён');
    expect(localStorage.getItem(CHARACTER_DRAFT_STORAGE_KEY)).toContain('"draftKey":"character:1"');
  });

  describe('базовая линия «Инвентаря» (R2)', () => {
    it('initDraft сохраняет переданную базовую линию (edit: снапшот оригинала)', () => {
      const store = useCharacterDraftStore();
      const baseline = {
        inventory: [{ id: 1, ruleCode: 'rule-404', quantity: 1, equipped: true }],
        money: 50,
      };

      store.initDraft('character:1', makeBuild(), config, baseline);

      expect(store.draftOf('character:1')?.inventoryBaseline).toEqual(baseline);
    });

    it('ensureInventoryBaseline для нового персонажа донормирует деньги и фиксирует пустую линию', () => {
      const store = useCharacterDraftStore();
      store.initDraft(null, makeBuild(), config);

      store.ensureInventoryBaseline(null, 10000);

      const entry = store.draftOf(null);
      expect(entry?.build.money).toBe(10000);
      expect(entry?.inventoryBaseline).toEqual({ inventory: [], money: 10000 });
      expect(entry?.dirty).toBe(true);
    });

    it('ensureInventoryBaseline идемпотентна: повторный вызов не перезаписывает линию', () => {
      const store = useCharacterDraftStore();
      store.initDraft(null, makeBuild(), config);

      store.ensureInventoryBaseline(null, 10000);
      store.patchBuild(null, { money: 9500 });
      store.ensureInventoryBaseline(null, 10000);

      expect(store.draftOf(null)?.build.money).toBe(9500);
      expect(store.draftOf(null)?.inventoryBaseline).toEqual({ inventory: [], money: 10000 });
    });

    it('ensureInventoryBaseline для edit без линии фиксирует снапшот текущего build без донорма', () => {
      const store = useCharacterDraftStore();
      const build = makeBuild();
      build.money = 50;
      build.inventory = [{ id: 1, ruleCode: 'rule-404', quantity: 2, equipped: false }];
      store.initDraft('character:1', build, config);

      store.ensureInventoryBaseline('character:1', 0);

      const entry = store.draftOf('character:1');
      expect(entry?.build.money).toBe(50);
      expect(entry?.inventoryBaseline).toEqual({
        inventory: [{ id: 1, ruleCode: 'rule-404', quantity: 2, equipped: false }],
        money: 50,
      });
      expect(entry?.dirty).toBe(false);
    });

    it('ensureInventoryBaseline no-op, когда линия уже есть (edit)', () => {
      const store = useCharacterDraftStore();
      const baseline = {
        inventory: [{ id: 1, ruleCode: 'rule-404', quantity: 1, equipped: true }],
        money: 50,
      };
      store.initDraft('character:1', makeBuild(), config, baseline);

      store.ensureInventoryBaseline('character:1', 999);

      expect(store.draftOf('character:1')?.build.money).toBe(0);
      expect(store.draftOf('character:1')?.inventoryBaseline).toEqual(baseline);
    });

    it('patchInventoryBaseline обновляет линию и персистит', () => {
      const store = useCharacterDraftStore();
      store.initDraft('character:1', makeBuild(), config);

      store.patchInventoryBaseline('character:1', { inventory: [], money: 42 });

      expect(store.draftOf('character:1')?.inventoryBaseline).toEqual({ inventory: [], money: 42 });
    });
  });
});
