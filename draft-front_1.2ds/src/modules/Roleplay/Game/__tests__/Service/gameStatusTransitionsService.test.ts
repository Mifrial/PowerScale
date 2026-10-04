import { describe, expect, it } from 'vitest';
import { gameStatusTransitionsService } from '@/modules/Roleplay/Game/Service/Instance/gameStatusTransitionsService';

import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';

const all: GameStatus[] = ['draft', 'recruiting', 'in_process', 'paused', 'completed'];

describe('gameStatusTransitions', () => {
  it('начать сессию можно из черновика/набора/в процессе/на паузе, статус при этом не меняется', () => {
    for (const status of all) {
      expect(gameStatusTransitionsService.canStartGame(status), status).toBe(
        status === 'draft' || status === 'recruiting' || status === 'in_process' || status === 'paused',
      );
    }
  });

  it('остановить сессию можно только когда она запущена; completed сам по себе сессию не гасит', () => {
    expect(gameStatusTransitionsService.canStopSession(true)).toBe(true);
    expect(gameStatusTransitionsService.canStopSession(false)).toBe(false);
  });

  it('завершённая игра read-only: сессию начать нельзя', () => {
    expect(gameStatusTransitionsService.canStartGame('completed')).toBe(false);
  });
});
