import type { MechanicState } from '@/modules/Roleplay/Rule/Dto/MechanicState';

/**
 * Контекст шага сборки персонажа для механик: readonly-снимок (`MechanicState`)
 * + мутабельные аккумуляторы результата шага. Хендлеры пишут в аккумуляторы,
 * сборка читает их после прогона события.
 */
export interface CharacterMechanicContext extends MechanicState {
  /** Итоговая доплата за способности шага «Основа» (в ОС). */
  osSurchargeTotal: number;
  /** Детализация доплаты по способностям (для показа на вкладке). */
  surchargeItems: { abilityCode: string; amount: number }[];
}
