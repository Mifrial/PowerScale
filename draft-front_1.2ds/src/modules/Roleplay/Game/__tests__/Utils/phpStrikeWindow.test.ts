import { describe, expect, it } from 'vitest';
import { phpStrikeWindowMode } from '@/modules/Roleplay/Game/Utils/phpStrikeWindow';

describe('phpStrikeWindowMode', () => {
  it('закрытое окно сбрасывает шаг целиком', () => {
    expect(phpStrikeWindowMode(false, false)).toBe('close');
    expect(phpStrikeWindowMode(false, true)).toBe('close');
  });

  it('окно на серверном ударе включает режим защиты', () => {
    expect(phpStrikeWindowMode(true, true)).toBe('server');
  });

  it('локально объявленный удар не сбрасывается сменой привязки', () => {
    expect(phpStrikeWindowMode(true, false)).toBe('keep');
  });
});
