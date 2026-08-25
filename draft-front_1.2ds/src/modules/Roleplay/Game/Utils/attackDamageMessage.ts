import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { AttackCalcPayload } from '@/modules/Roleplay/Game/Dto/AttackCalcPayload';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { ApplyAttackDamageResult } from '@/modules/Roleplay/Game/Utils/applyAttackDamage';
import type { CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function entityToken(key: CombatEntityKey, name: string): string {
  if (key.startsWith('npc:')) return `[[npc:${key.slice(4)},${name}]]`;

  return `[[character:${key.slice(10)},${name}]]`;
}

function ruleTokenById(ruleId: string | null | undefined, fallbackName: string, rules: Rule[]): string {
  if (!ruleId) return fallbackName;
  const rule = rules.find((entry) => entry.id === ruleId);

  return rule ? `[[rule:${rule.code}]]` : fallbackName;
}

function ruleTokenByCode(code: string | null | undefined, fallbackName: string, rules: Rule[]): string {
  if (!code) return fallbackName;
  const rule = rules.find((entry) => entry.code === code);

  return rule ? `[[rule:${rule.code}]]` : fallbackName;
}

export function formatDamageBrace(damage: DimensionalNumberValue): string {
  return `{${damage.base}|${damage.size}}`;
}

export function formatAttackSrLabel(sr: number): string {
  return String(sr);
}

export function formatAttackActionMessage(input: {
  attackerKey: CombatEntityKey;
  attackerName: string;
  action: CombatActionOption;
  attackerAp: number;
  rules: Rule[];
}): string {
  const attacker = entityToken(input.attackerKey, input.attackerName);
  const action = ruleTokenByCode(input.action.code, input.action.name, input.rules);

  return `${attacker} совершает действие ${action} за ${input.attackerAp}ОД.`;
}

export function formatStrikeNarrativeMessage(input: {
  attackerKey: CombatEntityKey;
  attackerName: string;
  defenderKey: CombatEntityKey;
  defenderName: string;
  weaponRuleId: string;
  weaponName: string;
  damageTypeCode?: string | null;
  profileType?: 'strike' | 'throw' | 'shoot';
  flank?: boolean;
  turn?: boolean;
  reaction: HitDefenseReaction;
  reactionAction: CombatActionOption | null;
  reactionAp: number;
  rules: Rule[];
}): string {
  const attacker = entityToken(input.attackerKey, input.attackerName);
  const defender = entityToken(input.defenderKey, input.defenderName);
  const weapon = ruleTokenById(input.weaponRuleId, input.weaponName, input.rules);
  const typeRule = input.damageTypeCode ? input.rules.find((rule) => rule.code === input.damageTypeCode) : undefined;
  const typeBit = typeRule ? typeRule.name.toLowerCase() : 'безымянный';
  const kind = input.profileType === 'throw' ? 'бросок' : input.profileType === 'shoot' ? 'выстрел' : 'удар';
  const flankBit = input.flank ? 'с фланга ' : '';
  const strike = `${attacker} наносит ${flankBit}${typeBit} ${kind} оружием ${weapon} по ${defender}.`;
  if (input.reaction === 'ignore' || !input.reactionAction) {
    return `${strike} Тот не реагирует.`;
  }
  const reaction = ruleTokenByCode(input.reactionAction.code, input.reactionAction.name, input.rules);
  const turnBit = input.turn ? ' с Поворотом' : '';

  return `${strike} Тот пытается совершить ${reaction}${turnBit} за ${input.reactionAp}ОД!`;
}

export function formatAttackResultMessage(input: {
  attackerKey: CombatEntityKey;
  attackerName: string;
  defenderKey: CombatEntityKey;
  defenderName: string;
  remainingSr: number;
  exhaustion: number;
  wound?: number;
}): string {
  const attacker = entityToken(input.attackerKey, input.attackerName);
  const defender = entityToken(input.defenderKey, input.defenderName);
  if (input.remainingSr <= 0) {
    return `${attacker} промахивается по ${defender}!`;
  }
  const bits = [`${input.exhaustion} истощения`];
  const wound = Math.max(0, input.wound ?? 0);
  if (wound > 0) bits.push(`${wound} рану`);

  return `${attacker} попадает по ${defender} с ${input.remainingSr} РУ и наносит ${bits.join(' и ')}!`;
}

export function buildAttackCalcPayload(input: {
  weaponDamage: DimensionalNumberValue;
  damageTypeCode?: string | null;
  rules: Rule[];
  sr: number;
  result: ApplyAttackDamageResult;
  defenseIgnored: boolean;
}): AttackCalcPayload {
  const typeRule = input.damageTypeCode ? input.rules.find((rule) => rule.code === input.damageTypeCode) : undefined;
  const typeName = typeRule?.name.toLowerCase() ?? 'урон';

  return {
    raw: input.result.raw,
    damage: input.weaponDamage,
    damageTypeName: typeName,
    resistance: input.result.resistance,
    defenseIgnored: input.defenseIgnored,
    attackSrLabel: formatAttackSrLabel(input.sr),
    stun: input.result.stun,
    wound: input.result.wound,
    knockout: input.result.knockout,
    cuttingWound: input.result.cuttingWound,
    layers: input.result.layers,
  };
}

export function defaultAttackActionLabel(profileType: 'strike' | 'throw' | 'shoot'): string {
  if (profileType === 'throw' || profileType === 'shoot') return 'Простая атака (дальний бой)';

  return 'Простая атака (ближний бой)';
}
