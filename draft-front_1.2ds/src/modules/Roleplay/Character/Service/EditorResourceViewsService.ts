import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { EditorResourceView } from '@/modules/Roleplay/Character/Dto/Editor/EditorResourceView';

export class EditorResourceViewsService {
  build(resources: ResourceValue[], rules: Rule[]): EditorResourceView[] {
    const rulesById = new Map(rules.map((rule) => [rule.id, rule]));

    return resources.map((resource) => {
      const max = {
        base: Math.max(0, resource.base.base + resource.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0)),
        size: resource.base.size,
      };

      return {
        ruleId: resource.ruleId,
        name: rulesById.get(resource.ruleId)?.name ?? resource.ruleId,
        current: resource.current,
        base: resource.base,
        max,
        bonuses: resource.bonuses.map((bonus) => ({
          delta: bonus.delta,
          source:
            rulesById.get(bonus.sourceRuleId ?? '')?.name ?? bonus.sourceLabel ?? bonus.sourceRuleId ?? 'источник',
          sourceRuleId: bonus.sourceRuleId,
        })),
      };
    });
  }
}
