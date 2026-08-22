import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RaceSpec } from '@/modules/Roleplay/Rule/Dto/Race/RaceSpec';
import type { RaceAbilityRef } from '@/modules/Roleplay/Rule/Dto/Race/RaceAbilityRef';
import type { RaceCharacteristic } from '@/modules/Roleplay/Rule/Dto/Race/RaceCharacteristic';

/**
 * Импорт видов/рас из docs/rule/AI.html (Фаза 2, S6).
 * Структура: human — вид (species), расы-дети — race с parent 'human'.
 *
 * Маппинг характеристик (№1):
 * - «Выносливость» → endurance (Стойкость); Стойкость/Ловкость/Сила — напрямую;
 *   Восприятие = min(Внимательность, Реакция); Интеллект = min(Память, Мышление) (производные).
 * - Правило профиля: если характеристика в профиле не указана — она равна 3 средних (base 3, size 0).
 *   Профиль задаёт только те характеристики, что переопределены.
 * - Размерность: «5↓» = base 5, size −1 (меньше среднего); «3↑» = base 3, size +1.
 *
 * Способности: только существующие в каталоге (automatic: true = расовый automatic).
 * Параметрические (Сопротивление магии X, Сопротивление холоду X, Магия X) — с потолком/значением
 * параметра `x` из докла. Черты, которых нет в каталоге (Угольное тело, Долгожитель…) — отложены,
 * не ссылаемся (иначе падает валидация ссылок).
 */

let nextId = 116;

const dim = (base: number, size = 0) => ({ base, size });

/** Потолок/значение параметра «X» способности у расы. */
const x = (base: number): Record<string, { base: number; size: number }> => ({ x: dim(base) });

/** Признаки вида/расы: гуманоид + вид (human) + собственная раса. */
const HUMAN_RACE_KEYWORDS: Record<string, number[]> = {
  alierets: [17, 21, 22],
  duariets: [17, 21, 23],
  aneit: [17, 21, 24],
  ahtar: [17, 21, 25],
  orf: [17, 21, 26],
  kogir: [17, 21, 27],
  acelatl: [17, 21, 28],
  ierit: [17, 21, 29],
  aeron: [17, 21, 30],
};

const S3 = (characteristic_code: string): RaceCharacteristic => ({
  characteristic_code,
  mode: 'fixed',
  base: dim(3),
});

const FIXED = (characteristic_code: string, base: number, size = 0): RaceCharacteristic => ({
  characteristic_code,
  mode: 'fixed',
  base: dim(base, size),
});

/** Базовые характеристики человека: все 3 средних (док: «если не указана — равна 3»). */
const baseHumanCharacteristics = (
  overrides: Record<string, { base: number; size?: number }> = {},
): RaceCharacteristic[] => {
  // Производные (Восприятие/Интеллект) не хранятся — вычисляются как min баз (см. формулы характеристик).
  const codes = ['strength', 'endurance', 'dexterity', 'attention', 'reaction', 'memory', 'reasoning'];
  const result = codes.map((code) => {
    const value = overrides[code];
    if (value) return FIXED(code, value.base, value.size ?? 0);

    return S3(code);
  });
  if (overrides.magic) result.push(FIXED('magic', overrides.magic.base, overrides.magic.size ?? 0));

  return result;
};

/** Раса-человек: parent human, cost_os, характеристики (база 3 + переопределения), способности. */
const humanRace = (
  code: string,
  name: string,
  description: string,
  cost_os: number,
  overrides: Record<string, { base: number; size?: number }> = {},
  abilities: RaceAbilityRef[] = [],
): Rule => {
  const spec: RaceSpec = {
    parent_race_code: 'human',
    cost_os,
    characteristics: baseHumanCharacteristics(overrides),
    abilities,
  };

  return {
    id: `rule-${nextId++}`,
    code,
    type: 'race',
    name,
    description,
    spaceId: 1,
    spec,
    keywordIds: HUMAN_RACE_KEYWORDS[code] ?? [17, 21],
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-07T10:00:00Z',
  };
};

