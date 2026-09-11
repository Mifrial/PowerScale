import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { SpellDamage } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDamage';
import { CHARACTERISTIC_BASE_RANGE, CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

/**
 * Считает урон заклинания как мощь с табличным modify по опыту keyword.
 */
export class SpellDamageService {
  modifyForExperience(damage: SpellDamage, experience: number): number {
    const matching = damage.power_modify_steps.filter((step) => step.min_experience <= experience);
    if (matching.length === 0) return damage.power_modify_steps[0]?.modify ?? 0;
    let chosen = matching[0];
    for (const step of matching) {
      if (step.min_experience > chosen.min_experience) chosen = step;
    }

    return chosen.modify;
  }

  amountFromPower(power: DimensionalNumberValue, modify: number): DimensionalNumberValue {
    return new DimensionalNumber(power).modify(modify, CHARACTERISTIC_BASE_RANGE).value;
  }

  /**
   * Снижает размер урона за ипари сверх бесплатных.
   * Null — урон ниже порога, попадание урона не наносит.
   */
  applyFalloff(
    amount: DimensionalNumberValue,
    distanceIpari: number,
    falloff: NonNullable<SpellDamage['falloff']>,
  ): DimensionalNumberValue | null {
    const extra = Math.max(0, Math.floor(distanceIpari) - falloff.free_ipari);
    const sizeStep = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;
    const reduced = CharacteristicNumber.from(amount).modifyWith(-sizeStep * extra * falloff.size_per_extra_ipari);
    if (reduced.modifyDiffTo(new DimensionalNumber(falloff.min)) < 0) return null;

    return reduced.value;
  }

  describe(damage: SpellDamage, typeName: string): string {
    const steps = [...damage.power_modify_steps]
      .sort((left, right) => left.min_experience - right.min_experience)
      .map((step) => `+${step.modify} от ${step.min_experience}`)
      .join(', ');
    const falloff = damage.falloff
      ? `; −${damage.falloff.size_per_extra_ipari} размер / ипари после ${damage.falloff.free_ipari}`
      : '';

    return `${typeName}; ${steps}${falloff}`;
  }
}
