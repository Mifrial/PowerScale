import { RuleRevisionResolverService } from '@/modules/Roleplay/Rule/Service/RuleRevisionResolverService';
import { revisionRulesFetcherRegistry } from '@/modules/Roleplay/Rule/Service/Instance/revisionRulesFetcherRegistry';
import { useRuleStore } from '@/modules/Roleplay/Rule/Store/rules';

export const ruleRevisionResolverService = new RuleRevisionResolverService(
  () => revisionRulesFetcherRegistry.get(),
  (ruleId, signal) => useRuleStore().fetchRule(ruleId, signal),
);
