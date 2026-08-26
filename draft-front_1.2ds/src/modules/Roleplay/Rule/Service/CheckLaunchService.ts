import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import {
  CHECK_HIT_CODE,
  CHECK_INITIATIVE_CODE,
  CHECK_INJURY_CODE,
  CHECK_SIMPLE_CODE,
} from '@/modules/Roleplay/Rule/Constant/Check/CHECK_CODES';
import type { CheckResolutionService } from '@/modules/Roleplay/Rule/Service/CheckResolutionService';

export class CheckLaunchService {
  constructor(private readonly checkResolution: CheckResolutionService) {}

  /** Проверки, которые можно запустить из диалога (не инициатива / увечье / удар). */
  isLaunchableCheck(rule: Rule): boolean {
    if (rule.type !== 'check') return false;
    if (rule.code === CHECK_INITIATIVE_CODE || rule.code === CHECK_INJURY_CODE || rule.code === CHECK_HIT_CODE) {
      return false;
    }

    return this.checkResolution.asCheckSpec(rule) !== null;
  }

  launchableChecks(rules: Rule[]): Rule[] {
    return rules
      .filter((rule) => this.isLaunchableCheck(rule))
      .sort((left, right) => {
        if (left.code === CHECK_SIMPLE_CODE) return -1;
        if (right.code === CHECK_SIMPLE_CODE) return 1;

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
