import type { IRuleApi } from '@/modules/Roleplay/Rule/Interface/IRuleApi';
import * as mock from '@/modules/Roleplay/Rule/Mock/mockRules';

export const mockRuleApi: IRuleApi = {
  getRules: mock.fetchRules,
  getRule: mock.fetchRule,
  getRuleVersions: mock.fetchRuleVersions,
  createRule: mock.createRule,
  updateRule: mock.updateRule,
  deleteRule: mock.deleteRule,
};
