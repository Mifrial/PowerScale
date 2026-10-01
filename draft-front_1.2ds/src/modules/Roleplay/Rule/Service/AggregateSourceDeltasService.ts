import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import { ADVANTAGE_SOURCE_MANUAL } from '@/modules/Roleplay/Rule/Constant/ADVANTAGE_SOURCE';

export class AggregateSourceDeltasService {
  /**
   * К одной цели от одного указанного источника — самый сильный бонус и самый сильный штраф.
   * Запись без source — отдельный источник: две такие не схлопываются. Нулевой delta не входит.
   */
  aggregateSourceDeltas<T extends { source_code: string | null; delta: number }>(entries: readonly T[]): T[] {
    const groups = new Map<string, T[]>();
    entries.forEach((entry, index) => {
      if (entry.delta === 0) return;
      const key = entry.source_code === null ? `\0${index}` : entry.source_code;
      const group = groups.get(key);
      if (group) group.push(entry);
      else groups.set(key, [entry]);
    });

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
