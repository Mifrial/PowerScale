import type { CheckAllowedModes } from '@/modules/Roleplay/Rule/Enum/CheckAllowedModes';
import type { CheckDifficultyInput } from '@/modules/Roleplay/Rule/Dto/Check/CheckDifficultyInput';

/**
 * Спека правила type='check'. Наследование parent_check_code — механики броска и матчинг грантов.
 * Соло/joint — режим запуска, не отдельная карточка.
 */
export interface CheckSpec {
  type: 'check';
  parent_check_code?: string | null;
  characteristic_code?: string | null;
  allow_characteristic_override?: boolean;
  default_efficiency?: number | null;
  difficulty_input: CheckDifficultyInput;
  allowed_modes: CheckAllowedModes;
  /** Нет поля или true — проверку можно запустить из диалога. */
  dialog_launch?: boolean;
  /** Нет поля — карточка не корень обычных проверок. */
  ordinary_root?: boolean;
  /** Нет поля — проверка и её потомки не открывают трату жетона концентрации. */
  concentration_token?: boolean;
  /** Нет поля — предок не считается проверкой воли. */
  willpower?: boolean;
  /** Нет поля — карточка не проверка ловкости при неустойчивости. Берётся первая такая. */
  unstable_check?: boolean;
  /** Нет поля — карточка не проверка удара. Берётся первая такая. */
  hit_check?: boolean;
  /**
   * Коды правил, чьи механики висят на броске этой проверки (напр. `rule-6-and-1`).
   * Задано (в т.ч. []) — не наследовать у предка. Не коды механик.
   */
  attached_rule_codes?: string[] | null;
}
