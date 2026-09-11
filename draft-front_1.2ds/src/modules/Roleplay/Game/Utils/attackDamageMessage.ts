import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { AttackCalcPayload } from '@/modules/Roleplay/Game/Dto/AttackCalcPayload';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import type { ApplyAttackDamageResult } from '@/modules/Roleplay/Game/Dto/ApplyAttackDamageResult';

import { findRuleByRef, type CombatActionOption } from '@/modules/Roleplay/Game/Utils/combatActions';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function entityToken(key: CombatEntityKey, name: string): string {
  if (key.startsWith('npc:')) return `[[npc:${key.slice(4)},${name}]]`;

  return `[[character:${key.slice(10)},${name}]]`;
}

function ruleTokenById(ruleCode: string | null | undefined, fallbackName: string, rules: Rule[]): string {
  if (!ruleCode) return fallbackName;
  const rule = rules.find((entry) => entry.code === ruleCode);

  return rule ? `[[rule:${rule.code}]]` : fallbackName;
}

function ruleTokenByCode(code: string | null | undefined, fallbackName: string, rules: Rule[]): string {
  if (!code) return fallbackName;
  const rule = rules.find((entry) => entry.code === code);

  return rule ? `[[rule:${rule.code}]]` : fallbackName;
}

function actionToken(action: CombatActionOption, rules: Rule[]): string {
  const rule = findRuleByRef(rules, action.ruleCode) ?? findRuleByRef(rules, action.code);
  if (!rule) return action.name;
  if (rule.name === action.name) return `[[rule:${rule.code}]]`;

  const customName = action.name.startsWith(`${rule.name} · `) ? action.name.slice(rule.name.length + 3) : action.name;

  return `[[rule:${rule.code}]] · ${customName}`;
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
  const action = actionToken(input.action, input.rules);

  return `${attacker} совершает действие ${action} за ${input.attackerAp}ОД.`;
}

