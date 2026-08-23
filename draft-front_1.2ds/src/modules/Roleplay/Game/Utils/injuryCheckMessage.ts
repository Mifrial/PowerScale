import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { InjuryHealUnit, InjuryOutcome } from '@/modules/Roleplay/Game/Dto/InjuryOutcome';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function entityToken(key: CombatEntityKey, name: string): string {
  if (key.startsWith('npc:')) return `[[npc:${key.slice(4)},${name}]]`;

  return `[[character:${key.slice(10)},${name}]]`;
}

function healUnitLabel(unit: InjuryHealUnit): string {
  if (unit === 'days') return 'дн.';
  if (unit === 'decades') return 'дек.';
  if (unit === 'months') return 'мес.';

  return 'лет';
}

export function formatInjuryOutcome(injury: InjuryOutcome): string {
  if (injury.strength <= 0) return 'увечья нет';
  const duration = injury.permanent ? 'Постоянное' : 'Временное';
  const heal = injury.heal
    ? ` (${injury.heal.diceCount}д${injury.heal.dieFaces} ${healUnitLabel(injury.heal.unit)} → ${injury.heal.total})`
    : '';
  const lethal = injury.lethal ? 'да' : 'нет';
  const disfiguring = injury.disfiguring ? 'да' : 'нет';

  return `Увечье: ${injury.strength}, ${duration}${heal}, Смертельное: ${lethal}, Обезображивающее: ${disfiguring}`;
}

export function formatInjuryCheckMessage(input: {
  targetKey: CombatEntityKey;
  targetName: string;
  damageTypeCode?: string | null;
  rules: Rule[];
  injury: InjuryOutcome;
}): string {
  const target = entityToken(input.targetKey, input.targetName);
  const typeRule = input.damageTypeCode ? input.rules.find((rule) => rule.code === input.damageTypeCode) : undefined;
  const typeBit = typeRule ? ` (${typeRule.name})` : '';

  return `${target} проходит проверку на увечье${typeBit}. ${formatInjuryOutcome(input.injury)}.`;
}