/** Вид/подвид (species): узел дерева с одноимённым признаком. */
const speciesRule = (
  code: string,
  name: string,
  description: string,
  parent: string | null,
  keywordIds: number[],
  ageYears?: { age: string; ageStart: number; ageEnd: number }[],
): Rule => ({
  id: `rule-${nextId++}`,
  code,
  type: 'species',
  name,
  description,
  spaceId: 1,
  spec: {
    parent_race_code: parent,
    abilities: [],
    ...(ageYears ? { age_years: ageYears } : {}),
  },
  keywordIds,
  mechanicId: null,
  mechanic_payload: null,
  createdAt: '2026-08-07T10:00:00Z',
});

/** Раса произвольного вида: база 3 + переопределения, признаки [гуманоид, вид, раса]. */
const raceRule = (
  code: string,
  name: string,
  description: string,
  parent: string,
  cost_os: number,
  overrides: Record<string, { base: number; size?: number }> = {},
  keywordIds: number[],
  abilities: RaceAbilityRef[] = [],
): Rule => {
  const spec: RaceSpec = {
    parent_race_code: parent,
    cost_os,
    characteristics: baseHumanCharacteristics(overrides),
    abilities,
  };

  return {
    id: `rule-${nextId++}`,
    code,
    type: 'race',
    name,
    description,
    spaceId: 1,
    spec,
    keywordIds,
    mechanicId: null,
    mechanic_payload: null,
    createdAt: '2026-08-07T10:00:00Z',
  };
};

/**
 * Расы Человека (профили основ из докла). Профиль задаёт только переопределённые характеристики;
 * остальные = 3 средних. «Магия 5» как Опция/Доступная — расовая доступность/характеристика (S9).
 */
