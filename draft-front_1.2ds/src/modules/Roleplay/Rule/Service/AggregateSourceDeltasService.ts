import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import { ADVANTAGE_SOURCE_MANUAL } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';

export class AggregateSourceDeltasService {
  /**
   * К одной цели от одного источника — самый сильный бонус и самый сильный штраф (ТР §7).
   */
  aggregateSourceDeltas<T extends { source_code: string | null; delta: number }>(entries: readonly T[]): T[] {
    const groups = new Map<string | null, T[]>();
    for (const entry of entries) {
      if (entry.delta === 0) continue;
      const group = groups.get(entry.source_code);
      if (group) group.push(entry);
      else groups.set(entry.source_code, [entry]);
    }

    const result: T[] = [];
    for (const group of groups.values()) {
      let bestBonus: T | null = null;
      let worstPenalty: T | null = null;
      for (const entry of group) {
        if (entry.delta > 0 && (bestBonus === null || entry.delta > bestBonus.delta)) bestBonus = entry;
        if (entry.delta < 0 && (worstPenalty === null || entry.delta < worstPenalty.delta)) worstPenalty = entry;
      }
      if (bestBonus) result.push(bestBonus);
      if (worstPenalty) result.push(worstPenalty);
    }

    return result;
  }

  netSourceDelta(entries: readonly { source_code: string | null; delta: number }[]): number {
    return this.aggregateSourceDeltas(entries).reduce((sum, entry) => sum + entry.delta, 0);
  }

  advantageEntries(
    delta: number,
    sourceCode: string | null = ADVANTAGE_SOURCE_MANUAL,
    sourceLabel: string | null = null,
  ): AdvantageModifier[] {
    if (delta === 0) return [];

    return [{ source_code: sourceCode, source_label: sourceLabel, delta }];
  }
}
