import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  DT_BLUNT_KO_CODE,
  DT_CUTTING_AS_WOUNDS_CODE,
  DT_EXHAUSTION_TO_STUN_CODE,
  DT_EXHAUSTION_TO_WOUND_CODE,
  DT_EXHAUSTION_TO_WOUND_X2_CODE,
  DT_INJURY_EFFICIENCY_CODE,
  DT_INJURY_EXTRA_DICE_SR_CODE,
  DT_PAY_SR_VS_RELIABILITY_CODE,
} from '@/modules/Roleplay/Rule/Constant/Damage/DAMAGE_TYPE_HOOKS';
import { INJURY_PROCEDURE_RULE_CODE } from '@/modules/Roleplay/Rule/Constant/Combat/INJURY_PROCEDURE';

function hookRule(
  id: string,
  code: string,
  name: string,
  description: string,
  mechanicId: number,
  payload?: Rule['mechanic_payload'],
): Rule {
  return {
    id,
    code,
    type: 'simple',
    name,
    description,
    spaceId: 1,
    keywordIds: [],
    mechanicId,
    mechanic_payload: payload ?? null,
    createdAt: '2026-08-23T12:00:00Z',
  };
}

/** Карточки хуков типов урона + процедура увечья. */
export const mockDamageTypeHooks: Rule[] = [
  hookRule(
    'rule-dt-injury-extra-dice-sr',
    DT_INJURY_EXTRA_DICE_SR_CODE,
    'Сложность увечья от РУ',
    'На проверке увечья от колющего: +⌊РУ атаки / 2⌋ к сложности (РУ попадания).',
    8,
  ),
  hookRule(
    'rule-dt-injury-efficiency',
    DT_INJURY_EFFICIENCY_CODE,
    'Помеха на увечье',
    'Проверка на увечье от рубящего идёт с помехой (дельта эффективности −1).',
    9,
    { type: 'injury_efficiency', delta: -1 },
  ),
  hookRule(
    'rule-dt-pay-sr-vs-reliability',
    DT_PAY_SR_VS_RELIABILITY_CODE,
    'РУ против надёжности',
    'Набранные РУ атаки игнорируют всё с надёжностью ≤ РУ. РУ не тратятся, выбора X нет.',
    10,
  ),
  hookRule(
    'rule-dt-exhaustion-to-stun',
    DT_EXHAUSTION_TO_STUN_CODE,
    'Истощение → оглушение',
    'Истощение от дробящего накладывает оглушение силой [истощение].',
    11,
  ),
  hookRule(
    'rule-dt-exhaustion-to-wound',
    DT_EXHAUSTION_TO_WOUND_CODE,
    'Истощение → рана',
    'Истощение от колющего накладывает раны силой [истощение].',
    12,
    { type: 'exhaustion_wound', multiplier: 1 },
  ),
  hookRule(
    'rule-dt-exhaustion-to-wound-x2',
    DT_EXHAUSTION_TO_WOUND_X2_CODE,
    'Истощение → рана ×2',
    'Истощение от рубящего накладывает раны силой [истощение × 2].',
    12,
    { type: 'exhaustion_wound', multiplier: 2 },
  ),
  hookRule(
    'rule-dt-cutting-as-wounds',
    DT_CUTTING_AS_WOUNDS_CODE,
    'Режущий как раны',
    'Не HP: размер урона привести к размеру цели → рана этой силы (не от Стойкости).',
    13,
  ),
  hookRule(
    'rule-dt-blunt-ko',
    DT_BLUNT_KO_CODE,
    'Потеря сознания (дробящий)',
    'При РУ атаки ≥ 6 или дробящих повреждениях ≥ [Стойкость] — потеря сознания.',
    14,
  ),
  hookRule(
    'rule-injury-procedure',
    INJURY_PROCEDURE_RULE_CODE,
    'Увечье',
    'Процедура проверки на увечье. Срез ревизии указывает mechanic injury@version.',
    15,
  ),
];
