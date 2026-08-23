import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { HitDefenseReaction } from '@/modules/Roleplay/Game/Dto/CheckOffer';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

function entityToken(key: CombatEntityKey, name: string): string {
  if (key.startsWith('npc:')) return `[[npc:${key.slice(4)},${name}]]`;

  return `[[character:${key.slice(10)},${name}]]`;
}

function ruleToken(ruleId: string | null | undefined, fallbackName: string, rules: Rule[]): string {
  if (!ruleId) return fallbackName;
  const rule = rules.find((entry) => entry.id === ruleId);

  return rule ? `[[rule:${rule.code}]]` : fallbackName;
}

export function formatHitCheckMessage(input: {
  attackerKey: CombatEntityKey;
  attackerName: string;
  defenderKey: CombatEntityKey;
  defenderName: string;
  weaponRuleId: string;
  weaponName: string;
  reaction: HitDefenseReaction;
  blockItemRuleId?: string | null;
  rules: Rule[];
}): string {
  const attacker = entityToken(input.attackerKey, input.attackerName);
  const defender = entityToken(input.defenderKey, input.defenderName);
  const weapon = ruleToken(input.weaponRuleId, input.weaponName, input.rules);
  let reactionText = 'игнорирует удар';
  if (input.reaction === 'dodge') reactionText = 'пытается уклониться';
  if (input.reaction === 'block') {
    const block = ruleToken(input.blockItemRuleId, 'щит', input.rules);
    reactionText = `пытается блокировать, используя ${block}`;
  }

  return `${attacker} совершает проверку на попадание оружием ${weapon} против ${defender}. Тот ${reactionText}!`;
}
