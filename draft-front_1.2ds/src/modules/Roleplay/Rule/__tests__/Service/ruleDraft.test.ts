import { describe, it, expect } from 'vitest';
import { ruleDraftService } from '@/modules/Roleplay/Rule/Service/Instance/ruleDraftService';
import type { CreateDraftParams } from '@/modules/Roleplay/Rule/Dto/CreateDraftParams';

const baseParams = (overrides: Partial<CreateDraftParams> = {}): CreateDraftParams => ({
  isEdit: false,
  id: '',
  type: 'simple',
  name: 'Правило',
  code: '',
  loadedCode: '',
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  ...overrides,
});

describe('RuleDraftService.createDraft', () => {
  it('новой записи присваивает draft-id и генерирует code через slugify(name) при пустом code', () => {
    const draft = ruleDraftService.createDraft(baseParams({ name: 'Боевой Топор' }));
    expect(draft.id.startsWith('draft-')).toBe(true);
    expect(draft.code).toBe('boevoy-topor');
  });

  it('использует code.trim(), если code задан', () => {
    const draft = ruleDraftService.createDraft(baseParams({ code: '  sword  ' }));
    expect(draft.code).toBe('sword');
  });

  it('isEdit сохраняет id и берёт code из loadedCode', () => {
    const draft = ruleDraftService.createDraft(
      baseParams({ isEdit: true, id: 'r-42', code: 'new-code', loadedCode: 'old-code' }),
    );
    expect(draft.id).toBe('r-42');
    expect(draft.code).toBe('old-code');
  });

  it('прокидывает keywordIds/mechanicId и создаёт ISO createdAt', () => {
    const draft = ruleDraftService.createDraft(baseParams({ keywordIds: [7, 9], mechanicId: 3 }));
    expect(draft.keywordIds).toEqual([7, 9]);
    expect(draft.mechanicId).toBe(3);
    expect(new Date(draft.createdAt).toISOString()).toBe(draft.createdAt);
  });

  it('spec null сводится к undefined, заданный spec сохраняется как есть', () => {
    const spec = { type: 'simple' } as unknown as CreateDraftParams['spec'];
    expect(ruleDraftService.createDraft(baseParams({ spec: null })).spec).toBeUndefined();
    expect(ruleDraftService.createDraft(baseParams({ spec })).spec).toBe(spec);
  });
});
