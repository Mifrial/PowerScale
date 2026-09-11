import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { AdvantageModifier } from '@/modules/Roleplay/Rule/Dto/AdvantageModifier';
/** Проверка характеристики: пул = база, размерность уходит в dieSize броска. */
export interface CharacteristicRollEntry {
  /** Имя характеристики (label броска и текст сообщения в чат). */
  name: string;
  value: DimensionalNumberValue;
  /** Код характеристики → проверка `check-{code}`. */
  characteristicCode?: string | null;
  /** ruleCode характеристики или проверки, если кода нет. */
  ruleCode?: string | null;
  advantages?: AdvantageModifier[];
  actorKey?: CombatEntityKey;
}
