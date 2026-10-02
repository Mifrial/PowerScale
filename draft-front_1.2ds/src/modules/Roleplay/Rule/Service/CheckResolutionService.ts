import type { CharacteristicSpec } from '@/modules/Roleplay/Rule/Dto/CharacteristicSpec';
import type { CheckSpec } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  COMMUNICATION_CHECK_DOMAIN_REF,
  CHECK_COMMUNICATION_CODE,
} from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';

export class CheckResolutionService {
  asCheckSpec(rule: Rule | undefined): CheckSpec | null {
    if (!rule || rule.type !== 'check') return null;
    const spec = rule.spec;
    if (!spec || typeof spec !== 'object' || !('type' in spec) || spec.type !== 'check') return null;

    return spec;
  }

  /** Код запуска и предки (корень последний). Цикл обрывается. */
  checkAncestorCodes(checkCode: string, rules: Rule[]): string[] {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const chain: string[] = [];
    const seen = new Set<string>();
    let current: string | undefined = checkCode;

    while (current && !seen.has(current)) {
      seen.add(current);
      chain.push(current);
      const spec = this.asCheckSpec(byCode.get(current));
      current = spec?.parent_check_code ?? undefined;
    }

    return chain;
  }

  checkMatchesGrant(launchCheckCode: string, grantCheckCode: string, rules: Rule[]): boolean {
    return this.checkAncestorCodes(launchCheckCode, rules).includes(grantCheckCode);
  }

  resolveCheckCharacteristicCode(checkCode: string, rules: Rule[], override?: string | null): string | null {
    if (override) return override;
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    for (const code of this.checkAncestorCodes(checkCode, rules)) {
      const characteristic = this.asCheckSpec(byCode.get(code))?.characteristic_code;
      if (characteristic) return characteristic;
    }

    return null;
  }

  resolveCheckAttachedRuleCodes(checkCode: string, rules: Rule[]): string[] {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    for (const code of this.checkAncestorCodes(checkCode, rules)) {
      const spec = this.asCheckSpec(byCode.get(code));
      if (spec && spec.attached_rule_codes !== undefined && spec.attached_rule_codes !== null) {
        return spec.attached_rule_codes;
      }
    }

    return [];
  }

  /** Правило можно повесить на проверку: есть механика, это не сама проверка и не «Бросок». */
  isCheckAttachableRule(rule: Rule): boolean {
    if (rule.type === 'check' || rule.mechanics.some((row) => row.mechanicPayload?.type === 'roll')) return false;

    return rule.mechanics.length > 0;
  }

  /** Первая проверка с флагом, иначе пустая строка. */
  firstCheckCode(rules: Rule[], flag: 'ordinary_root' | 'unstable_check' | 'hit_check'): string {
    const found = rules.find((rule) => this.asCheckSpec(rule)?.[flag] === true);

    return found?.code ?? '';
  }

  /** Первая проверка с ordinary_root, иначе пустая строка. */
  ordinaryRootCode(rules: Rule[]): string {
    return this.firstCheckCode(rules, 'ordinary_root');
  }

  /** У проверки или предка включён флаг. */
  ancestorHasFlag(checkCode: string, rules: Rule[], flag: 'concentration_token' | 'willpower'): boolean {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));

    return this.checkAncestorCodes(checkCode, rules).some(
      (code) => this.asCheckSpec(byCode.get(code))?.[flag] === true,
    );
  }

  /** Код проверки по характеристике (`check-strength`) или корень простой проверки. */
  resolveCheckCodeForCharacteristic(characteristicCode: string | null | undefined, rules: Rule[]): string {
    if (characteristicCode) {
      const checkCode = `check-${characteristicCode}`;
      if (rules.some((rule) => rule.type === 'check' && rule.code === checkCode)) return checkCode;
    }

    return this.ordinaryRootCode(rules);
  }

  resolveCheckCodeFromRuleCode(ruleCode: string | null | undefined, rules: Rule[]): string {
    if (!ruleCode) return this.ordinaryRootCode(rules);
    const rule = rules.find((candidate) => candidate.code === ruleCode);
    if (!rule) return this.ordinaryRootCode(rules);
    if (rule.type === 'check') return rule.code;
    if (rule.type === 'characteristic') return this.resolveCheckCodeForCharacteristic(rule.code, rules);

    return this.ordinaryRootCode(rules);
  }

  resolveCheckEfficiency(checkCode: string, rules: Rule[], fallback: number): number {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    for (const code of this.checkAncestorCodes(checkCode, rules)) {
      const value = this.asCheckSpec(byCode.get(code))?.default_efficiency;
      if (value != null) return value;
    }
    const payload = rules
      .flatMap((rule) => rule.mechanics)
      .find((row) => row.mechanicPayload?.type === 'roll')?.mechanicPayload;
    if (payload?.type === 'roll' && payload.data.efficiency != null) {
      return payload.data.efficiency;
    }

    return fallback;
  }

  communicationCheckOptions(rules: Rule[]): { code: string; name: string }[] {
    return rules
      .filter((rule) => {
        const spec = this.asCheckSpec(rule);

        return spec?.parent_check_code === CHECK_COMMUNICATION_CODE;
      })
      .map((rule) => ({ code: rule.code, name: rule.name }));
  }

  isCommunicationCheckDomain(domainRef: string): boolean {
    return domainRef === COMMUNICATION_CHECK_DOMAIN_REF;
  }

  /**
   * Жетон концентрации: предок с concentration_token
   * или фактическая характеристика после override.
   */
  isConcentrationTokenCheck(checkCode: string, rules: Rule[], characteristicOverride?: string | null): boolean {
    if (this.ancestorHasFlag(checkCode, rules, 'concentration_token')) return true;
    const characteristic = this.resolveCheckCharacteristicCode(checkCode, rules, characteristicOverride);
    if (!characteristic) return false;
    const rule = rules.find((item) => item.code === characteristic && item.type === 'characteristic');
    const spec = rule?.spec as CharacteristicSpec | undefined;

    return spec?.concentration_token === true;
  }
}
