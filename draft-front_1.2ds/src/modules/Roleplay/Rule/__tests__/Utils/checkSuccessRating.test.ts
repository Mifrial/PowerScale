import { describe, expect, it } from 'vitest';
import { checkSuccessRating } from '@/modules/Roleplay/Rule/Utils/checkSuccessRating';

describe('checkSuccessRating', () => {
  it('пример: {3|1} против {2|1} — успех, РУ 1', () => {
    expect(checkSuccessRating({ base: 3, size: 1 }, { base: 2, size: 1 })).toEqual({ passed: true, rating: 1 });
  });

  it('приводит обе стороны к меньшему размеру: 2 больших = 4 средних', () => {
    expect(checkSuccessRating({ base: 3, size: 1 }, { base: 4, size: 0 })).toEqual({ passed: true, rating: 2 });
  });

  it('1↓ против 5: 5 → 10↓, РУ −9 (не дробь −4.5)', () => {
    expect(checkSuccessRating({ base: 1, size: -1 }, { base: 5, size: 0 })).toEqual({ passed: false, rating: -9 });
    expect(checkSuccessRating({ base: 5, size: 0 }, { base: 1, size: -1 })).toEqual({ passed: true, rating: 9 });
  });

  it('провал, если успехов меньше сложности', () => {
    expect(checkSuccessRating({ base: 1, size: 1 }, { base: 2, size: 1 }).passed).toBe(false);
  });
});
