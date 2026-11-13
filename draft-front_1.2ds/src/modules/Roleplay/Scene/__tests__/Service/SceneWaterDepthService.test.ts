import { describe, expect, it } from 'vitest';
import { signedDistanceField } from '@/modules/Roleplay/Scene/Service/SceneWaterDepthService';

/** Маска из строк: `#` — вода, `.` — берег. */
const maskOf = (rows: string[]): { mask: Float32Array; cols: number; rows: number } => {
  const cols = rows[0].length;
  const mask = new Float32Array(cols * rows.length);
  rows.forEach((row, y) => {
    for (let x = 0; x < cols; x += 1) {
      mask[y * cols + x] = row[x] === '#' ? 1 : 0;
    }
  });

  return { mask, cols, rows: rows.length };
};

describe('signedDistanceField', () => {
  it('держит знак: плюс внутри воды, минус на берегу', () => {
    const { mask, cols, rows } = maskOf(['.....', '.###.', '.###.', '.###.', '.....']);
    const signed = signedDistanceField(mask, cols, rows);

    expect(signed[2 * cols + 2]).toBeGreaterThan(0);
    expect(signed[0]).toBeLessThan(0);
  });

  it('отсчитывает урез между клетками, а не по их центрам', () => {
    const { mask, cols, rows } = maskOf(['##..', '##..', '##..', '##..']);
    const signed = signedDistanceField(mask, cols, rows);

    // Последняя клетка воды и первая клетка берега равноудалены от уреза.
    expect(signed[1]).toBeCloseTo(0.5);
    expect(signed[2]).toBeCloseTo(-0.5);
  });

  it('растёт вглубь на полклетки на каждую клетку', () => {
    const { mask, cols, rows } = maskOf(['.....', '.###.', '.###.', '.###.', '.....']);
    const signed = signedDistanceField(mask, cols, rows);

    expect(signed[1 * cols + 1]).toBeCloseTo(0.5);
    expect(signed[2 * cols + 2]).toBeCloseTo(1.5);
  });

  it('не отдаёт бесконечность на маске без берега или без воды', () => {
    const full = maskOf(['###', '###', '###']);
    const empty = maskOf(['...', '...', '...']);

    for (const value of signedDistanceField(full.mask, full.cols, full.rows)) {
      expect(Number.isFinite(value)).toBe(true);
      expect(value).toBeGreaterThan(0);
    }

    for (const value of signedDistanceField(empty.mask, empty.cols, empty.rows)) {
      expect(Number.isFinite(value)).toBe(true);
      expect(value).toBeLessThan(0);
    }
  });
});
