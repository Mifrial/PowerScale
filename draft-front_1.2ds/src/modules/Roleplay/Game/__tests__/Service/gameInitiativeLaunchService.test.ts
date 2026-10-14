import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { IGameApi } from '@/modules/Roleplay/Game/Interface/IGameApi';
import { gameInitiativeLaunchService } from '@/modules/Roleplay/Game/Service/Instance/gameInitiativeLaunchService';
import { gamePhpBattleContext } from '@/modules/Roleplay/Game/Service/Instance/gamePhpBattleContext';

function api(overrides: Partial<IGameApi>): IGameApi {
  return overrides as unknown as IGameApi;
}

describe('gameInitiativeLaunchService', () => {
  beforeEach(() => {
    gamePhpBattleContext.battleId = null;
    gamePhpBattleContext.battleVersion = 1;
    gamePhpBattleContext.lastOrder = null;
  });

  it('не бросает, если бой уже открыт', async () => {
    gamePhpBattleContext.rememberBattle(9, 1);
    await expect(
      gameInitiativeLaunchService.launch(
        api({}),
        1,
        true,
        [{ type: 'character', id: 1, method: 'characteristic' }],
        new Map(),
      ),
    ).rejects.toThrow('Бой уже открыт');
  });

  it('стартует сессию только если её нет, затем бой с порядком', async () => {
    const startGameSession = vi.fn(async () => {
      return { kind: 'transition' as const, conflict: undefined };
    });
    const startGameBattle = vi.fn(async () => {
      gamePhpBattleContext.rememberBattle(4, 2);
      gamePhpBattleContext.rememberOrder([
        { type: 'character', id: 10, difficulty: null, roll: { base: 5, size: 0 }, success: true, rating: 5 },
      ]);

      return { kind: 'transition' as const };
    });
    const rollInitiative = vi.fn();

    const launched = await gameInitiativeLaunchService.launch(
      api({ startGameSession, startGameBattle, rollInitiative }),
      3,
      false,
      [{ type: 'character', id: 10, method: 'characteristic' }],
      new Map([['character:10', 'Герой']]),
    );

    expect(startGameSession).toHaveBeenCalledOnce();
    expect(startGameBattle).toHaveBeenCalledOnce();
    expect(startGameBattle.mock.calls[0]?.[0]).toMatchObject({
      rolls: [{ type: 'character', id: 10, method: 'characteristic' }],
    });
    expect(rollInitiative).not.toHaveBeenCalled();
    expect(launched.scale.participants[0]?.name).toBe('Герой');
    expect(launched.orderMessage).toContain('Герой');
  });
});
