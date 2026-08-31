import type { ChatAttachment } from '@/modules/Messages/Chat/Dto/ChatAttachment';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { DotTickCalcPayload } from '@/modules/Roleplay/Game/Dto/DotTickCalcPayload';
import type { ApplyAttackDamageResult } from '@/modules/Roleplay/Game/Dto/ApplyAttackDamageResult';

import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DOT_TICK_ATTACHMENT_TYPE } from '@/modules/Roleplay/Game/Constant/Dot/DOT_TICK_ATTACHMENT_TYPE';
import { damageTypeSpecService } from '@/modules/Roleplay/Rule/init';

function entityToken(key: CombatEntityKey, name: string): string {
  if (key.startsWith('npc:')) return `[[npc:${key.slice(4)},${name}]]`;

  return `[[character:${key.slice(10)},${name}]]`;
}

function countWord(n: number, one: string, few: string, many: string): string {
  if (n === 0) return few;
  const n10 = n % 10;
  const n100 = n % 100;
  if (n10 === 1 && n100 !== 11) return one;
  if (n10 >= 2 && n10 <= 4 && (n100 < 10 || n100 >= 20)) return few;

  return many;
}

function typeChip(code: string, rules: Rule[]): string {
  const rule = rules.find((item) => item.code === code && item.type === 'damage_type');
  const genitive = damageTypeSpecService.damageTypeForms(code, rules)?.genitive ?? rule?.name.toLowerCase() ?? code;
  if (!rule) return genitive;

  return `[[rule:${rule.code},${genitive}]]`;
}

export function formatDotTickMessage(
  targetName: string,
  targetKey: CombatEntityKey,
  damageTypeCode: string,
  hpDamage: number,
  exhaustion: number,
  rules: Rule[],
): string {
  const target = entityToken(targetKey, targetName);
  const typeBit = typeChip(damageTypeCode, rules);
  const damage = `${hpDamage} ${countWord(hpDamage, 'урон', 'урона', 'уронов')}`;
  const exh =
    exhaustion > 0
      ? `, что наносит ему ${exhaustion} ${countWord(exhaustion, 'истощение', 'истощения', 'истощений')}`
      : '';

  return `${target} получает ${damage} от ${typeBit}${exh}`;
}

export function buildDotTickAttachment(
  label: string,
  strength: DimensionalNumberValue,
  damageTypeCode: string,
  result: ApplyAttackDamageResult,
  rules: Rule[],
  defenseIgnored: boolean,
): ChatAttachment<DotTickCalcPayload> {
  const typeRule = rules.find((item) => item.code === damageTypeCode && item.type === 'damage_type');

  return {
    type: DOT_TICK_ATTACHMENT_TYPE,
    payload: {
      label,
      raw: result.raw,
      hpDamage: result.hpDamage,
      damage: strength,
      damageTypeName: typeRule?.name.toLowerCase() ?? damageTypeCode,
      resistance: result.resistance,
      defenseIgnored,
      exhaustion: result.exhaustion,
      layers: result.layers,
    },
  };
}
