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
  /**
   * Коды правил, чьи механики висят на броске этой проверки (напр. `rule-6-and-1`).
   * Задано (в т.ч. []) — не наследовать у предка. Не коды механик.
   */
  attached_rule_codes?: string[] | null;
}
