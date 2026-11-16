import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { GamePhpSustainPrompt } from '@/modules/Roleplay/Game/Dto/GamePhpSustainPrompt';
import type { GamePhpSpellChain } from '@/modules/Roleplay/Game/Dto/GamePhpSpellChain';

/** Ответ конца хода / continue / abort / раны. */
export interface GamePhpTurnResult {
  pendingActionEffects?: Record<CombatEntityKey, PendingActionEffect[]>;
  version: number;
  battleId?: number;
  sheetVersion?: number;
  activeIndex?: number;
  round?: number;
  /** `continueAction` открыл отложенный удар. */
  opened?: boolean;
  /** Почему отложенный удар не открылся. */
  refused?: string;
  /** Остаток ОД в долге, если удар ещё не открыт. */
  remaining?: number;
  /** Вопросы поддержания на входе хода нового текущего. */
  sustain?: GamePhpSustainPrompt[];
  /** Ключ каста, если долг завершился кастом. */
  castId?: string | null;
  /** Незавершённая цепь после завершения каста. */
  chain?: GamePhpSpellChain | null;
  /** Долг ОД нового текущего. */
  debt?: {
    kind: string;
    actionRuleCode: string;
    remaining: number;
    total: number;
  } | null;
  /** Прерванный процесс: карточка и шаг. */
  processRuleCode?: string;
  stepCode?: string;
  aborted?: boolean;
}
