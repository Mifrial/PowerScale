import { describe, expect, it } from 'vitest';
import { gameTimeToSeconds, normalizeGameTime, gameTimeLabel } from '@/modules/Roleplay/Game/Utils/gameTime';
import type { GameTime } from '@/modules/Roleplay/Game/Dto/GameTime';

function time(partial: Partial<GameTime>): GameTime {
  return { years: 0, months: 0, decades: 0, days: 0, hours: 0, minutes: 0, ...partial };
}

describe('gameTimeToSeconds', () => {
  it('конвертирует каждую единицу в секунды (минута — минимальная)', () => {
    expect(gameTimeToSeconds(time({ minutes: 1 }))).toBe(60);
    expect(gameTimeToSeconds(time({ hours: 1 }))).toBe(3600);
    expect(gameTimeToSeconds(time({ days: 1 }))).toBe(108000);
    expect(gameTimeToSeconds(time({ decades: 1 }))).toBe(1080000);
    expect(gameTimeToSeconds(time({ months: 1 }))).toBe(3240000);
    expect(gameTimeToSeconds(time({ years: 1 }))).toBe(32400000);
  });

  it('суммирует единицы (1 год 1 месяц 1 день 1 час 1 минута)', () => {
    expect(gameTimeToSeconds(time({ years: 1, months: 1, days: 1, hours: 1, minutes: 1 }))).toBe(
      32400000 + 3240000 + 108000 + 3600 + 60,
    );
  });
});

describe('normalizeGameTime', () => {
  it('13 месяцев → 1 год 3 месяца', () => {
    expect(normalizeGameTime(time({ months: 13 }))).toEqual(time({ years: 1, months: 3 }));
  });

  it('15 дней → 1 декада 5 дней', () => {
    expect(normalizeGameTime(time({ days: 15 }))).toEqual(time({ decades: 1, days: 5 }));
  });

  it('75 часов → 2 дня 15 часов', () => {
    expect(normalizeGameTime(time({ hours: 75 }))).toEqual(time({ days: 2, hours: 15 }));
  });

  it('61 минута → 1 час 1 минута', () => {
    expect(normalizeGameTime(time({ minutes: 61 }))).toEqual(time({ hours: 1, minutes: 1 }));
  });

  it('нулевой сдвиг не меняется', () => {
    expect(normalizeGameTime(time({}))).toEqual(time({}));
  });

  it('не меняет уже канонический сдвиг', () => {
    expect(normalizeGameTime(time({ years: 1, days: 4 }))).toEqual(time({ years: 1, days: 4 }));
  });
});

describe('gameTimeLabel', () => {
  it('нулевой сдвиг — «0 минут»', () => {
    expect(gameTimeLabel(time({}))).toBe('0 минут');
  });

  it('«1 год и 4 дня» — только ненулевые единицы', () => {
    expect(gameTimeLabel(time({ years: 1, days: 4 }))).toBe('1 год и 4 дня');
  });

  it('три и более единиц — «X, Y и Z»', () => {
    expect(gameTimeLabel(time({ years: 1, months: 2, days: 4 }))).toBe('1 год, 2 месяца и 4 дня');
  });

  it('русская плюрализация единиц', () => {
    expect(gameTimeLabel(time({ days: 1 }))).toBe('1 день');
    expect(gameTimeLabel(time({ days: 2 }))).toBe('2 дня');
    expect(gameTimeLabel(time({ days: 5 }))).toBe('5 дней');
    expect(gameTimeLabel(time({ days: 11 }))).toBe('11 дней');
    expect(gameTimeLabel(time({ days: 21 }))).toBe('21 день');
    expect(gameTimeLabel(time({ years: 12 }))).toBe('12 лет');
    expect(gameTimeLabel(time({ months: 3 }))).toBe('3 месяца');
    expect(gameTimeLabel(time({ minutes: 1 }))).toBe('1 минута');
    expect(gameTimeLabel(time({ minutes: 4 }))).toBe('4 минуты');
    expect(gameTimeLabel(time({ minutes: 20 }))).toBe('20 минут');
  });
});
