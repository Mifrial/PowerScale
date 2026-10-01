import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import { routes } from '@/modules/Roleplay/Game/routes';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import { gameNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';
import { users as realUsers } from '@/modules/Core/User/Mock/mockUsers';
import { npcDetailAccessService } from '@/modules/Roleplay/Game/Service/Instance/npcDetailAccessService';

const gameRoot = join(dirname(fileURLToPath(import.meta.url)), '../..');

describe('NpcDetail route', () => {
  it('ленивый маршрут без route-perm', () => {
    const route = routes.find((entry) => entry.name === 'NpcDetail');
    expect(route?.path).toBe('games/:id/npcs/:npcId');
    expect(route?.meta?.requiresAll).toBeUndefined();
    expect(typeof route?.component).toBe('function');
  });
});

describe('ленивая деталка NPC', () => {
  it('список и карточка игры не импортируют страницу деталки', () => {
    const list = readFileSync(join(gameRoot, 'Component/Detail/NpcsTab.vue'), 'utf8');
    const gamePage = readFileSync(join(gameRoot, 'Page/GameDetailPage.vue'), 'utf8');
    expect(list).not.toContain('NpcDetailPage');
    expect(gamePage).not.toContain('NpcDetailPage');
    expect(list).toContain('getNpcSummaries');
  });
});

describe('NpcDetailAccessService', () => {
  const game = gameDetails.find((detail) => detail.game.id === 1)!;
  const user = (id: number) => realUsers.find((entry) => entry.id === id)!;

  it('скрытый лист не открывается и не считается полным', () => {
    const hidden = gameNpcs.find((npc) => npc.id === 4)!;
    expect(npcDetailAccessService.canOpen(user(6), game, hidden)).toBe(false);
    expect(npcDetailAccessService.showsFullSheet(null, ['shortDescription'])).toBe(false);
  });

  it('чужое предложение не открывается, полный лист — только со всеми секциями', () => {
    const proposed = gameNpcs.find((npc) => npc.id === 3)!;
    expect(npcDetailAccessService.canOpen(user(6), game, proposed)).toBe(false);
    expect(npcDetailAccessService.canOpen(user(1), game, proposed)).toBe(true);
    expect(npcDetailAccessService.showsFullSheet({} as CharacterVersion, SHEET_VISIBLE_SECTIONS)).toBe(true);
    expect(npcDetailAccessService.showsFullSheet({} as CharacterVersion, SHEET_VISIBLE_SECTIONS.slice(0, 2))).toBe(
      false,
    );
  });
});
