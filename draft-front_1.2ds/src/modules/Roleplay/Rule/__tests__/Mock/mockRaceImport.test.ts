import { describe, it, expect } from 'vitest';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { mockRaceImport } from '@/modules/Roleplay/Rule/Mock/mockRaceImport';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';

const byCode = new Map(ruleCatalog.map((r) => [r.code, r]));

const dim = (base: number, size = 0) => ({ base, size });

const raceSpec = (code: string): RaceSpec | undefined => {
  const rule = byCode.get(code);

  return rule?.type === 'race' ? (rule.spec as RaceSpec | undefined) : undefined;
};

describe('mockRaceImport (S6)', () => {
  it('human — вид (species), расы-дети — race с parent human', () => {
    const human = byCode.get('human');
    expect(human?.type).toBe('species');
    expect((human?.spec as { parent_race_code?: string | null } | undefined)?.parent_race_code).toBeNull();

    const codes = mockRaceImport.map((r) => r.code).sort();
    for (const code of ['alierets', 'duariets', 'aneit', 'ahtar', 'orf', 'kogir', 'acelatl', 'ierit', 'aeron']) {
      expect(codes).toContain(code);
      expect(raceSpec(code)?.parent_race_code).toBe('human');
    }
  });

  it('cost_os: Алиерц 10, Аэрон 12, остальные 0', () => {
    expect(raceSpec('alierets')?.cost_os).toBe(10);
    expect(raceSpec('aeron')?.cost_os).toBe(12);
    for (const code of ['duariets', 'aneit', 'ahtar', 'orf', 'kogir', 'acelatl', 'ierit']) {
      expect(raceSpec(code)?.cost_os).toBe(0);
    }
  });

  it('характеристики: база 3 для неуказанных, переопределения из профиля (Выносливость→endurance)', () => {
    const duariets = raceSpec('duariets')?.characteristics;
    expect(duariets).toContainEqual({ characteristic_code: 'endurance', mode: 'fixed', base: { base: 5, size: 0 } });
    // Неуказанные характеристики = 3 средних. Производные (Восприятие/Интеллект) не хранятся
    // в спеке расы — вычисляются как min баз (см. buildCharacteristics).
    for (const code of ['strength', 'dexterity', 'attention', 'reaction', 'memory', 'reasoning']) {
      expect(duariets).toContainEqual({ characteristic_code: code, mode: 'fixed', base: { base: 3, size: 0 } });
    }
    expect(duariets).toHaveLength(7);
    expect(duariets?.some((c) => c.characteristic_code === 'perception' || c.characteristic_code === 'intellect')).toBe(
      false,
    );

    const aneit = raceSpec('aneit')?.characteristics;
    expect(aneit).toContainEqual({ characteristic_code: 'strength', mode: 'fixed', base: { base: 5, size: -1 } });
    expect(aneit).toContainEqual({ characteristic_code: 'magic', mode: 'fixed', base: { base: 4, size: -1 } });
    // magic добавляется только если переопределён; у duariets его нет.
    expect(duariets?.some((c) => c.characteristic_code === 'magic')).toBe(false);
  });

  it('Аэрон: размерные характеристики и cost_os 12', () => {
    expect(raceSpec('aeron')?.cost_os).toBe(12);
    expect(raceSpec('aeron')?.characteristics).toContainEqual({
      characteristic_code: 'memory',
      mode: 'fixed',
      base: { base: 3, size: 1 },
    });
    expect(raceSpec('aeron')?.characteristics).toContainEqual({
      characteristic_code: 'reaction',
      mode: 'fixed',
      base: { base: 3, size: -1 },
    });
    expect(raceSpec('aeron')?.characteristics).toContainEqual({
      characteristic_code: 'endurance',
      mode: 'fixed',
      base: { base: 4, size: -1 },
    });
  });

  it('Ацелатль: бесплатно Быстроногий (расовый automatic)', () => {
    expect(raceSpec('acelatl')?.abilities).toContainEqual({ ability_code: 'fast-footed', automatic: true });
  });

  it('Дюариец: бесплатно Сопротивление холоду 1, приобретаемые Магия и Сопротивление магии с потолками', () => {
    const abilities = raceSpec('duariets')?.abilities ?? [];
    expect(abilities).toContainEqual({ ability_code: 'cold-resistance', automatic: true, parameters: { x: dim(1) } });
    expect(abilities).toContainEqual({
      ability_code: 'magic-potential',
      automatic: false,
      parameters: { x: dim(4) },
    });
    expect(abilities).toContainEqual({
      ability_code: 'magic-resistance',
      automatic: false,
      parameters: { x: dim(2) },
    });
  });

  it('признаки: вид/подвид/раса несут одноимённые признаки (гуманоид, человек, раса)', () => {
    const raceKeywordId: Record<string, number> = {
      alierets: 22,
      duariets: 23,
      aneit: 24,
      ahtar: 25,
      orf: 26,
      kogir: 27,
      acelatl: 28,
      ierit: 29,
      aeron: 30,
    };
    const human = byCode.get('human');
    expect(human?.keywordIds).toContain(17); // humanoid
    expect(human?.keywordIds).toContain(21); // human

    for (const [code, keywordId] of Object.entries(raceKeywordId)) {
      const rule = byCode.get(code);
      expect(rule?.keywordIds).toContain(17); // гуманоид
      expect(rule?.keywordIds).toContain(21); // человек (вид)
      expect(rule?.keywordIds).toContain(keywordId); // собственная раса
    }
  });

  it('другие виды: Дворфы/Орки и расы-дети, эльфийские расы', () => {
    const dwarves = byCode.get('dwarves');
    expect(dwarves?.type).toBe('species');
    expect((dwarves?.spec as { parent_race_code?: string | null } | undefined)?.parent_race_code).toBeNull();

    const turim = raceSpec('turim');
    expect(turim?.parent_race_code).toBe('dwarves');
    expect(turim?.cost_os).toBe(0);

    const orcs = byCode.get('orcs');
    expect(orcs?.type).toBe('species');
    for (const code of ['orgul', 'orhan', 'orzack', 'muukai']) {
      expect(raceSpec(code)?.parent_race_code).toBe('orcs');
      expect(raceSpec(code)?.cost_os).toBe(2);
    }

    // Эльфы: подвид Верто → раса Арилет; Литен/Труул — расы лесных эльфов.
    const verto = byCode.get('verto');
    expect(verto?.type).toBe('species');
    expect(raceSpec('arilet')?.parent_race_code).toBe('verto');
    expect(raceSpec('liten')?.parent_race_code).toBe('wood-elves');
    expect(raceSpec('truul')?.parent_race_code).toBe('wood-elves');
    expect(raceSpec('arilet')?.cost_os).toBe(2);
  });

  it('характеристики орков: размерные значения', () => {
    expect(raceSpec('orgul')?.characteristics).toContainEqual({
      characteristic_code: 'strength',
      mode: 'fixed',
      base: { base: 3, size: 1 },
    });
    // «Интеллект 5↓» в доке — сокращение: хранятся базы Память и Мышление 5↓ (Интеллект = min).
    expect(raceSpec('orgul')?.characteristics).toContainEqual({
      characteristic_code: 'memory',
      mode: 'fixed',
      base: { base: 5, size: -1 },
    });
    expect(raceSpec('orgul')?.characteristics).toContainEqual({
      characteristic_code: 'reasoning',
      mode: 'fixed',
      base: { base: 5, size: -1 },
    });
    expect(raceSpec('orhan')?.characteristics).toContainEqual({
      characteristic_code: 'dexterity',
      mode: 'fixed',
      base: { base: 3, size: -2 },
    });
  });

  it('параметрические способности рас: автоматические несут значение, доступные — потолок', () => {
    const autoMr = (code: string) =>
      raceSpec(code)?.abilities.find((a) => a.ability_code === 'magic-resistance' && a.automatic);
    const avail = (code: string, ability: string) =>
      raceSpec(code)?.abilities.find((a) => a.ability_code === ability && !a.automatic);

    // Дюариец: Сопротивление холоду 1 автоматически, Магия 4 и Сопротивление магии 2 — доступные.
    expect(raceSpec('duariets')?.abilities).toContainEqual({
      ability_code: 'cold-resistance',
      automatic: true,
      parameters: { x: dim(1) },
    });

    // Аэрон: бесплатно Сопротивление магии 2, опции до 5; Магия 3.
    expect(autoMr('aeron')?.parameters).toEqual({ x: dim(2) });
    expect(avail('aeron', 'magic-resistance')?.parameters).toEqual({ x: dim(5) });
    expect(avail('aeron', 'magic-potential')?.parameters).toEqual({ x: dim(3) });

    // Эльфы: бесплатно Сопротивление магии 1, доступны Магия 4 и Сопротивление магии 3.
    for (const code of ['arilet', 'liten', 'truul']) {
      expect(autoMr(code)?.parameters).toEqual({ x: dim(1) });
      expect(avail(code, 'magic-resistance')?.parameters).toEqual({ x: dim(3) });
      expect(avail(code, 'magic-potential')?.parameters).toEqual({ x: dim(4) });
    }

    // Турим: Магия 5.
    expect(avail('turim', 'magic-potential')?.parameters).toEqual({ x: dim(5) });

    // Орки: автоматическое Сопротивление магии 1/2/3, Магия 3.
    expect(autoMr('orgul')?.parameters).toEqual({ x: dim(1) });
    expect(autoMr('orhan')?.parameters).toEqual({ x: dim(2) });
    expect(autoMr('orzack')?.parameters).toEqual({ x: dim(3) });
    for (const code of ['orgul', 'orhan', 'orzack']) {
      expect(avail(code, 'magic-potential')?.parameters).toEqual({ x: dim(3) });
    }

    // Му’укай: Сопротивление магии 1, доступной Магии нет.
    expect(autoMr('muukai')?.parameters).toEqual({ x: dim(1) });
    expect(raceSpec('muukai')?.abilities.some((a) => a.ability_code === 'magic-potential')).toBe(false);
  });
});
