import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/**
 * Быстрый бросок (макрос) характеристики персонажа в боевом контексте игры.
 * Хранится per (game, entity); characteristicRuleId — характеристика версии листа.
 */
export interface GameQuickRoll {
  gameId: number;
  entityKey: CombatEntityKey;
  characteristicRuleId: string;
}
