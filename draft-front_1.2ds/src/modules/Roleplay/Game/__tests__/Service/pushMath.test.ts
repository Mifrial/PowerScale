import { describe, expect, it } from 'vitest';
import { pushMathService } from '@/modules/Roleplay/Game/Service/Instance/pushMathService';

describe('PushMathService', () => {
  it('пол толчка — вес минус два размера', () => {
    expect(pushMathService.pushFloor({ base: 3, size: 0 })).toEqual({ base: 3, size: -2 });
  });

  it('блок поднимает успехи цели до пола', () => {
    expect(pushMathService.raiseDefenderSuccesses({ base: 1, size: -2 }, { base: 3, size: -2 })).toEqual({
      base: 3,
      size: -2,
    });
  });

  it('урон — успехи Силы минус размер, отброс — РУ минус два размера', () => {
    expect(pushMathService.crushingDamage({ base: 4, size: 0 })).toEqual({ base: 4, size: -1 });
    expect(pushMathService.knockbackIpari(4)).toEqual({ base: 4, size: -2 });
  });

  it('НУЛЕВОЙ РУ не даёт эффекта', () => {
    expect(pushMathService.successRating({ base: 2, size: 0 }, { base: 3, size: 0 })).toBe(0);
  });

  it('РУ считает размер, а не toNumber', () => {
    expect(pushMathService.successRating({ base: 4, size: 0 }, { base: 3, size: -2 })).toBeGreaterThan(0);
  });

  it('делитель позы режет РУ отброса, не обнуляет при полном РУ', () => {
    expect(pushMathService.postureRating(0, 2)).toBe(0);
    expect(pushMathService.postureRating(1, 2)).toBe(0);
    expect(pushMathService.postureRating(3, 2)).toBe(1);
    expect(pushMathService.postureRating(3, 1)).toBe(3);
  });
});
