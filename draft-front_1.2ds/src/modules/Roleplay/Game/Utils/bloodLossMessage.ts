import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

function entityToken(key: CombatEntityKey, name: string): string {
  if (key.startsWith('npc:')) return `[[npc:${key.slice(4)},${name}]]`;

  return `[[character:${key.slice(10)},${name}]]`;
}

function damageWord(n: number): string {
  const n10 = n % 10;
  const n100 = n % 100;
  if (n10 === 1 && n100 !== 11) return 'повреждение';
  if (n10 >= 2 && n10 <= 4 && (n100 < 10 || n100 >= 20)) return 'повреждения';

  return 'повреждений';
}

export function formatBloodLossTickMessage(
  targetName: string,
  delta: number,
  total: number,
  targetKey: CombatEntityKey,
): string {
  const target = entityToken(targetKey, targetName);

  return `${target} получил ${delta} ${damageWord(delta)} от кровопотери. Итого у персонажа уже Кровопотеря ${total}`;
}
