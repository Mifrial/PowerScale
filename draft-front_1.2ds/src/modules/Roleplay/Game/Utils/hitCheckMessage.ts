import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Enum/HitDefenseReaction';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function entityToken(key: CombatEntityKey, name: string): string {
  if (key.startsWith('npc:')) return `[[npc:${key.slice(4)},${name}]]`;

  return `[[character:${key.slice(10)},${name}]]`;
}

function ruleToken(ruleCode: string | null | undefined, fallbackName: string, rules: Rule[]): string {
  if (!ruleCode) return fallbackName;
  const rule = rules.find((entry) => entry.code === ruleCode);

  return rule ? `[[rule:${rule.code}]]` : fallbackName;
}

export function formatHitCheckMessage(input: {
  attackerKey: CombatEntityKey;
  attackerName: string;
  defenderKey: CombatEntityKey;
  defenderName: string;
  weaponRuleCode: string;
  weaponName: string;
  reaction: HitDefenseReaction;
  blockItemRuleCode?: string | null;
  rules: Rule[];
}): string {
  const attacker = entityToken(input.attackerKey, input.attackerName);
  const defender = entityToken(input.defenderKey, input.defenderName);
  const weapon = ruleToken(input.weaponRuleCode, input.weaponName, input.rules);
  let reactionText = 'игнорирует удар';
  if (input.reaction === 'dodge') reactionText = 'пытается уклониться';
  if (input.reaction === 'block') {
    const block = ruleToken(input.blockItemRuleCode, 'щит', input.rules);
    reactionText = `пытается блокировать, используя ${block}`;
  }

  return `${attacker} совершает проверку на попадание оружием ${weapon} против ${defender}. Тот ${reactionText}!`;
}
