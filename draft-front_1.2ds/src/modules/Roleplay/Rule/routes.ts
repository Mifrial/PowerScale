import type { RouteLocationNormalizedLoaded, RouteRecordRaw } from 'vue-router'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import { useRuleStore } from './Store/rules'

export const ruleCtxChildren: RouteRecordRaw[] = [
  {
    path: 'rules/new',
    name: 'RuleNew',
    component: () => import('./Page/RuleEditPage.vue'),
    meta: { title: 'Создание правила', crumb: () => [{ title: 'Создание правила' }] },
  },
  {
    path: 'rules/:ruleId',
    meta: { crumb: ruleDetailCrumb },
    children: [
      {
        path: '',
        name: 'RuleDetail',
        component: () => import('./Page/RuleDetailPage.vue'),
      },
      {
        path: 'edit',
        name: 'RuleEdit',
        component: () => import('./Page/RuleEditPage.vue'),
        meta: { title: 'Редактирование правила', crumb: ruleEditCrumb },
      },
    ],
  },
]

function ruleName(to: RouteLocationNormalizedLoaded): string {
  const ruleId = String(to.params.ruleId)
  const revision = useSpaceRevisionStore()
  return revision.effectiveRules.find(r => r.id === ruleId)?.name ?? useRuleStore().currentRule?.name ?? ''
}

function ruleDetailCrumb(to: RouteLocationNormalizedLoaded) {
  return [{
    title: ruleName(to) || 'Правило',
    to: `/space/${to.params.code}/${to.params.ctx}/rules/${to.params.ruleId}`,
  }]
}

function ruleEditCrumb() {
  return [{ title: 'Редактирование' }]
}
