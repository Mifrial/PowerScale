import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  COMMUNICATION_CHECK_DOMAIN_REF,
  CHECK_COMMUNICATION_CODE,
  CHECK_SIMPLE_CODE,
} from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';

/** Код правила «Бросок» (тот же, что Game ROLL_RULE_CODE) — без импорта Game. */
const ROLL_RULE_CODE = 'roll';

export function asCheckSpec(rule: Rule | undefined): CheckSpec | null {
  if (!rule || rule.type !== 'check') return null;
  const spec = rule.spec;
  if (!spec || typeof spec !== 'object' || !('type' in spec) || spec.type !== 'check') return null;

  return spec;
}

/** Код запуска и предки (корень последний). Цикл обрывается. */
export function checkAncestorCodes(checkCode: string, rules: Rule[]): string[] {
  const byCode = new Map(rules.map((rule) => [rule.code, rule]));
  const chain: string[] = [];
  const seen = new Set<string>();
  let current: string | undefined = checkCode;

  while (current && !seen.has(current)) {
    seen.add(current);
    chain.push(current);
    const spec = asCheckSpec(byCode.get(current));
    current = spec?.parent_check_code ?? undefined;
  }

  return chain;
}

export function checkMatchesGrant(launchCheckCode: string, grantCheckCode: string, rules: Rule[]): boolean {
  return checkAncestorCodes(launchCheckCode, rules).includes(grantCheckCode);
}

export function resolveCheckCharacteristicCode(
  checkCode: string,
  rules: Rule[],
  override?: string | null,
): string | null {
  if (override) return override;
  const byCode = new Map(rules.map((rule) => [rule.code, rule]));
  for (const code of checkAncestorCodes(checkCode, rules)) {
    const characteristic = asCheckSpec(byCode.get(code))?.characteristic_code;
    if (characteristic) return characteristic;
  }

  return null;
}

export function resolveCheckAttachedRuleCodes(checkCode: string, rules: Rule[]): string[] {
  const byCode = new Map(rules.map((rule) => [rule.code, rule]));
  for (const code of checkAncestorCodes(checkCode, rules)) {
    const spec = asCheckSpec(byCode.get(code));
    if (spec && spec.attached_rule_codes !== undefined && spec.attached_rule_codes !== null) {
      return spec.attached_rule_codes;
    }
  }

  return [];
}

/** Правило можно повесить на проверку: есть механика, это не сама проверка и не «Бросок». */
export function isCheckAttachableRule(rule: Rule): boolean {
  if (rule.type === 'check' || rule.code === ROLL_RULE_CODE) return false;

  return rule.mechanicId != null;
}

/** Код проверки по характеристике (`check-strength`) или корень простой проверки. */
export function resolveCheckCodeForCharacteristic(
  characteristicCode: string | null | undefined,
  rules: Rule[],
): string {
  if (characteristicCode) {
    const checkCode = `check-${characteristicCode}`;
    if (rules.some((rule) => rule.type === 'check' && rule.code === checkCode)) return checkCode;
  }

  return CHECK_SIMPLE_CODE;
}

export function resolveCheckCodeFromRuleId(ruleId: string | null | undefined, rules: Rule[]): string {
  if (!ruleId) return CHECK_SIMPLE_CODE;
  const rule = rules.find((candidate) => candidate.id === ruleId);
  if (!rule) return CHECK_SIMPLE_CODE;
  if (rule.type === 'check') return rule.code;
  if (rule.type === 'characteristic') return resolveCheckCodeForCharacteristic(rule.code, rules);

  return CHECK_SIMPLE_CODE;
}

export function resolveCheckEfficiency(checkCode: string, rules: Rule[], fallback: number): number {
  const byCode = new Map(rules.map((rule) => [rule.code, rule]));
  for (const code of checkAncestorCodes(checkCode, rules)) {
    const value = asCheckSpec(byCode.get(code))?.default_efficiency;
    if (value != null) return value;
  }
  const roll = rules.find((rule) => rule.code === ROLL_RULE_CODE);
  const payload = roll?.mechanic_payload;
  if (payload?.type === 'roll' && payload.data.efficiency != null) {
    return payload.data.efficiency;
  }

  return fallback;
}

export function communicationCheckOptions(rules: Rule[]): { code: string; name: string }[] {
  return rules
    .filter((rule) => {
      const spec = asCheckSpec(rule);

      return spec?.parent_check_code === CHECK_COMMUNICATION_CODE;
    })
    .map((rule) => ({ code: rule.code, name: rule.name }));
}

export function isCommunicationCheckDomain(domainRef: string): boolean {
  return domainRef === COMMUNICATION_CHECK_DOMAIN_REF;
}
