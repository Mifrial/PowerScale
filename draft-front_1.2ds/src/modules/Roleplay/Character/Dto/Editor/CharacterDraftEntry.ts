import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { InventoryItem } from '@/modules/Roleplay/Character/Dto/InventoryItem';

/** Базовая линия «Инвентаря» (R2): снапшот на момент входа на шаг. */
export interface InventoryBaseline {
  inventory: InventoryItem[];
  money: number;
}

/**
 * Единый черновик редактора листа (ТР §7): один на персонажа/НПС.
 * `draftKey` — строковый ключ с пространством имён: `character:${id}` / `npc:${id}`, null — новый.
 */
export interface CharacterDraftEntry {
  draftKey: string | null;
  build: CharacterBuild;
  config: CharacterCreationConfig;
  /**
   * Базовая линия «Инвентаря» (R2): edit — снапшот оригинала (fromVersion); new — фиксируется
   * при первом входе на шаг «Инвентарь» (деньги донормируются до effectiveMoney). Отменяются
   * только покупки сверх этой линии; продажа изначальных предметов не делается.
   */
  inventoryBaseline?: InventoryBaseline | null;
  /** Есть несохранённые на бэк изменения. */
  dirty: boolean;
  updatedAt: string;
}
