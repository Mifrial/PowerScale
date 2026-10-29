import { describe, expect, it, vi } from 'vitest';
import { GameApi } from '@/modules/Roleplay/Game/Service/GameApi';

describe('GameApi.getActionOptions', () => {
  it('просит опции у сервера по участнику и отбрасывает пустые строки', async () => {
    const runAction = vi.fn().mockResolvedValue({
      success: true,
      data: {
        afterStrikeOptions: [
          {
            actionCode: 'razmashistyy-udar',
            code: 'yarostnyy-ryvok',
            name: 'Яростный рывок',
            summary: 'Проверка на Силу воли против 3',
          },
          { actionCode: '', code: 'broken', name: '', summary: '' },
        ],
      },
    });
    const api = new GameApi({ runAction } as never);

    const options = await api.getActionOptions(7, 'character:12');

    expect(runAction).toHaveBeenCalledWith(
      'game.getActionOptions',
      { gameId: 7, actorType: 'character', actorId: 12 },
      undefined,
    );
    expect(options).toEqual([
      {
        actionCode: 'razmashistyy-udar',
        code: 'yarostnyy-ryvok',
        name: 'Яростный рывок',
        summary: 'Проверка на Силу воли против 3',
      },
    ]);
  });

  it('отказывает пустому ключу участника', async () => {
    const runAction = vi.fn();
    const api = new GameApi({ runAction } as never);

    await expect(api.getActionOptions(7, 'character:0')).rejects.toThrow();
    expect(runAction).not.toHaveBeenCalled();
  });
});
