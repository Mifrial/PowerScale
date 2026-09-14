import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

function entityToken(key: CombatEntityKey, name: string): string {
  if (key.startsWith('npc:')) return `[[npc:${key.slice(4)},${name}]]`;

  return `[[character:${key.slice(10)},${name}]]`;
}

export function formatBloodClottingMessage(
  targetName: string,
  targetKey: CombatEntityKey,
  strength: number,
  rating: number,
  passed: boolean,
): string {
  const target = entityToken(targetKey, targetName);
  if (!passed) return `${target}: свёртывание крови (рана сила ${strength}) — провал.`;

  return `${target}: свёртывание крови (рана сила ${strength}) — успех, +${rating} к вкладу.`;
}
