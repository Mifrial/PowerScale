import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/**
 * Быстрый бросок (макрос) характеристики персонажа в боевом контексте игры.
 * Хранится per (game, entity); characteristicRuleCode — характеристика версии листа.
 */
export interface GameQuickRoll {
  gameId: number;
  entityKey: CombatEntityKey;
  characteristicRuleCode: string;
}
