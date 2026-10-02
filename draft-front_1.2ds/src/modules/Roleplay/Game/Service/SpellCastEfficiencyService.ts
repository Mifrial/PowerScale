import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

/** Сдвиг грани эффективности броска суммой дельт по источнику. */
export class SpellCastEfficiencyService {
  apply(spec: DiceRollSpec, deltas: readonly { sourceCode: string; delta: number }[]): DiceRollSpec {
    if (deltas.length === 0) {
      return spec;
    }
    const sum = aggregateSourceDeltasService.netSourceDelta(
      deltas.map((entry) => ({ source_code: entry.sourceCode, delta: entry.delta })),
    );
    const next = new DimensionalNumber({ base: spec.efficiency, size: spec.efficiencySize ?? 0 }).modify(
      sum,
      CHARACTERISTIC_BASE_RANGE,
    );

    return { ...spec, efficiency: next.value.base, efficiencySize: next.value.size };
  }
}
