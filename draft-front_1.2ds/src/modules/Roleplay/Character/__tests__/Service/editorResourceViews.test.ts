import { describe, expect, it } from 'vitest';
import { editorResourceViewsService } from '@/modules/Roleplay/Character/Service/Instance/editorResourceViewsService';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const rule = (id: string, code: string, name: string): Rule => ({
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
  ruleId: 'resource-action-points',
  current: { base: 7, size: 0 },
  base: { base: 5, size: 0 },
  bonuses: [
    { sourceRuleId: 'ability-speed', sourceLabel: null, delta: 1 },
    { sourceRuleId: null, sourceLabel: 'временный эффект', delta: -1 },
  ],
};

describe('EditorResourceViewsService', () => {
  it('считает итоговый лимит и разрешает источники модификаторов', () => {
    const view = editorResourceViewsService.build(
      [resource],
      [
        rule('resource-action-points', 'action-points', 'Очки действий'),
        rule('ability-speed', 'speed', 'Тренировка скорости'),
      ],
    )[0];

    expect(view).toMatchObject({
      ruleId: 'resource-action-points',
      name: 'Очки действий',
      current: { base: 7, size: 0 },
      base: { base: 5, size: 0 },
      max: { base: 5, size: 0 },
    });
    expect(view.bonuses).toEqual([
      { sourceRuleId: 'ability-speed', source: 'Тренировка скорости', delta: 1 },
      { sourceRuleId: null, source: 'временный эффект', delta: -1 },
    ]);
  });

  it('не даёт лимиту стать отрицательным', () => {
    const view = editorResourceViewsService.build(
      [{ ...resource, bonuses: [{ sourceRuleId: null, sourceLabel: 'штраф', delta: -10 }] }],
      [],
    )[0];

    expect(view.max).toEqual({ base: 0, size: 0 });
  });
});
