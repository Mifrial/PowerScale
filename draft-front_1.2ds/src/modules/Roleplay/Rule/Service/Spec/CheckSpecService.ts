import type { CheckOutcome } from '@/modules/Roleplay/Rule/Dto/Check/CheckSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/** Пустые исходы, добавление и разбор порогов проверки. */
export class CheckSpecService {
  createEmptyOutcome(): CheckOutcome {
    return { rating: -1, state_rule_code: null };
  }

  addOutcome(outcomes: CheckOutcome[] | undefined): CheckOutcome[] {
    return [...(outcomes ?? []), this.createEmptyOutcome()];
  }

  removeOutcome(outcomes: CheckOutcome[] | undefined, index: number): CheckOutcome[] {
    return (outcomes ?? []).filter((_, outcomeIndex) => outcomeIndex !== index);
  }

  getOutcomeKind(outcome: CheckOutcome | undefined): 'rating' | 'max' {
    return outcome?.rating != null ? 'rating' : 'max';
  }

  setOutcomeKind(outcomes: CheckOutcome[] | undefined, index: number, kind: string): CheckOutcome[] {
    return this.patchOutcome(outcomes, index, (outcome) => {
      if (kind === 'max') {
        return { ...outcome, rating: undefined, max_rating: outcome.max_rating ?? outcome.rating ?? -1 };
      }

      return { ...outcome, max_rating: undefined, rating: outcome.rating ?? outcome.max_rating ?? -1 };
    });
  }

  setOutcomeRating(outcomes: CheckOutcome[] | undefined, index: number, value: unknown): CheckOutcome[] {
    return this.patchOutcome(outcomes, index, (outcome) => ({
      ...outcome,
      rating: this.numberOrZero(value),
      max_rating: undefined,
    }));
  }

  setOutcomeMaxRating(outcomes: CheckOutcome[] | undefined, index: number, value: unknown): CheckOutcome[] {
    return this.patchOutcome(outcomes, index, (outcome) => ({
      ...outcome,
      rating: undefined,
      max_rating: this.numberOrZero(value),
    }));
  }

  setOutcomeState(outcomes: CheckOutcome[] | undefined, index: number, value: unknown): CheckOutcome[] {
    return this.patchOutcome(outcomes, index, (outcome) => ({
      ...outcome,
      state_rule_code: typeof value === 'string' && value !== '' ? value : null,
    }));
  }

  matchesOutcome(outcome: CheckOutcome, rating: number): boolean {
    if (outcome.rating != null) return outcome.rating === rating;

    return outcome.max_rating != null && rating <= outcome.max_rating;
  }

  matchOutcome(outcomes: CheckOutcome[] | undefined, rating: number): { index: number; outcome: CheckOutcome } | null {
    const list = outcomes ?? [];
    const index = list.findIndex((outcome) => this.matchesOutcome(outcome, rating));

    return index >= 0 ? { index, outcome: list[index] } : null;
  }

  outcomeStateCodes(outcomes: CheckOutcome[] | undefined): string[] {
    const codes = (outcomes ?? [])
      .map((outcome) => outcome.state_rule_code)
      .filter((code): code is string => code != null && code !== '');

    return [...new Set(codes)];
  }

  outcomeRank(outcomes: CheckOutcome[] | undefined, index: number): number {
    return (outcomes ?? [])
      .slice(0, index + 1)
      .filter((outcome) => outcome.state_rule_code != null && outcome.state_rule_code !== '').length;
  }

  outcomeRows(outcomes: CheckOutcome[] | undefined, rules: Rule[]): { condition: string; stateName: string }[] {
    return (outcomes ?? []).map((outcome) => ({
      condition: this.outcomeCondition(outcome),
      stateName: this.stateName(outcome.state_rule_code, rules),
    }));
  }

  private patchOutcome(
    outcomes: CheckOutcome[] | undefined,
    index: number,
    patcher: (outcome: CheckOutcome) => CheckOutcome,
  ): CheckOutcome[] {
    return (outcomes ?? []).map((outcome, outcomeIndex) => (outcomeIndex === index ? patcher(outcome) : outcome));
  }

  private numberOrZero(value: unknown): number {
    const number = Number(value);

    return Number.isFinite(number) ? number : 0;
  }

  private outcomeCondition(outcome: CheckOutcome): string {
    if (outcome.rating != null) return `РУ = ${outcome.rating}`;
    if (outcome.max_rating != null) return `РУ ≤ ${outcome.max_rating}`;

    return 'РУ —';
  }

  private stateName(code: string | null | undefined, rules: Rule[]): string {
    if (code == null || code === '') return 'чисто';

    return rules.find((rule) => rule.code === code)?.name ?? code;
  }
}
