import type { GameNpc } from '@/modules/Roleplay/Game/Dto/GameNpc';

/** Лист НПС устарел относительно ревизии/пространства игры (пустой лист не переводят). */
export function needsNpcMigration(
  npc: Pick<GameNpc, 'version'>,
  game: { rulesRevision: number | null; spaceCode: string | null },
): boolean {
  const version = npc.version;
  if (!version || game.rulesRevision === null || game.spaceCode === null) return false;

  return version.rulesRevision !== game.rulesRevision || version.spaceCode !== game.spaceCode;
}
