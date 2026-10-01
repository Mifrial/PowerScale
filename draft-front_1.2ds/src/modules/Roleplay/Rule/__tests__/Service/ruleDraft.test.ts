import { describe, it, expect } from 'vitest';
import { ruleDraftService } from '@/modules/Roleplay/Rule/Service/Instance/ruleDraftService';
import type { CreateDraftParams } from '@/modules/Roleplay/Rule/Dto/CreateDraftParams';

const baseParams = (overrides: Partial<CreateDraftParams> = {}): CreateDraftParams => ({
  isEdit: false,
  id: null,
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
  it('новой записи id = null и генерирует code через slugify(name) при пустом code', () => {
    const draft = ruleDraftService.createDraft(baseParams({ name: 'Боевой Топор' }));
    expect(draft.id).toBeNull();
    expect(draft.code).toBe('boevoy-topor');
  });

  it('использует code.trim(), если code задан', () => {
    const draft = ruleDraftService.createDraft(baseParams({ code: '  sword  ' }));
    expect(draft.code).toBe('sword');
  });

  it('isEdit сохраняет id и берёт code из loadedCode', () => {
    const draft = ruleDraftService.createDraft(
      baseParams({ isEdit: true, id: 42, code: 'new-code', loadedCode: 'old-code' }),
    );
    expect(draft.id).toBe(42);
    expect(draft.code).toBe('old-code');
  });

  it('прокидывает keywordIds/mechanicId и unix createdAt', () => {
    const draft = ruleDraftService.createDraft(baseParams({ keywordIds: [7, 9], mechanicId: 3 }));
    expect(draft.keywordIds).toEqual([7, 9]);
    expect(draft.mechanicId).toBe(3);
    expect(typeof draft.createdAt).toBe('number');
    expect(draft.createdAt).toBeGreaterThan(1_700_000_000);
  });

  it('spec null сводится к undefined, заданный spec сохраняется как есть', () => {
    const spec = { type: 'simple' } as unknown as CreateDraftParams['spec'];
    expect(ruleDraftService.createDraft(baseParams({ spec: null })).spec).toBeUndefined();
    expect(ruleDraftService.createDraft(baseParams({ spec })).spec).toBe(spec);
  });

  it('прокидывает contentStatus, иначе needs_work', () => {
    expect(ruleDraftService.createDraft(baseParams()).contentStatus).toBe('needs_work');
    expect(ruleDraftService.createDraft(baseParams({ contentStatus: 'ready' })).contentStatus).toBe('ready');
  });

  it('тот же mechanicId сохраняет payload отдельной копией', () => {
    const mechanicPayload = {
      type: 'roll' as const,
      data: { diceCount: 3, dieFaces: 6, efficiency: 3, adv: 0, sub_mechanics: ['advantage_disadvantage'] },
      futureField: { nested: [1] },
    } as unknown as CreateDraftParams['mechanicPayload'];
    const draft = ruleDraftService.createDraft(baseParams({
      isEdit: true,
      mechanicId: 5,
      loadedMechanicId: 5,
      mechanicPayload,
    }));
    expect(draft.mechanicPayload).toEqual(mechanicPayload);
    expect(draft.mechanicPayload).not.toBe(mechanicPayload);
  });

  it('смена и очистка mechanicId сбрасывают payload в null', () => {
    const mechanicPayload = { type: 'injury_efficiency' as const, delta: -1 };
    expect(ruleDraftService.createDraft(baseParams({
      mechanicId: 6,
      loadedMechanicId: 5,
      mechanicPayload,
    })).mechanicPayload).toBeNull();
    expect(ruleDraftService.createDraft(baseParams({
      mechanicId: null,
      loadedMechanicId: 5,
      mechanicPayload,
    })).mechanicPayload).toBeNull();
  });

  it('без смены id сохраняет null и undefined payload', () => {
    expect(ruleDraftService.createDraft(baseParams({
      mechanicId: 5,
      loadedMechanicId: 5,
      mechanicPayload: null,
    })).mechanicPayload).toBeNull();
    expect(ruleDraftService.createDraft(baseParams({
      mechanicId: null,
      loadedMechanicId: null,
    })).mechanicPayload).toBeUndefined();
  });

  it('прокидывает contentNote, иначе пустую строку', () => {
    expect(ruleDraftService.createDraft(baseParams()).contentNote).toBe('');
    expect(ruleDraftService.createDraft(baseParams({ contentNote: 'ждёт удар' })).contentNote).toBe('ждёт удар');
  });
});
