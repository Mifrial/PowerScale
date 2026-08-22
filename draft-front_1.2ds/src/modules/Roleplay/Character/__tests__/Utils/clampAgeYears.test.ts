import { describe, it, expect } from 'vitest';
import { clampAgeYears, type AgeScaleStep } from '@/modules/Roleplay/Character/Utils/clampAgeYears';

const scale: AgeScaleStep[] = [
  { name: 'Молодой', min: 20, max: 30 },
  { name: 'Взрослый', min: 30, max: 40 },
  { name: 'Старый', min: 40, max: null },
];

describe('clampAgeYears', () => {
  it('возраст в пределах ступени — не меняется', () => {
    expect(clampAgeYears(25, scale)).toBeNull();
    expect(clampAgeYears(30, scale)).toBeNull();
  });

  it('ниже первой ступени — зажимается к её минимуму (20)', () => {
    expect(clampAgeYears(15, scale)).toBe(20);
    expect(clampAgeYears(0, scale)).toBe(20);
  });

  it('за верхней границей («Старый») — не меняется', () => {
    expect(clampAgeYears(50, scale)).toBeNull();
  });

  it('без возраста или без шкалы — не меняется', () => {
    expect(clampAgeYears(null, scale)).toBeNull();
    expect(clampAgeYears(25, [])).toBeNull();
  });
});
