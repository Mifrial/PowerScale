import { describe, expect, it, vi } from 'vitest';
import { GameApi } from '@/modules/Roleplay/Game/Service/GameApi';

describe('GameApi.getActionModifiers', () => {
  it('просит модификаторы у сервера по участнику и отбрасывает пустые строки', async () => {
    const runAction = vi.fn().mockResolvedValue({
      success: true,
      data: {
        modifiers: [
          {
            actionCode: 'razmashistyy-udar',
            actionName: 'Размашистый удар',
            code: 'yarostnyy-ryvok',
            name: 'Яростный рывок',
            summary: 'Проверка на Силу воли против 3',
          },
          { actionCode: '', actionName: '', code: 'broken', name: '', summary: '' },
        ],
      },
    });
    const api = new GameApi({ runAction } as never);

    const modifiers = await api.getActionModifiers(7, 'character:12');

    expect(runAction).toHaveBeenCalledWith(
      'game.getActionOptions',
      { gameId: 7, actorType: 'character', actorId: 12 },
      undefined,
    );
    expect(modifiers).toEqual([
      {
        actionCode: 'razmashistyy-udar',
        actionName: 'Размашистый удар',
        code: 'yarostnyy-ryvok',
        name: 'Яростный рывок',
        summary: 'Проверка на Силу воли против 3',
      },
    ]);
  });

  it('отказывает пустому ключу участника', async () => {
    const runAction = vi.fn();
    const api = new GameApi({ runAction } as never);

    await expect(api.getActionModifiers(7, 'character:0')).rejects.toThrow();
    expect(runAction).not.toHaveBeenCalled();
  });
});
