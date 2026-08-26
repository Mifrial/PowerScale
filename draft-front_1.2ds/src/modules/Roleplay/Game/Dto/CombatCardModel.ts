import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CombatEntityKind } from '@/modules/Roleplay/Game/Enum/CombatEntityKind';
/** Модель боевой карточки участника: резолюция entity + версия/оверлей + права. */
export interface CombatCardModel {
  kind: CombatEntityKind;
  entityId: number;
  entityKey: CombatEntityKey;
  name: string;
  /** Approved-версия персонажа / версия НПС (null — лист не заполнен). */
  version: CharacterVersion | null;
  /** Оверлей с изменениями; null — изменений ещё не было (пустая запись). */
  overlay: GameCombatOverlay | null;
  /** Версия с применённым оверлеем для view-model карточки (null — версии нет). */
  effectiveVersion: CharacterVersion | null;
  /** CD-6: игрок — своего approved-персонажа; ГМ — любого (персонажи и НПС). */
  canEdit: boolean;
}
