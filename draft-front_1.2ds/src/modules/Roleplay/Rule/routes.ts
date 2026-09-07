import type { RouteLocationNormalizedLoaded, RouteRecordRaw } from 'vue-router';
import { useRuleStore } from '@/modules/Roleplay/Rule/Store/rules';

export const ruleCtxChildren: RouteRecordRaw[] = [
  {
    path: 'rules/new',
    name: 'RuleNew',
    component: () => import('@/modules/Roleplay/Rule/Page/RuleEditPage.vue'),
    meta: { title: 'Создание правила', crumb: () => [{ title: 'Создание правила' }] },
  },
  {
    path: 'rules/:ruleCode',
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
];

function ruleName(): string {
  // Имя правила берём из собственного Rule-стора (текущее открытое правило),
  // чтобы не тянуть ревизионный контекст Space в метаданные роутов.
  return useRuleStore().currentRule?.name ?? '';
}

function ruleDetailCrumb(to: RouteLocationNormalizedLoaded) {
  return [
    {
      title: ruleName() || 'Правило',
      to: `/space/${String(to.params.code)}/${String(to.params.ctx)}/rules/${encodeURIComponent(String(to.params.ruleCode))}`,
    },
  ];
}

function ruleEditCrumb() {
  return [{ title: 'Редактирование' }];
}
