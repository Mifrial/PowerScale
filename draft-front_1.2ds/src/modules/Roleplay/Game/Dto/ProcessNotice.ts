import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Всплывающее уведомление о состоянии процесса. */
export interface ProcessNotice {
  text: string;
  /** Участник, чей процесс изменился. */
  entityKey?: CombatEntityKey;
  /** Подпись кнопки продолжения; нет — кнопки нет. */
  continueLabel?: string;
}
