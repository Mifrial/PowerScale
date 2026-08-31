import { describe, expect, it } from 'vitest';
import { editorResourceViewsService } from '@/modules/Roleplay/Character/Service/Instance/editorResourceViewsService';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const rule = (id: number | null, code: string, name: string): Rule => ({
  id,
  code,
  type: 'resource',
  name,
  description: '',
  spaceId: 1,
  keywordIds: [],
  mechanicId: null,
  createdAt: '2026-01-01T00:00:00Z',
});

const resource: ResourceValue = {
  ruleCode: 'action-points',
  current: { base: 7, size: 0 },
  base: { base: 5, size: 0 },
  bonuses: [
    { sourceRuleCode: 'speed', sourceLabel: null, delta: 1 },
    { sourceRuleCode: null, sourceLabel: 'временный эффект', delta: -1 },
  ],
};

describe('EditorResourceViewsService', () => {
  it('считает итоговый лимит и разрешает источники модификаторов', () => {
    const view = editorResourceViewsService.build(
      [resource],
      [rule(null, 'action-points', 'Очки действий'), rule(null, 'speed', 'Тренировка скорости')],
    )[0];

    expect(view).toMatchObject({
      ruleCode: 'action-points',
      name: 'Очки действий',
      current: { base: 7, size: 0 },
      base: { base: 5, size: 0 },
      max: { base: 5, size: 0 },
    });
    expect(view.bonuses).toEqual([
      { sourceRuleCode: 'speed', source: 'Тренировка скорости', delta: 1 },
      { sourceRuleCode: null, source: 'временный эффект', delta: -1 },
    ]);
  });

  it('не даёт лимиту стать отрицательным', () => {
    const view = editorResourceViewsService.build(
      [{ ...resource, bonuses: [{ sourceRuleCode: null, sourceLabel: 'штраф', delta: -10 }] }],
      [],
    )[0];

    expect(view.max).toEqual({ base: 0, size: 0 });
  });
});
