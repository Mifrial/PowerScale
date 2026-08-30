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
      to: `/space/${to.params.code}/${to.params.ctx}/rules/${to.params.ruleId}`,
    },
  ];
}

function ruleEditCrumb() {
  return [{ title: 'Редактирование' }];
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
        meta: {
          title: 'Создание признака',
          crumb: () => [{ title: 'Создание признака' }],
          requiresAny: ['keyword.create'],
        },
      },
      {
        path: ':id/edit',
        name: 'KeywordEdit',
        component: () => import('@/modules/Roleplay/Rule/Page/KeywordEditPage.vue'),
        meta: {
          title: 'Редактирование признака',
          crumb: () => [{ title: 'Редактирование признака' }],
          requiresAny: ['keyword.edit'],
        },
      },
    ],
  },
];
