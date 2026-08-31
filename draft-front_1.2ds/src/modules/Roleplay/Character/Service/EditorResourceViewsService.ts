import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ResourceValue } from '@/modules/Roleplay/Character/Dto/ResourceValue';
import type { EditorResourceView } from '@/modules/Roleplay/Character/Dto/Editor/EditorResourceView';

export class EditorResourceViewsService {
  build(resources: ResourceValue[], rules: Rule[]): EditorResourceView[] {
    const rulesByCode = new Map(rules.map((rule) => [rule.code, rule]));

    return resources.map((resource) => {
      const max = {
        base: Math.max(0, resource.base.base + resource.bonuses.reduce((sum, bonus) => sum + bonus.delta, 0)),
        size: resource.base.size,
      };

      return {
        ruleCode: resource.ruleCode,
        name: rulesByCode.get(resource.ruleCode)?.name ?? resource.ruleCode,
        current: resource.current,
        base: resource.base,
        max,
        bonuses: resource.bonuses.map((bonus) => ({
          delta: bonus.delta,
          source:
            rulesByCode.get(bonus.sourceRuleCode ?? '')?.name ??
            bonus.sourceLabel ??
            bonus.sourceRuleCode ??
            'источник',
          sourceRuleCode: bonus.sourceRuleCode,
        })),
      };
    });
  }
}