export const mockRaceImport: Rule[] = [
  // Алиерц — 10 ОС, Выносливость 5, доступна Магия 5
  humanRace(
    'alierets',
    'Алиерц',
    'Вид человека с выдающимися генами: 10 ОС, Выносливость 5, доступна Магия.',
    10,
    { endurance: { base: 5 } },
    [{ ability_code: 'magic-potential', automatic: false, parameters: x(5) }],
  ),
  // Дюариец — 0 ОС, Выносливость 5; бесплатно Сопротивление холоду; доступны Магия 4, Сопротивление магии 1-2
  humanRace(
    'duariets',
    'Дюариец',
    'Выносливый северянин: Выносливость 5, бесплатно Сопротивление холоду, доступны Магия и Сопротивление магии.',
    0,
    { endurance: { base: 5 } },
    [
      { ability_code: 'cold-resistance', automatic: true, parameters: x(1) },
      { ability_code: 'magic-potential', automatic: false, parameters: x(4) },
      { ability_code: 'magic-resistance', automatic: false, parameters: x(2) },
    ],
  ),
  // Анеит — 0 ОС, Выносливость 5, Сила 5↓, Магия 4↓; доступна Магия 5
  humanRace(
    'aneit',
    'Анеит',
    'Вид человека: Выносливость 5, Сила 5↓, Магия 4↓, доступна Магия.',
    0,
    {
      endurance: { base: 5 },
      strength: { base: 5, size: -1 },
      magic: { base: 4, size: -1 },
    },
    [{ ability_code: 'magic-potential', automatic: false, parameters: x(5) }],
  ),
  // Ахтар — 0 ОС, Выносливость 5, Магия 4↓; бесплатно Сопротивление магии 2; доступна Магия 5
  humanRace(
    'ahtar',
    'Ахтар',
    'Вид человека: Выносливость 5, Магия 4↓, бесплатно Сопротивление магии, доступна Магия.',
    0,
    {
      endurance: { base: 5 },
      magic: { base: 4, size: -1 },
    },
    [
      { ability_code: 'magic-resistance', automatic: true, parameters: x(2) },
      { ability_code: 'magic-potential', automatic: false, parameters: x(5) },
    ],
  ),
  // Орф — 0 ОС, Выносливость 5; доступна Магия 5
  humanRace('orf', 'Орф', 'Вид человека: Выносливость 5, доступна Магия.', 0, { endurance: { base: 5 } }, [
    { ability_code: 'magic-potential', automatic: false, parameters: x(5) },
  ]),
  // Когир — 0 ОС, Выносливость 5, Сила 4
  humanRace('kogir', 'Когир', 'Вид человека: Выносливость 5, Сила 4.', 0, {
    endurance: { base: 5 },
    strength: { base: 4 },
  }),
  // Ацелатль — 0 ОС, Выносливость 5, бесплатно «Быстроногий», доступна Магия 4
  humanRace(
    'acelatl',
    'Ацелатль',
    'Вид человека: Выносливость 5, бесплатно Быстроногий, доступна Магия.',
    0,
    { endurance: { base: 5 } },
    [
      { ability_code: 'fast-footed', automatic: true },
      { ability_code: 'magic-potential', automatic: false, parameters: x(4) },
    ],
  ),
  // Иерит — 0 ОС, Выносливость 5; доступна Магия 5
  humanRace('ierit', 'Иерит', 'Вид человека: Выносливость 5, доступна Магия.', 0, { endurance: { base: 5 } }, [
    { ability_code: 'magic-potential', automatic: false, parameters: x(5) },
  ]),
  // Аэрон — 12 ОС, Выносливость 4↓, Память 3↑, Реакция 3↓; бесплатно Сопротивление магии; доступна Магия
  humanRace(
    'aeron',
    'Аэрон',
    'Вид человека с долгой жизнью: 12 ОС, Выносливость 4↓, Память 3↑, Реакция 3↓.',
    12,
    {
      endurance: { base: 4, size: -1 },
      memory: { base: 3, size: 1 },
      reaction: { base: 3, size: -1 },
    },
    [
      { ability_code: 'magic-resistance', automatic: true, parameters: x(2) },
      { ability_code: 'magic-resistance', automatic: false, parameters: x(5) },
      { ability_code: 'magic-potential', automatic: false, parameters: x(3) },
    ],
  ),

  // --- Эльфы (вид elves уже есть в ruleCatalog) ---
  // Верто — подвид лесных эльфов; его раса — Арилет (громовое древо).
  speciesRule('verto', 'Верто', 'Вековые эльфы.', 'wood-elves', [17, 18, 19]),
  raceRule(
    'arilet',
    'Арилет',
    'Эльф громового древа: Сила 5↓, Стойкость 5↓, Выносливость 5, доступна Магия.',
    'verto',
    2,
    { endurance: { base: 5 }, strength: { base: 5, size: -1 } },
    [17, 18, 19, 39],
    [
      { ability_code: 'magic-resistance', automatic: true, parameters: x(1) },
      { ability_code: 'magic-resistance', automatic: false, parameters: x(3) },
      { ability_code: 'magic-potential', automatic: false, parameters: x(4) },
    ],
  ),
  // Литен и Труул — расы лесных эльфов (parent wood-elves).
  raceRule(
    'liten',
    'Литен',
    'Эльф резинового древа: Сила 5↓, Стойкость 5↓, Выносливость 4, Ловкость 4, доступна Магия.',
    'wood-elves',
    2,
    { endurance: { base: 4 }, dexterity: { base: 4 }, strength: { base: 5, size: -1 } },
    [17, 18, 19, 40],
    [
      { ability_code: 'magic-resistance', automatic: true, parameters: x(1) },
      { ability_code: 'magic-resistance', automatic: false, parameters: x(3) },
      { ability_code: 'magic-potential', automatic: false, parameters: x(4) },
    ],
  ),
  raceRule(
    'truul',
    'Труул',
    'Торфяной эльф: Сила 5↓, Стойкость 5↓, Выносливость 5, доступна Магия.',
    'wood-elves',
    2,
    { endurance: { base: 5 }, strength: { base: 5, size: -1 } },
    [17, 18, 19, 41],
    [
      { ability_code: 'magic-resistance', automatic: true, parameters: x(1) },
      { ability_code: 'magic-resistance', automatic: false, parameters: x(3) },
      { ability_code: 'magic-potential', automatic: false, parameters: x(4) },
    ],
  ),

  // --- Дворфы (вид) ---
  speciesRule(
    'dwarves',
    'Дворфы',
    'Гуманоид, дворф.',
    null,
    [17, 32],
    [
      { age: 'Младенец', ageStart: 0, ageEnd: 4 },
      { age: 'Малыш', ageStart: 4, ageEnd: 7 },
      { age: 'Ребёнок', ageStart: 7, ageEnd: 14 },
      { age: 'Подросток', ageStart: 14, ageEnd: 20 },
      { age: 'Молодой', ageStart: 20, ageEnd: 40 },
      { age: 'Взрослый', ageStart: 40, ageEnd: 70 },
      { age: 'Зрелый', ageStart: 70, ageEnd: 90 },
      { age: 'Пожилой', ageStart: 90, ageEnd: 120 },
    ],
  ),
  raceRule(
    'turim',
    'Турим',
    'Дворф: Стойкость 4, Выносливость 5, бесплатно Пиворождённый/Маленький шаг/Темновидение, доступна Магия.',
    'dwarves',
    0,
    { endurance: { base: 5 } },
    [17, 32, 34],
    [
      { ability_code: 'beerborn', automatic: true },
      { ability_code: 'small-step', automatic: true },
      { ability_code: 'dark-vision', automatic: true },
      { ability_code: 'magic-potential', automatic: false, parameters: x(5) },
    ],
  ),

  // --- Орки (вид) ---
  speciesRule(
    'orcs',
    'Орки',
    'Гуманоид, орк.',
    null,
    [17, 33],
    [
      { age: 'Младенец', ageStart: 0, ageEnd: 3 },
      { age: 'Малыш', ageStart: 3, ageEnd: 5 },
      { age: 'Ребёнок', ageStart: 5, ageEnd: 10 },
      { age: 'Подросток', ageStart: 10, ageEnd: 15 },
      { age: 'Молодой', ageStart: 15, ageEnd: 20 },
      { age: 'Взрослый', ageStart: 20, ageEnd: 30 },
      { age: 'Зрелый', ageStart: 30, ageEnd: 40 },
      { age: 'Пожилой', ageStart: 40, ageEnd: 50 },
    ],
  ),
  raceRule(
    'orgul',
    'Оргул',
    'Орк: Сила 3↑, Стойкость 3↑, Ловкость 3↓, Интеллект 5↓, бесплатно Большой/Быстроногий/Сопротивление магии, доступна Магия.',
    'orcs',
    2,
    {
      strength: { base: 3, size: 1 },
      endurance: { base: 3, size: 1 },
      dexterity: { base: 3, size: -1 },
      memory: { base: 5, size: -1 },
      reasoning: { base: 5, size: -1 },
    },
    [17, 33, 35],
    [
      { ability_code: 'big-build', automatic: true },
      { ability_code: 'fast-footed', automatic: true },
      { ability_code: 'magic-resistance', automatic: true, parameters: x(1) },
      { ability_code: 'magic-potential', automatic: false, parameters: x(3) },
      { ability_code: 'seeing', automatic: false },
      { ability_code: 'scenting', automatic: false },
    ],
  ),
  raceRule(
    'orhan',
    'Орхан',
    'Орк: Сила 4↑, Стойкость 4↑, Ловкость 3↓↓, Интеллект 5↓.',
    'orcs',
    2,
    {
      strength: { base: 4, size: 1 },
      endurance: { base: 4, size: 1 },
      dexterity: { base: 3, size: -2 },
      memory: { base: 5, size: -1 },
      reasoning: { base: 5, size: -1 },
    },
    [17, 33, 36],
    [
      { ability_code: 'big-build', automatic: true },
      { ability_code: 'magic-resistance', automatic: true, parameters: x(2) },
      { ability_code: 'magic-potential', automatic: false, parameters: x(3) },
      { ability_code: 'scenting', automatic: false },
    ],
  ),
  raceRule(
    'orzack',
    'Орзак',
    'Орк: Сила 3↑, Стойкость 3↑, Ловкость 4↓, Интеллект 5↓.',
    'orcs',
    2,
    {
      strength: { base: 3, size: 1 },
      endurance: { base: 3, size: 1 },
      dexterity: { base: 4, size: -1 },
      memory: { base: 5, size: -1 },
      reasoning: { base: 5, size: -1 },
    },
    [17, 33, 37],
    [
      { ability_code: 'big-build', automatic: true },
      { ability_code: 'magic-resistance', automatic: true, parameters: x(3) },
      { ability_code: 'magic-potential', automatic: false, parameters: x(3) },
    ],
  ),
  raceRule(
    'muukai',
    'Му’укай',
    'Орк: Сила 3↑, Стойкость 3↑, Ловкость 3↓, Магия 3.',
    'orcs',
    2,
    {
      strength: { base: 3, size: 1 },
      endurance: { base: 3, size: 1 },
      dexterity: { base: 3, size: -1 },
      magic: { base: 3 },
    },
    [17, 33, 38],
    [
      { ability_code: 'big-build', automatic: true },
      { ability_code: 'magic-resistance', automatic: true, parameters: x(1) },
    ],
  ),
];
