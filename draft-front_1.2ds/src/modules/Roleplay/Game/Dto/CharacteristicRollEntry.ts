import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
/** Проверка характеристики: пул = база, размерность уходит в dieSize броска. */
export interface CharacteristicRollEntry {
  /** Имя характеристики (label броска и текст сообщения в чат). */
  name: string;
  value: DimensionalNumberValue;
  /** Код характеристики → проверка `check-{code}`. */
  characteristicCode?: string | null;
  /** ruleId характеристики или проверки, если кода нет. */
  ruleId?: string | null;
  actorKey?: CombatEntityKey;
}
