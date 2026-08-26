import { describe, expect, it } from 'vitest';
import { gameStatusTransitionsService } from '@/modules/Roleplay/Game/Service/Instance/gameStatusTransitionsService';

import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';

const all: GameStatus[] = ['draft', 'recruiting', 'in_process', 'paused', 'playing', 'completed'];

describe('gameStatusTransitions', () => {
  it('начать сессию можно из черновика/набора/в процессе/на паузе (→ playing)', () => {
    for (const status of all) {
      expect(gameStatusTransitionsService.canStartGame(status), status).toBe(
        status === 'draft' || status === 'recruiting' || status === 'in_process' || status === 'paused',
      );
    }
  });

  it('остановить сессию можно только из playing (→ in_process); completed — отдельный терминальный статус', () => {
    for (const status of all) {
      expect(gameStatusTransitionsService.canStopSession(status), status).toBe(status === 'playing');
    }
  });

  it('завершённая игра read-only: ни начать сессию, ни остановить', () => {
    expect(gameStatusTransitionsService.canStartGame('completed')).toBe(false);
    expect(gameStatusTransitionsService.canStopSession('completed')).toBe(false);
  });
});
