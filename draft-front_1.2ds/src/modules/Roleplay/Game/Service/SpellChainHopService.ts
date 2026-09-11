import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { SpellChain } from '@/modules/Roleplay/Rule/Dto/Ability/SpellChain';
import type { SpellDamage } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDamage';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CHARACTERISTIC_BASE_RANGE, CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';
import { spellDamageService } from '@/modules/Roleplay/Rule/init';

/** Прыжки Цепной: −1 размер от предыдущего hop; via_other разрешает повтор после другой цели. */
export class SpellChainHopService {
  canHop(input: {
    raw: number;
    nextKey: CombatEntityKey;
    lastKey: CombatEntityKey;
    alreadyHit: CombatEntityKey[];
    sameTarget: SpellChain['same_target'];
  }): boolean {
    if (input.raw <= 0) {
      return false;
    }
    if (input.nextKey === input.lastKey) {
      return false;
    }
    if (input.sameTarget === 'via_other') {
      return true;
    }

    return !input.alreadyHit.includes(input.nextKey);
  }

  nextAmount(
    amount: DimensionalNumberValue,
    chain: SpellChain,
    distanceIpari: number,
    falloff: SpellDamage['falloff'] | undefined,
  ): DimensionalNumberValue | null {
    const sizeStep = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;
    let next: DimensionalNumberValue | null = amount;
    if (falloff) {
      next = spellDamageService.applyFalloff(amount, distanceIpari, falloff);
    }
    if (!next) {
      return null;
    }
    const reduced = CharacteristicNumber.from(next).modifyWith(-sizeStep * chain.damage_size_per_hop);
    if (reduced.modifyDiffTo(new DimensionalNumber(chain.min)) < 0) {
      return null;
    }

    return reduced.value;
  }
}