export function formatStrikeNarrativeMessage(input: {
  attackerKey: CombatEntityKey;
  attackerName: string;
  defenderKey: CombatEntityKey;
  defenderName: string;
  weaponRuleCode: string;
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
  const weapon = ruleTokenById(input.weaponRuleCode, input.weaponName, input.rules);
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

export function formatTouchConnectMessage(input: {
  attackerKey: CombatEntityKey;
  attackerName: string;
  defenderKey: CombatEntityKey;
  defenderName: string;
  passed: boolean;
}): string {
  const attacker = entityToken(input.attackerKey, input.attackerName);
  const defender = entityToken(input.defenderKey, input.defenderName);
  if (!input.passed) {
    return `${attacker} промахивается по ${defender}!`;
  }

  return `${attacker} попадает по ${defender}!`;
}

export function formatSpellCastBeginMessage(input: {
  casterKey: CombatEntityKey;
  casterName: string;
  spellRuleCode: string;
  spellName: string;
  spellOd: number;
  touchActionCode: string | null;
  touchActionName: string;
  touchOd: number;
  spentOd: number;
  targetKey?: CombatEntityKey | null;
  targetName?: string | null;
  appliedUpgradeCodes?: string[];
  rules: Rule[];
}): string {
  const caster = entityToken(input.casterKey, input.casterName);
  const spell = ruleTokenByCode(input.spellRuleCode, input.spellName, input.rules);
  let text = `${caster} творит заклинание ${spell} за ${input.spellOd}ОД!`;
  if (input.touchActionCode) {
    const touch = ruleTokenByCode(input.touchActionCode, input.touchActionName, input.rules);
    text += ` Заклинание требует касания, выбрано действие: ${touch} за ${input.touchOd}ОД. Итого будет потрачено ${input.spentOd}ОД!`;
  } else if (input.targetKey && input.targetName) {
    text += ` Цель: ${entityToken(input.targetKey, input.targetName)}.`;
  }
  const upgrades = (input.appliedUpgradeCodes ?? [])
    .map((code) => ruleTokenByCode(code, code, input.rules))
    .filter((token) => token !== '');
  if (upgrades.length > 0) {
    text += ` Применено: ${upgrades.join(', ')}.`;
  }

  return text;
}

export function formatSpellCastCheckMessage(input: {
  casterKey: CombatEntityKey;
  casterName: string;
  characteristicName: string;
}): string {
  const caster = entityToken(input.casterKey, input.casterName);

  return `${caster} проходит проверку на сотворение: ${input.characteristicName}.`;
}

export function formatSpellEffectMessage(input: {
  spellRuleCode: string;
  spellName: string;
  defenderKey: CombatEntityKey;
  defenderName: string;
  exhaustion: number;
  wound?: number;
  raw?: number;
  rules: Rule[];
}): string {
  const spell = ruleTokenByCode(input.spellRuleCode, input.spellName, input.rules);
  const defender = entityToken(input.defenderKey, input.defenderName);
  const bits: string[] = [];
  const raw = Math.max(0, input.raw ?? 0);
  if (input.exhaustion > 0) bits.push(`${input.exhaustion} истощения`);
  const wound = Math.max(0, input.wound ?? 0);
  if (wound > 0) bits.push(`${wound} рану`);
  if (bits.length === 0) {
    if (raw > 0) {
      return `${spell} бьёт по ${defender} и наносит ${raw} повреждения!`;
    }

    return `${spell} бьёт по ${defender}, но не наносит повреждений!`;
  }

  return `${spell} бьёт по ${defender} и наносит ${bits.join(' и ')}!`;
}

export function formatChainBreakMessage(input: { spellRuleCode: string; spellName: string; rules: Rule[] }): string {
  const spell = ruleTokenByCode(input.spellRuleCode, input.spellName, input.rules);

  return `${spell} обрывается.`;
}

export function formatSustainBeginMessage(input: {
  casterKey: CombatEntityKey;
  casterName: string;
  spellRuleCode: string;
  spellName: string;
  rules: Rule[];
}): string {
  const caster = entityToken(input.casterKey, input.casterName);
  const spell = ruleTokenByCode(input.spellRuleCode, input.spellName, input.rules);

  return `${caster} поддерживает ${spell}.`;
}

export function formatSustainDropMessage(input: {
  casterKey: CombatEntityKey;
  casterName: string;
  spellRuleCode: string;
  spellName: string;
  lostSource: boolean;
  rules: Rule[];
}): string {
  const caster = entityToken(input.casterKey, input.casterName);
  const spell = ruleTokenByCode(input.spellRuleCode, input.spellName, input.rules);
  if (input.lostSource) {
    return `${caster}: источник недоступен, ${spell} спадает.`;
  }

  return `${caster} перестаёт поддерживать ${spell}.`;
}

export function formatSpellCastOutcomeMessage(input: {
  milk: boolean;
  castFailed: boolean;
  casterKey: CombatEntityKey;
  casterName: string;
  spellRuleCode: string;
  spellName: string;
  spentOd: number;
  rules: Rule[];
}): string {
  if (input.milk) {
    return 'Эффект заклинания уходит в молоко.';
  }
  const caster = entityToken(input.casterKey, input.casterName);
  const spell = ruleTokenByCode(input.spellRuleCode, input.spellName, input.rules);
  if (input.castFailed) {
    return `${caster} не смог сотворить ${spell}.`;
  }

  return `${caster} успешно сотворил ${spell} за ${input.spentOd}ОД!`;
}

export function buildAttackCalcPayload(input: {
  weaponDamage: DimensionalNumberValue;
  damageTypeCode?: string | null;
  rules: Rule[];
  sr: number;
  endurance: DimensionalNumberValue;
  result: ApplyAttackDamageResult;
  defenseIgnored: boolean;
  heading?: string | null;
}): AttackCalcPayload {
  const typeRule = input.damageTypeCode ? input.rules.find((rule) => rule.code === input.damageTypeCode) : undefined;
  const typeName = typeRule?.name.toLowerCase() ?? 'урон';

  return {
    raw: input.result.raw,
    damage: input.weaponDamage,
    damageTypeName: typeName,
    resistance: input.result.resistance,
    endurance: input.endurance,
    defenseIgnored: input.defenseIgnored,
    attackSrLabel: formatAttackSrLabel(input.sr),
    appliedSr: input.result.appliedSr,
    srCap: input.result.srCap,
    heading: input.heading ?? null,
    stun: input.result.stun,
    shock: input.result.shock,
    exhaustion: input.result.exhaustion,
    remainingHpDamage: input.result.remainingHpDamage,
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
