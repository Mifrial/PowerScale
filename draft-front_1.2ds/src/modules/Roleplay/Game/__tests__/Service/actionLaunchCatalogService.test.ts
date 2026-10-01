import { describe, expect, it } from 'vitest';
import type { CurrentSpeed } from '@/modules/Roleplay/Game/Dto/CurrentSpeed';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { actionLaunchCatalogService } from '@/modules/Roleplay/Game/Service/Instance/actionLaunchCatalogService';

const stopped: CurrentSpeed = {
  horizontal: { stepsPerActionPoint: 0, direction: null },
  vertical: { stepsPerActionPoint: 0, direction: null },
};

function actionRule(code: string, requirements: Requirement[]): Rule {
  return {
    mechanics: [],
    id: null,
    code,
    type: 'ability',
    name: code,
    description: '',
    spaceId: 1,
    createdAt: 0,
    spec: {
      type: 'action',
      zones: { any: { kind: 'automatic' } },
      requirements: [{ level: 1, requirements }],
      grants: [],
      parent_ability_code: null,
      action_components: [],
    },
  };
}

describe('ActionLaunchCatalogService', () => {
  it('скрывает действие, пока скорость не выполнена, и оставляет неизвестное требование', () => {
    const speedRule = actionRule('dash', [
      {
        type: 'current_speed',
        axis: 'horizontal',
        direction: 'front',
        min_steps_per_action_point: 1,
      },
    ]);
    const unknownRule = actionRule('wait-gate', [{ type: 'future_gate' } as unknown as Requirement]);
    const hidden = actionLaunchCatalogService.listOptions({
      rules: [speedRule, unknownRule],
      currentSpeed: stopped,
      actorVersion: null,
      woundOverlay: null,
    });

    expect(hidden.map((action) => action.code)).toEqual(['wait-gate']);

    const shown = actionLaunchCatalogService.listOptions({
      rules: [speedRule],
      currentSpeed: { ...stopped, horizontal: { stepsPerActionPoint: 1, direction: 'front' } },
      actorVersion: null,
      woundOverlay: null,
    });

    expect(shown.map((action) => action.code)).toEqual(['dash']);
  });
});
