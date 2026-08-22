import { describe, it, expect } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { buildRaceCharacteristicLabels } from '@/modules/Roleplay/Character/Utils/raceCharacteristicLabels';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import type { RaceCharacteristic } from '@/modules/Roleplay/Rule/Dto/Race/RaceCharacteristic';

const specOf = (code: string): RaceSpec => ruleCatalog.find((r) => r.code === code)?.spec as RaceSpec;
const labelsOf = (code: string) => buildRaceCharacteristicLabels(specOf(code), ruleCatalog);

describe('buildRaceCharacteristicLabels', () => {
  it('человек: производные одним значением, базы, равные минимуму производной, скрыты', () => {
    const labels = labelsOf('alierets');

    expect(labels).toContainEqual({ name: 'Восприятие', label: '3' });
    expect(labels).toContainEqual({ name: 'Интеллект', label: '3' });
    expect(labels.some((l) => l.name === 'Внимательность')).toBe(false);
    expect(labels.some((l) => l.name === 'Память')).toBe(false);
    expect(labels.some((l) => l.label.includes(' до '))).toBe(false);
  });

  it('орк: «Интеллект 5↓» из баз Память/Мышление, без вырожденных диапазонов', () => {
    const labels = labelsOf('orgul');

    expect(labels).toContainEqual({ name: 'Интеллект', label: '5↓' });
    expect(labels).toContainEqual({ name: 'Восприятие', label: '3' });
    expect(labels.some((l) => l.name === 'Память' || l.name === 'Мышление')).toBe(false);
  });

  it('аэрон: производные с размерностью, базы, отличные от минимума, видны', () => {
    const labels = labelsOf('aeron');

    expect(labels).toContainEqual({ name: 'Восприятие', label: '3↓' });
    expect(labels).toContainEqual({ name: 'Интеллект', label: '3' });
    expect(labels).toContainEqual({ name: 'Память', label: '3↑' });
    expect(labels.some((l) => l.name === 'Мышление')).toBe(false);
  });

  it('докупаемая характеристика: диапазон «от base до max»', () => {
    const strength: RaceCharacteristic = {
      characteristic_code: 'strength',
      mode: 'purchased',
      base: { base: 3, size: 0 },
      purchase: [
        { value: { base: 3, size: 0 }, cost: 0 },
        { value: { base: 5, size: 0 }, cost: 4 },
      ],
    };
    const spec: RaceSpec = { parent_race_code: null, cost_os: 0, characteristics: [strength], abilities: [] };

    expect(buildRaceCharacteristicLabels(spec, ruleCatalog)).toContainEqual({ name: 'Сила', label: 'от 3 до 5' });
  });

  it('база 5↓ (значение 2.5) не скрывается минимумом производной 2 (toNumber-floor-баг)', () => {
    const memory: RaceCharacteristic = {
      characteristic_code: 'memory',
      mode: 'fixed',
      base: { base: 5, size: -1 },
    };
    const reasoning: RaceCharacteristic = {
      characteristic_code: 'reasoning',
      mode: 'fixed',
      base: { base: 2, size: 0 },
    };
    const spec: RaceSpec = {
      parent_race_code: null,
      cost_os: 0,
      characteristics: [memory, reasoning],
      abilities: [],
    };

    const labels = buildRaceCharacteristicLabels(spec, ruleCatalog);
    expect(labels).toContainEqual({ name: 'Память', label: '5↓' });
    expect(labels).toContainEqual({ name: 'Интеллект', label: '2' });
    // Мышление 2 равно минимуму производной — скрыта (D85).
    expect(labels.some((l) => l.name === 'Мышление')).toBe(false);
  });
});
