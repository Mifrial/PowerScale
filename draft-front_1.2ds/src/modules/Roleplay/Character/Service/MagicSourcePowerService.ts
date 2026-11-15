import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';
import type { ItemStorageValue } from '@/modules/Roleplay/Character/Dto/ItemStorageValue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

const MAGIC_POWER_CODE = 'magic-power';

/**
 * Источник-хранилище как магическая мощь: кристалл даёт свою Предельную мощь,
 * поэтому на карточке он показывается тем же чипом, что и характеристики.
 */
export class MagicSourcePowerService {
  /** Синтетическая характеристика «Магическая мощь» из строки `sheet.items`. */
  characteristicOf(source: ItemStorageValue, rules: Rule[]): CharacteristicOverview {
    const rule = rules.find((entry) => entry.code === MAGIC_POWER_CODE);
    const label = DimensionalNumber.from(source.maxPower).toString();

    return {
      ruleCode: MAGIC_POWER_CODE,
      name: rule?.name ?? 'Магическая мощь',
      shortName: null,
      base: source.maxPower,
      baseLabel: label,
      value: source.maxPower,
      valueLabel: label,
      delta: 0,
      href: null,
      isResolved: true,
      group: 'magic',
      subtitle: null,
      modifiers: [],
      conditionalModifiers: [],
      derived: null,
    };
  }

  /** Имя предмета-хранилища. */
  sourceName(source: ItemStorageValue, rules: Rule[]): string {
    return rules.find((entry) => entry.code === source.ruleCode)?.name ?? source.ruleCode;
  }
}
