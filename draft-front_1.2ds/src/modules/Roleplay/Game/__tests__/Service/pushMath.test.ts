import { describe, expect, it } from 'vitest';
import { pushMathService } from '@/modules/Roleplay/Game/Service/Instance/pushMathService';

describe('PushMathService', () => {
  it('минимум Устойчивости — вес минус один размер', () => {
    expect(pushMathService.stabilityFloor({ base: 3, size: 0 })).toEqual({ base: 3, size: -1 });
  });

  it('Устойчивость: игнор — минимум, блок — Сила плюс-минус РУ', () => {
    const floor = { base: 3, size: -1 };
    const strength = { base: 5, size: 0 };

    expect(pushMathService.stability('ignore', 5, strength, floor)).toEqual(floor);
    expect(pushMathService.stability('block', 1, strength, floor)).toEqual({ base: 4, size: 0 });
    expect(pushMathService.stability('block', -3, strength, floor)).toEqual({ base: 5, size: 1 });
  });

  it('Неустойчивость — разница Сил с делителем типа урона', () => {
    expect(pushMathService.unstable({ base: 5, size: 0 }, { base: 4, size: 0 })).toBe(1);
    expect(pushMathService.unstable({ base: 5, size: 0 }, { base: 3, size: -1 })).toBe(5);
    expect(pushMathService.unstable({ base: 5, size: 0 }, { base: 3, size: -1 }, 2)).toBe(2);
  });

  it('отброс — четверть Неустойчивости; меньше четырёх — нет отброса', () => {
    expect(pushMathService.knockbackIpari(3)).toBeNull();
    expect(pushMathService.knockbackIpari(4)).toEqual({ base: 1, size: 0 });
    expect(pushMathService.knockbackIpari(13)).toEqual({ base: 3, size: 0 });
  });

  it('урон профиля сдвигается модификатором толчка', () => {
    expect(pushMathService.damageModifier({ base: 4, size: -1 }, -3)).toEqual({ base: 4, size: -2 });
  });
});
