import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { GamePhpSpellChain } from '@/modules/Roleplay/Game/Dto/GamePhpSpellChain';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { CHARACTERISTIC_BASE_RANGE, CharacteristicNumber } from '@/modules/Roleplay/Rule/Value/CharacteristicNumber';

/**
 * Урон следующего прыжка цепи для показа в окне выбора.
 *
 * Повторяет серверную формулу (снижение по ипари, −размер за прыжок,
 * минимум) только для подсказки. Сопротивление цели не учитывает:
 * сервер считает урон сам.
 */
export class SpellChainPreviewService {
  private readonly sizeStep = CHARACTERISTIC_BASE_RANGE.max - CHARACTERISTIC_BASE_RANGE.min + 1;

  damage(chain: GamePhpSpellChain, distanceIpari: number): DimensionalNumberValue | null {
    let reduced = CharacteristicNumber.from(chain.amount);
    if (chain.freeIpari !== null && chain.sizePerExtraIpari !== null) {
      const extra = Math.max(0, distanceIpari - chain.freeIpari);
      if (extra > 0) {
        reduced = reduced.modifyWith(-this.sizeStep * extra * chain.sizePerExtraIpari);
      }
      if (chain.damageMin && reduced.modifyDiffTo(new DimensionalNumber(chain.damageMin)) < 0) {
        return null;
      }
    }

    const hop = reduced.modifyWith(-this.sizeStep * chain.damageSizePerHop);
    if (hop.modifyDiffTo(new DimensionalNumber(chain.min)) < 0) {
      return null;
    }

    return hop.value;
  }
}
