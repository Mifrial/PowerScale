import type { CombatStateDetailRow } from '@/modules/Roleplay/Game/Dto/CombatStateDetailRow';
import type { CombatProcessKind } from '@/modules/Roleplay/Game/Enum/CombatProcessKind';

/** Строка блока «Процессы» на боевой карточке. */
export interface CombatProcessRow {
  id: string;
  kind: CombatProcessKind;
  name: string;
  leftLabel: string;
  valueLabel: string;
  iconCode: string;
  details: CombatStateDetailRow[];
  canAbort: boolean;
  abortLabel: string;
  canChargeCast: boolean;
  chargeCastLabel: string;
}
