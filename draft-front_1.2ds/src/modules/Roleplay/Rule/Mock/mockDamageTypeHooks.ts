import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { INJURY_PROCEDURE_RULE_CODE } from '@/modules/Roleplay/Rule/Constant/Combat/INJURY_PROCEDURE';

/** Процедура увечья. Хуки типов урона живут на самих типах. */
export const mockDamageTypeHooks: Rule[] = [
  {
    id: 9109,
    code: INJURY_PROCEDURE_RULE_CODE,
    type: 'simple',
    name: 'Увечье',
    description:
      'По каждому типу ⌊повреждения / Стойкость⌋ проверок (хуки типа). Остатки разных типов в одном ударе складываются: ещё ⌊сумма / Стойкость⌋ от Упадка сил. Один тип — как раньше max(повреждения, рана, истощение).',
    spaceId: 1,
    keywordIds: [],
    mechanics: [{ mechanicId: 15, mechanicPayload: null }],
    createdAt: 1787486400,
  },
];
