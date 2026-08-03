import type { RouteLocationNormalizedLoaded, RouteRecordRaw } from 'vue-router'
import { useSpaceRevisionStore } from '@/modules/Roleplay/Space/Store/spaceRevision'
import { useRuleStore } from '@/modules/Roleplay/Rule/Store/rules'

export const ruleCtxChildren: RouteRecordRaw[] = [
  {
    path: 'rules/new',
    name: 'RuleNew',
    component: () => import('@/modules/Roleplay/Rule/Page/RuleEditPage.vue'),
    meta: { title: 'Создание правила', crumb: () => [{ title: 'Создание правила' }] },
  },
  {
    path: 'rules/:ruleId',
    meta: { crumb: ruleDetailCrumb },
    children: [
      {
        path: '',
        name: 'RuleDetail',
        component: () => import('@/modules/Roleplay/Rule/Page/RuleDetailPage.vue'),
      },
      {
        path: 'edit',
        name: 'RuleEdit',
        component: () => import('@/modules/Roleplay/Rule/Page/RuleEditPage.vue'),
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

export const adminChildren: RouteRecordRaw[] = [
  {
    path: 'keywords',
    meta: { crumb: () => [{ title: 'Признаки', to: '/admin/keywords' }], requiresAny: ['keyword.view'] },
    children: [
      {
        path: '',
        name: 'Keywords',
        component: () => import('@/modules/Roleplay/Rule/Page/KeywordsListPage.vue'),
      },
      {
        path: 'new',
        name: 'KeywordNew',
        component: () => import('@/modules/Roleplay/Rule/Page/KeywordEditPage.vue'),
        meta: { title: 'Создание тега', crumb: () => [{ title: 'Создание тега' }], requiresAny: ['keyword.create'] },
      },
      {
        path: ':id/edit',
        name: 'KeywordEdit',
        component: () => import('@/modules/Roleplay/Rule/Page/KeywordEditPage.vue'),
        meta: { title: 'Редактирование тега', crumb: () => [{ title: 'Редактирование тега' }], requiresAny: ['keyword.edit'] },
      },
    ],
  },
]
