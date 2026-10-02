import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { CHECK_INITIATIVE_CODE, CHECK_INJURY_CODE } from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import type { CheckResolutionService } from '@/modules/Roleplay/Rule/Service/CheckResolutionService';

export class CheckLaunchService {
  constructor(private readonly checkResolution: CheckResolutionService) {}

  /** Проверки, которые можно запустить из диалога (не инициатива / увечье / удар). */
  isLaunchableCheck(rule: Rule, rules: Rule[]): boolean {
    if (rule.type !== 'check') return false;
    const spec = this.checkResolution.asCheckSpec(rule);
    if (!spec || spec.dialog_launch === false) return false;
    const hitCheckCode = this.checkResolution.firstCheckCode(rules, 'hit_check');
    if (
      rule.code === CHECK_INITIATIVE_CODE ||
      rule.code === CHECK_INJURY_CODE ||
      (hitCheckCode !== '' && rule.code === hitCheckCode)
    ) {
      return false;
    }

    return true;
  }

  launchableChecks(rules: Rule[]): Rule[] {
    return rules
      .filter((rule) => this.isLaunchableCheck(rule, rules))
      .sort((left, right) => {
        const leftRoot = this.checkResolution.asCheckSpec(left)?.ordinary_root === true;
        const rightRoot = this.checkResolution.asCheckSpec(right)?.ordinary_root === true;
        if (leftRoot && !rightRoot) return -1;
        if (rightRoot && !leftRoot) return 1;

        return left.name.localeCompare(right.name, 'ru');
      });
  }

  checkAllowsPairwise(rule: Rule): boolean {
    const spec = this.checkResolution.asCheckSpec(rule);
    if (!spec) return false;

    return spec.allowed_modes === 'joint' || spec.allowed_modes === 'both';
  }

  checkAllowsSolo(rule: Rule): boolean {
    const spec = this.checkResolution.asCheckSpec(rule);
    if (!spec) return false;

    return spec.allowed_modes === 'solo' || spec.allowed_modes === 'both';
  }

  /** «Сила воли против Истощения» — из характеристики пула и состояния сложности. */
  checkVersusLabel(rule: Rule, rules: Rule[]): string | null {
    const spec = this.checkResolution.asCheckSpec(rule);
    if (!spec || spec.difficulty_input.kind !== 'from_state') return null;
    const stateCode = spec.difficulty_input.state_code;
    const characteristic =
      spec.characteristic_code === null || spec.characteristic_code === undefined
        ? null
        : (rules.find((candidate) => candidate.code === spec.characteristic_code)?.name ?? spec.characteristic_code);
    const state = rules.find((candidate) => candidate.code === stateCode)?.name ?? stateCode;
    if (!characteristic) return `против «${state}»`;

    return `${characteristic} против «${state}»`;
  }
}
