import { RuleRevisionResolverService } from '@/modules/Roleplay/Rule/Service/RuleRevisionResolverService';
import { getRevisionRulesFetcher } from '@/modules/Roleplay/Rule/init';
import { useRuleStore } from '@/modules/Roleplay/Rule/Store/rules';

export const ruleRevisionResolverService = new RuleRevisionResolverService(getRevisionRulesFetcher, (ruleId, signal) =>
  useRuleStore().fetchRule(ruleId, signal),
);
