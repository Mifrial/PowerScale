import { describe, expect, it, beforeEach } from 'vitest';
import { setActivePinia, createPinia } from 'pinia';
import { useSectionCatalogStore } from '@/modules/Roleplay/RuleSpace/Store/sectionCatalog';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';

const published: AbilitySection[] = [{ code: 'root', name: 'Корень', parentCode: null, sortOrder: 10 }];
const other: AbilitySection[] = [{ code: 'other', name: 'Другое', parentCode: null, sortOrder: 10 }];

describe('sectionCatalog store', () => {
  beforeEach(() => {
    localStorage.clear();
    setActivePinia(createPinia());
  });

  it('без draft не dirty относительно published', () => {
    const store = useSectionCatalogStore();
    expect(store.getDraftSections(1)).toBeNull();
    expect(store.isDirty(1, published)).toBe(false);
  });

  it('saveDraft делает каталог dirty и переживает новый стор', () => {
    const store = useSectionCatalogStore();
    store.saveDraft(1, [{ code: 'root', name: 'Новое', parentCode: null, sortOrder: 10 }]);
    expect(store.isDirty(1, published)).toBe(true);
    setActivePinia(createPinia());
    const restored = useSectionCatalogStore();
    expect(restored.getDraftSections(1)?.[0]?.name).toBe('Новое');
  });

  it('discardDraft возвращает published', () => {
    const store = useSectionCatalogStore();
    store.saveDraft(1, [{ code: 'root', name: 'Новое', parentCode: null, sortOrder: 10 }]);
    store.discardDraft(1);
    expect(store.isDirty(1, published)).toBe(false);
    expect(store.getDraftSections(1)).toBeNull();
  });

  it('пространства не пересекаются', () => {
    const store = useSectionCatalogStore();
    store.saveDraft(1, published);
    store.saveDraft(2, other);
    expect(store.getDraftSections(1)?.[0]?.code).toBe('root');
    expect(store.getDraftSections(2)?.[0]?.code).toBe('other');
  });
});
