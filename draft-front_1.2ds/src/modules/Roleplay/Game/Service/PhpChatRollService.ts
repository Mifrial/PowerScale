import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';
import type { RangedHitDifficultyBreakdown } from '@/modules/Roleplay/Game/Dto/DiceRollCheckOutcome';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';

/** Вложение type=roll из PHP-проверки удара/воли: {roll, difficulty, success, rating}. */
export class PhpChatRollService {
  displayOf(payload: unknown): DiceRollResult | null {
    const fromPhp = this.fromPhpCheck(payload);
    if (fromPhp) return fromPhp;
    if (this.hasSpec(payload)) return payload;

    return null;
  }

  fromPhpCheck(payload: unknown): DiceRollResult | null {
    if (payload === null || typeof payload !== 'object' || Array.isArray(payload)) return null;
    const row = payload as Record<string, unknown>;
    if ('spec' in row) return null;
    if (typeof row.success !== 'boolean' || typeof row.rating !== 'number') return null;
    const difficulty = this.pair(row.difficulty);
    const thrown = this.pair(row.roll);
    if (!difficulty || !thrown) return null;
    const faces = Array.isArray(row.faces) ? row.faces.filter((face): face is number => typeof face === 'number') : [];
    const adjusted = Array.isArray(row.adjustedRolls)
      ? row.adjustedRolls.filter((face): face is number => typeof face === 'number')
      : faces;
    const successes = Array.isArray(row.successes)
      ? row.successes.filter((face): face is number => typeof face === 'number')
      : adjusted.map(() => 0);
    const dropped = Array.isArray(row.droppedRolls)
      ? row.droppedRolls.filter((face): face is number => typeof face === 'number')
      : [];
    const dieFaces = typeof row.dieFaces === 'number' ? row.dieFaces : thrown.size || 6;
    const label = typeof row.label === 'string' ? row.label : undefined;
    const actorKey = typeof row.actorKey === 'string' ? (row.actorKey as CombatEntityKey) : undefined;
    const masteryAdjustments = this.masteryAdjustmentsOf(row.masteryAdjustments);
    const efficiency = this.pair(row.efficiency);
    const rangedHit = this.rangedHitOf(row.ranged_hit);

    return {
      spec: {
        diceCount: Math.max(1, adjusted.length || faces.length),
        dieFaces,
        efficiency: efficiency?.base ?? 0,
        ...(efficiency ? { efficiencySize: efficiency.size } : {}),
        advantages: [],
        dieSize: thrown.size,
        ...(typeof row.poolSize === 'number' ? { poolSize: row.poolSize } : {}),
        ...(label ? { label } : {}),
        ...(actorKey ? { actorKey } : {}),
        ...(masteryAdjustments.length ? { masteryAdjustments } : {}),
      },
      rolls: faces,
      successes,
      adjustedRolls: adjusted,
      droppedRolls: dropped,
      totalSuccesses: thrown.base,
      check: {
        check_code: label ?? 'check',
        ...(label ? { check_name: label } : {}),
        difficulty,
        passed: row.success,
        rating: row.rating,
        ...(typeof row.min_success_size === 'number' ? { min_success_size: row.min_success_size } : {}),
        ...(rangedHit ? { ranged_hit: rangedHit } : {}),
      },
    };
  }

  private rangedHitOf(value: unknown): RangedHitDifficultyBreakdown | null {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) return null;
    const row = value as Record<string, unknown>;
    const reaction = row.reaction;
    if (reaction !== 'dodge' && reaction !== 'block' && reaction !== 'ignore') return null;
    if (
      typeof row.cover !== 'number' ||
      typeof row.defense_result !== 'number' ||
      typeof row.range_size !== 'number' ||
      typeof row.distance_ipari !== 'number'
    ) {
      return null;
    }

    return {
      cover: row.cover,
      defense_result: row.defense_result,
      reaction: reaction as HitDefenseReaction,
      range_size: row.range_size,
      distance_ipari: row.distance_ipari,
      ...this.optionalPair('cover_value', row.cover_value),
      ...this.optionalPair('defense_value', row.defense_value),
      ...this.optionalPair('minimum_value', row.minimum_value),
      ...this.optionalPair('base_value', row.base_value),
      ...this.optionalPair('distance_value', row.distance_value),
      ...this.optionalPair('falloff_value', row.falloff_value),
      ...this.optionalPair('difficulty', row.difficulty),
    };
  }

  private optionalPair<K extends string>(key: K, value: unknown): Record<K, DimensionalNumberValue> | object {
    const pair = this.pair(value);

    return pair ? ({ [key]: pair } as Record<K, DimensionalNumberValue>) : {};
  }

  private masteryAdjustmentsOf(value: unknown): AdvantageModifier[] {
    if (!Array.isArray(value)) return [];

    return value.flatMap((entry) => {
      if (entry === null || typeof entry !== 'object') return [];
      const row = entry as Record<string, unknown>;
      if (typeof row.delta !== 'number') return [];

      return [
        {
          source_code: typeof row.source_code === 'string' ? row.source_code : null,
          source_label: typeof row.source_label === 'string' ? row.source_label : null,
          delta: row.delta,
        },
      ];
    });
  }

  private hasSpec(payload: unknown): payload is DiceRollResult {
    return (
      payload !== null &&
      typeof payload === 'object' &&
      'spec' in payload &&
      (payload as DiceRollResult).spec !== null &&
      typeof (payload as DiceRollResult).spec === 'object'
    );
  }

  private pair(value: unknown): DimensionalNumberValue | null {
    if (value === null || typeof value !== 'object') return null;
    const row = value as Record<string, unknown>;
    if (typeof row.base !== 'number' || typeof row.size !== 'number') return null;

    return { base: row.base, size: row.size };
  }
}
