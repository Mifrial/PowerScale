import type { IRuleApi } from '../Interface/IRuleApi'
import * as mock from './mockRules'
import { fetchMechanics } from './mockMechanics'

export const mockRuleApi: IRuleApi = {
  getRules: mock.fetchRules,
  getRule: mock.fetchRule,
  getRuleVersions: mock.fetchRuleVersions,
  createRule: mock.createRule,
  updateRule: mock.updateRule,
  deleteRule: mock.deleteRule,
  getMechanics: fetchMechanics,
}
