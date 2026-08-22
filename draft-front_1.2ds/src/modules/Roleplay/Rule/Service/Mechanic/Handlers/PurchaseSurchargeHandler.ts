import type { MechanicHandler } from '@/modules/Roleplay/Rule/Service/Mechanic/MechanicHandler';
import type { MechanicPayload } from '@/modules/Roleplay/Rule/Dto/MechanicPayload';
import type { CharacterMechanicContext } from '@/modules/Roleplay/Rule/Dto/CharacterMechanicContext';

/** Событие пересбора шага «Основа» (os) в редакторе персонажа. */
export const PURCHASE_SURCHARGE_EVENT = 'character.osSteps';

/**
 * Прогрессивная доплата: третья и каждая последующая способность из фильтра
 * доплачивает `surcharge` ОС сверх своей цены (Дискуссия 4). Две инстанции
 * («Общая», «Зверолюди») — две data-строки с разным payload.filter.
 * Пишет итог в аккумуляторы контекста шага (osSurchargeTotal + детализация).
 */
export const purchaseSurchargeHandler: MechanicHandler<CharacterMechanicContext> = {
  code: 'purchase_surcharge',
  version: '1.0.0',
  subscriptions: { [PURCHASE_SURCHARGE_EVENT]: 0 },
  run(input: { payload: MechanicPayload | null; context: CharacterMechanicContext }): void {
    if (input.payload?.type !== 'purchase_surcharge') return;
    const { filter, free_count, surcharge } = input.payload;
    const context = input.context;

    const matchedCodes: string[] = [];
    for (const [code, level] of context.abilityLevels) {
      if (level < 1) continue;
      const keywords = context.abilityKeywords.get(code);
      const byKeyword = filter.keyword_code !== undefined && keywords?.has(filter.keyword_code) === true;
      const byRace = filter.race_code !== undefined && context.racialAbilityCodes.has(code);
      if (byKeyword || byRace) matchedCodes.push(code);
    }

    const surchargedCount = Math.max(0, matchedCodes.length - free_count);
    if (surchargedCount === 0) return;
    context.osSurchargeTotal += surchargedCount * surcharge;
    for (const abilityCode of matchedCodes.slice(free_count)) {
      context.surchargeItems.push({ abilityCode, amount: surcharge });
    }
  },
};
