import type { CharacteristicGroup } from '@/modules/Roleplay/Rule/Enum/CharacteristicGroup';
import type { OverviewModifier } from '@/modules/Roleplay/Character/Dto/Overview/OverviewModifier';
import type { DerivedCharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/DerivedCharacteristicOverview';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface CharacteristicOverview {
  ruleCode: string;
  name: string;
  /** Короткая подпись плашки (для секций с известным контекстом), напр. «Общее», «Меч»; null — полное имя. */
  shortName: string | null;
  /** База — размерное число (хранится в версии). */
  base: DimensionalNumberValue;
  /** Строка базы для отображения, напр. «3» или «3↑». */
  baseLabel: string;
  /** Итог = база + модификаторы. */
  value: DimensionalNumberValue;
  /** Строка для отображения итога, например «5» или «5↑». */
  valueLabel: string;
  /** Сумма постоянных модификаторов (пункты базы). */
  delta: number;
  href: string | null;
  isResolved: boolean;
  group: CharacteristicGroup;
  subtitle: string | null;
  modifiers: OverviewModifier[];
  conditionalModifiers: OverviewModifier[];
  derived: DerivedCharacteristicOverview | null;
}
