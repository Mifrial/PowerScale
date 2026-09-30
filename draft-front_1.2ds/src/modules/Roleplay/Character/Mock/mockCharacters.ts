import type { Character } from '@/modules/Roleplay/Character/Dto/Character';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { CharacterStatus } from '@/modules/Roleplay/Character/Enum/CharacterStatus';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import type { UpdateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/UpdateCharacterData';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { AddCustomRuleData } from '@/modules/Roleplay/Character/Dto/AddCustomRuleData';
import type { UpdateCustomRuleData } from '@/modules/Roleplay/Character/Dto/UpdateCustomRuleData';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { getCurrentUserId, getCurrentUserSummary } from '@/modules/Core/Auth/Mock/mockAuth';
import { mockCreateCharacterDiscussion } from '@/modules/Messages/Chat/Mock/mockChat';
import { SHEET_VISIBILITY_DEFAULT } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_VISIBILITY_PRESETS';
import { fetchRevision, fetchSpace, fetchSpaceByCode } from '@/modules/Roleplay/RuleSpace/Mock/mockSpaces';
import { characterMigrationService } from '@/modules/Roleplay/Character/Service/Instance/characterMigrationService';
import { characterVersionIntegrityService } from '@/modules/Roleplay/Character/Service/Instance/characterVersionIntegrityService';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { characterPatchService } from '@/modules/Roleplay/Character/Service/Instance/characterPatchService';
import type { MigrationResult } from '@/modules/Roleplay/Character/Dto/MigrationResult';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';
import type { CharacterBuild } from '@/modules/Roleplay/Character/Dto/Editor/CharacterBuild';
import type { CharacterCreationConfig } from '@/modules/Roleplay/Character/Dto/Editor/CharacterCreationConfig';
import type { CharacterPatch } from '@/modules/Roleplay/Character/Dto/CharacterPatch';
import type { CharacterCreateRequest } from '@/modules/Roleplay/Character/Dto/CharacterCreateRequest';
import type { CharacterValidateRequest } from '@/modules/Roleplay/Character/Dto/CharacterValidateRequest';
import type { CharacterValidationResult } from '@/modules/Roleplay/Character/Dto/CharacterValidationResult';
import type { CharacterValidationProblem } from '@/modules/Roleplay/Character/Dto/CharacterValidationProblem';
import { CharacterApiError } from '@/modules/Roleplay/Character/Service/CharacterApiError';

// База характеристики — размерное число: база (3–5) + размерность (для базовых 0).
// Итог не хранится: считается в карточке как база + модификаторы.
const dim = (base: number, size = 0): DimensionalNumberValue => ({ base, size });

function defaultVisibility(): SheetVisibility {
  return SHEET_VISIBILITY_DEFAULT.map((rule) => ({ audience: rule.audience, sections: [...rule.sections] }));
}

// Фикстуры персонажей: разные статусы, свободные и в игре, черновик одного владельца.
// Денормализованные поля (raceLabel, ownerName, gameName, currentPoints) — представление списка.
// Инвариант привязки (БД §3): space_id + rules_version_at. В фикстурах (spaceId, spaceCode,
// rulesRevision) согласованы с моками Space: spaceId 1 = razrabotka (revision 5, rulesRevision ≤ 5),
// spaceId 2 = actual (revision 12, rulesRevision ≤ 12).
// Версия ссылается на правила каталога (Rule) только ruleCode; каждое правило обязано разрешаться
// в ревизии (Mock/mockSpaces: предметы/расы/виды всегда входят в срез ревизии).
export const characters: Character[] = [
  {
    id: 1,
    name: 'Торвин Стальной Кулак',
    status: 'ready',
    active: true,
    ownerId: 1,
    ownerName: 'Иван Петров',
    raceId: 6,
    raceLabel: 'Человек',
    gameId: null,
    gameName: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    shortDescription: 'Кузнец-ветеран, помнит падение старого цитадели.',
    visibility: defaultVisibility(),
    currentPoints: { os: 30, ol: 0, or: 12 },
    discussionChatId: 7,
  },
  {
    id: 2,
    name: 'Элиандра Тенелист',
    status: 'draft',
    active: true,
    ownerId: 2,
    ownerName: 'Администратор',
    raceId: 29,
    raceLabel: 'Эльф',
    gameId: null,
    gameName: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 4,
    shortDescription: 'Следопыт в изгнании, охотится на лесных призраков.',
    visibility: defaultVisibility(),
    currentPoints: { os: 25, ol: 3, or: 5 },
    discussionChatId: 14,
  },
  {
    id: 3,
    name: 'Гаррик из Тени',
    status: 'ready',
    active: true,
    ownerId: 2,
    ownerName: 'Администратор',
    raceId: 122,
    raceLabel: 'Ацелатль',
    gameId: 1,
    gameName: 'Забытые земли',
    spaceId: 2,
    spaceCode: 'actual',
    rulesRevision: 6,
    shortDescription: 'Ловкий карманник с сомнительной репутацией.',
    // Демо: в игре игроки видят только имя и краткое описание.
    visibility: [{ audience: 'all', sections: ['shortDescription'] }],
    currentPoints: { os: 20, ol: 0, or: 18 },
    discussionChatId: 20,
  },
  {
    id: 4,
    name: 'Морган Мёртвый Глаз',
    status: 'ready',
    active: true,
    ownerId: 3,
    ownerName: 'Пётр Козлов',
    raceId: 6,
    raceLabel: 'Человек',
    gameId: 1,
    gameName: 'Забытые земли',
    spaceId: 2,
    spaceCode: 'actual',
    rulesRevision: 11,
    shortDescription: 'Бывший наёмник, лишённый глаза за предательство.',
    visibility: defaultVisibility(),
    currentPoints: { os: 30, ol: 0, or: 7 },
    discussionChatId: null,
  },
  {
    id: 5,
    name: 'Серафина Древний Ветер',
    status: 'ready',
    active: false,
    ownerId: 1,
    ownerName: 'Иван Петров',
    raceId: 30,
    raceLabel: 'Лесной эльф',
    gameId: null,
    gameName: null,
    spaceId: 1,
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    shortDescription: 'Посланница торговой гильдии, отвечает за границы.',
    visibility: defaultVisibility(),
    currentPoints: { os: 27, ol: 2, or: 10 },
    discussionChatId: null,
  },
];

// Версия хранит ссылки (ruleCode), базы характеристик и модификаторы; значения считаются в карточке
// (база + модификаторы). Ресурс: значение и базовый лимит — размерные числа (безразмерный — размер 0)
// + бонусы/штрафы; лимит = база + сумма дельт, лимит 0 — ресурса у персонажа нет. Очки (points: ОС/ОЛ/ОР)
// и деньги (money) — данные персонажа, блок «Разное». Имена/формулы правил разрешаются из ревизии при отрисовке.
export const versions: Record<number, CharacterVersion> = {
  1: {
    name: 'Торвин Стальной Кулак',
    shortDescription: 'Кузнец-ветеран, помнит падение старого цитадели.',
    fullDescription:
      'Торвин — последний из гильдии горных кузнецов, переживших осаду старой цитадели. Он куёт оружие из обломков крепостных ворот и хранит карту подвалов, куда не ступала нога десять лет.',
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleCode: 'human',
    characteristics: [
      { ruleCode: 'strength', base: dim(5), modifiers: [] },
      { ruleCode: 'endurance', base: dim(3, 1), modifiers: [] },
      { ruleCode: 'dexterity', base: dim(3, 3), modifiers: [] },
      { ruleCode: 'perception', base: dim(3), modifiers: [] },
      { ruleCode: 'intellect', base: dim(3), modifiers: [] },
      { ruleCode: 'attention', base: dim(5), modifiers: [] },
      { ruleCode: 'reaction', base: dim(3), modifiers: [] },
      { ruleCode: 'memory', base: dim(3), modifiers: [] },
      { ruleCode: 'reasoning', base: dim(3), modifiers: [] },
      {
        ruleCode: 'communication',
        base: dim(3),
        modifiers: [
          {
            sourceRuleCode: null,
            sourceLabel: 'Обстоятельства',
            delta: 1,
            target: 'communication',
            scope: 'проверки на обман',
          },
        ],
      },
      { ruleCode: 'willpower', base: dim(4), modifiers: [] },
      {
        ruleCode: 'melee-combat',
        base: dim(3, -1),
        modifiers: [
          { sourceRuleCode: 'blizhniy-boy', sourceLabel: null, delta: 1, target: 'melee-combat', scope: null },
        ],
      },
      { ruleCode: 'magic-power', base: dim(3), modifiers: [] },
      { ruleCode: 'magic-control', base: dim(3, -1), modifiers: [] },
    ],
    resources: [
      { ruleCode: 'action-points', current: dim(4), base: dim(4), bonuses: [] },
      { ruleCode: 'spirit-energy', current: dim(3, -1), base: dim(8, -1), bonuses: [] },
    ],
    abilities: [
      { ruleCode: 'borba', level: 1 },
      { ruleCode: 'keen-hearing', level: 1 },
      { ruleCode: 'becoming-arcanist', level: 1 },
      { ruleCode: 'magic-core-capacity', level: 1, parameters: { x: dim(3) } },
      {
        ruleCode: 'discharge',
        level: 1,
        domain: 'Арканист',
        domainCode: 'arcanist',
      },
    ],
    points: { osSpent: 30, olSpent: 0, olTotal: 7, orSpent: 0, orTotal: 12 },
    money: 50,
    ageYears: null,
    inventory: [
      { id: 1, ruleCode: 'fekhtovalnyy-mech', quantity: 1, equipped: true },
      { id: 2, ruleCode: 'latnyy-dospekh', quantity: 1, equipped: true },
      { id: 3, ruleCode: 'magic-core', quantity: 1, equipped: true, modifierRuleCodes: [] },
    ],
    states: [
      { stateRuleCode: 'exhaustion', value: 2 },
      { stateRuleCode: 'wound', value: 4 },
      { stateRuleCode: 'wound', value: 1 },
      { stateRuleCode: 'poisoning', poison: { poisonRuleCode: 'poison-scorpion', damage_type_code: 'poison-1' } },
      { stateRuleCode: 'poisoning', poison: { poisonRuleCode: 'poison-viper', damage_type_code: 'poison-3' } },
      { stateRuleCode: 'burning', dimensionalValue: { base: 3, size: 1 } },
      { stateRuleCode: 'stunned', value: 2 },
    ],
    senses: [],
  },
  2: {
    name: 'Элиандра Тенелист',
    shortDescription: 'Следопыт в изгнании, охотится на лесных призраков.',
    fullDescription:
      'Элиандра покинула свой лес после того, как тень прогнала её народ. Она ведёт охоту на бесплотных созданий и собирает их эссенцию для обряда возвращения.',
    spaceCode: 'razrabotka',
    rulesRevision: 4,
    raceRuleCode: 'arilet',
    characteristics: [
      { ruleCode: 'strength', base: dim(3), modifiers: [] },
      {
        ruleCode: 'dexterity',
        base: dim(5),
        modifiers: [{ sourceRuleCode: 'arilet', sourceLabel: null, delta: 1, target: 'dexterity', scope: null }],
      },
      { ruleCode: 'memory', base: dim(3), modifiers: [] },
      { ruleCode: 'reasoning', base: dim(3), modifiers: [] },
      { ruleCode: 'intellect', base: dim(3), modifiers: [] },
      { ruleCode: 'attention', base: dim(3), modifiers: [] },
      { ruleCode: 'reaction', base: dim(3), modifiers: [] },
      { ruleCode: 'communication', base: dim(3), modifiers: [] },
      { ruleCode: 'melee-combat', base: dim(3, -1), modifiers: [] },
    ],
    resources: [
      {
        ruleCode: 'action-points',
        current: dim(4),
        base: dim(3),
        bonuses: [{ sourceRuleCode: null, sourceLabel: 'Раса — эльф', delta: 1 }],
      },
      {
        ruleCode: 'spirit-energy',
        current: dim(2, -1),
        base: dim(3, -1),
        bonuses: [{ sourceRuleCode: null, sourceLabel: 'Усталость', delta: -1 }],
      },
    ],
    abilities: [
      { ruleCode: 'keen-hearing', level: 1 },
      { ruleCode: 'night-vision', level: 1 },
    ],
    points: { osSpent: 25, olSpent: 3, olTotal: 7, orSpent: 0, orTotal: 5 },
    money: 20,
    ageYears: null,
    inventory: [
      { id: 1, ruleCode: 'dlinnyy-luk', quantity: 1, equipped: true },
      { id: 2, ruleCode: 'kinzhal', quantity: 1, equipped: false },
    ],
    states: [{ stateRuleCode: 'exhaustion', value: 1 }],
    senses: [],
  },
  3: {
    name: 'Гаррик из Тени',
    shortDescription: 'Ловкий карманник с сомнительной репутацией.',
    fullDescription:
      'Гаррик знает все тайные ходы торгового квартала. Работает на старшего гильдии, но его верность продаётся дороже любого товара.',
    spaceCode: 'actual',
    rulesRevision: 6,
    raceRuleCode: 'acelatl',
    characteristics: [
      { ruleCode: 'strength', base: dim(3), modifiers: [] },
      { ruleCode: 'endurance', base: dim(5), modifiers: [] },
      { ruleCode: 'dexterity', base: dim(3), modifiers: [] },
      { ruleCode: 'memory', base: dim(3), modifiers: [] },
      { ruleCode: 'reasoning', base: dim(3), modifiers: [] },
      { ruleCode: 'intellect', base: dim(3), modifiers: [] },
      { ruleCode: 'attention', base: dim(3), modifiers: [] },
      { ruleCode: 'reaction', base: dim(3), modifiers: [] },
      { ruleCode: 'communication', base: dim(3), modifiers: [] },
      { ruleCode: 'melee-combat', base: dim(3, -1), modifiers: [] },
    ],
    resources: [
      {
        ruleCode: 'action-points',
        current: dim(5),
        base: dim(3),
        bonuses: [{ sourceRuleCode: 'acelatl', sourceLabel: null, delta: 2 }],
      },
      { ruleCode: 'spirit-energy', current: dim(1, -1), base: dim(1, -1), bonuses: [] },
    ],
    abilities: [
      { ruleCode: 'borba', level: 2 },
      { ruleCode: 'night-vision', level: 1 },
    ],
    points: { osSpent: 20, olSpent: 0, olTotal: 7, orSpent: 0, orTotal: 18 },
    money: 130,
    ageYears: null,
    inventory: [
      { id: 1, ruleCode: 'kinzhal', quantity: 2, equipped: true },
      { id: 2, ruleCode: 'fekhtovalnyy-mech', quantity: 1, equipped: false },
      { id: 3, ruleCode: 'klassicheskiy-shchit', quantity: 1, equipped: true },
    ],
    states: [
      { stateRuleCode: 'poisoning', poison: { poisonRuleCode: 'poison-scorpion', damage_type_code: 'poison-1' } },
      { stateRuleCode: 'poisoning', poison: { poisonRuleCode: 'poison-viper', damage_type_code: 'poison-3' } },
    ],
    senses: [],
  },
  4: {
    name: 'Морган Мёртвый Глаз',
    shortDescription: 'Бывший наёмник, лишённый глаза за предательство.',
    fullDescription:
      'Морган потерял глаз, когда выдал нанимателя. Теперь его кредо — платить только один гвоздь от каждого дела, иначе живым не уйти.',
    spaceCode: 'actual',
    rulesRevision: 11,
    raceRuleCode: 'human',
    characteristics: [
      {
        ruleCode: 'strength',
        base: dim(4),
        modifiers: [{ sourceRuleCode: 'human', sourceLabel: null, delta: 1, target: 'strength', scope: null }],
      },
      { ruleCode: 'dexterity', base: dim(3), modifiers: [] },
      { ruleCode: 'memory', base: dim(3), modifiers: [] },
      { ruleCode: 'reasoning', base: dim(3), modifiers: [] },
      { ruleCode: 'intellect', base: dim(3), modifiers: [] },
      { ruleCode: 'attention', base: dim(3), modifiers: [] },
      { ruleCode: 'reaction', base: dim(3), modifiers: [] },
      { ruleCode: 'communication', base: dim(3), modifiers: [] },
      { ruleCode: 'melee-combat', base: dim(3, -1), modifiers: [] },
    ],
    resources: [
      { ruleCode: 'action-points', current: dim(4), base: dim(4), bonuses: [] },
      {
        ruleCode: 'spirit-energy',
        current: dim(0, -1),
        base: dim(1, -1),
        bonuses: [{ sourceRuleCode: null, sourceLabel: 'Тяжёлое ранение', delta: -1 }],
      },
    ],
    abilities: [{ ruleCode: 'borba', level: 2 }],
    points: { osSpent: 30, olSpent: 0, olTotal: 7, orSpent: 0, orTotal: 7 },
    money: 85,
    ageYears: null,
    inventory: [
      { id: 1, ruleCode: 'fekhtovalnyy-mech', quantity: 1, equipped: true },
      { id: 2, ruleCode: 'latnyy-dospekh', quantity: 1, equipped: true },
      { id: 3, ruleCode: 'klassicheskiy-shchit', quantity: 1, equipped: true },
    ],
    states: [
      { stateRuleCode: 'wound', value: 3 },
      { stateRuleCode: 'exhaustion', value: 1 },
    ],
    senses: [],
  },
  5: {
    name: 'Серафина Древний Ветер',
    shortDescription: 'Посланница торговой гильдии, отвечает за границы.',
    fullDescription:
      'Серафина ведёт переговоры на границах трёх королевств. Её герб — древний ветер гильдии, но за печатью скрыт договор, который она не смеет разорвать.',
    spaceCode: 'razrabotka',
    rulesRevision: 5,
    raceRuleCode: 'liten',
    characteristics: [
      { ruleCode: 'strength', base: dim(3), modifiers: [] },
      { ruleCode: 'dexterity', base: dim(4), modifiers: [] },
      {
        ruleCode: 'memory',
        base: dim(3),
        modifiers: [{ sourceRuleCode: 'liten', sourceLabel: null, delta: 1, target: 'memory', scope: null }],
      },
      { ruleCode: 'reasoning', base: dim(4), modifiers: [] },
      { ruleCode: 'intellect', base: dim(4), modifiers: [] },
      { ruleCode: 'attention', base: dim(3), modifiers: [] },
      { ruleCode: 'reaction', base: dim(3), modifiers: [] },
      { ruleCode: 'communication', base: dim(3), modifiers: [] },
      { ruleCode: 'melee-combat', base: dim(3, -1), modifiers: [] },
    ],
    resources: [
      { ruleCode: 'action-points', current: dim(4), base: dim(4), bonuses: [] },
      { ruleCode: 'spirit-energy', current: dim(2, -1), base: dim(2, -1), bonuses: [] },
    ],
    abilities: [
      { ruleCode: 'keen-hearing', level: 1 },
      { ruleCode: 'night-vision', level: 1 },
    ],
    points: { osSpent: 27, olSpent: 2, olTotal: 7, orSpent: 0, orTotal: 10 },
    money: 200,
    ageYears: null,
    inventory: [
      { id: 1, ruleCode: 'dlinnyy-luk', quantity: 1, equipped: true },
      { id: 2, ruleCode: 'kinzhal', quantity: 1, equipped: false },
    ],
    states: [],
    senses: [],
  },
};

// Предыдущая версия до последней миграции правил (для сравнения «до/после» на карточке).
const previousVersions: Record<number, CharacterVersion | null> = {};
const actualVersionByCharacterId: Record<number, number> = Object.fromEntries(
  characters.map((character) => [character.id, 1]),
) as Record<number, number>;
const completedCommands = new Map<string, { fingerprint: string; detail: CharacterDetail }>();

const details: Record<number, CharacterDetail> = Object.fromEntries(
  characters.map((character) => [
    character.id,
    {
      character: { ...character },
      version: versions[character.id],
      actualVersion: actualVersionByCharacterId[character.id],
      discussionChatId: character.discussionChatId,
      previousVersion: previousVersions[character.id] ?? null,
    },
  ]),
);

const delay = (ms = 300) => new Promise((r) => setTimeout(r, ms));

async function assertVersionMatchesSpace(version: CharacterVersion, spaceId: number): Promise<void> {
  const space = await fetchSpace(spaceId);
  if (version.spaceCode !== space.code) {
    throw new Error('Пространство версии персонажа не совпадает с пространством персонажа');
  }
  const revision = await fetchRevision(spaceId, version.rulesRevision);
  characterVersionIntegrityService.assertValid(version, revision.rules);
}

/** Личные заметки владельца — не в CharacterVersion и не в details (ГМ не должен их увидеть в листе). */
const ownerNotesByCharacterId: Record<number, string> = {
  1: 'Не забыть про долг кузнецу.',
};

function toViewerDetail(id: number): CharacterDetail {
  const detail = details[id];
  if (!detail) throw new Error(`Character ${id} not found`);
  const result: CharacterDetail = {
    character: { ...detail.character },
    version: detail.version,
    actualVersion: actualVersionByCharacterId[id],
    discussionChatId: detail.character.discussionChatId,
    previousVersion: previousVersions[id] ?? null,
  };
  if (getCurrentUserId() === detail.character.ownerId) {
    result.ownerNotes = ownerNotesByCharacterId[id] ?? null;
  }

  return result;
}

export async function fetchCharacters(_signal?: AbortSignal): Promise<Character[]> {
  await delay();

  return characters.map((character) => ({ ...character }));
}

export async function fetchCharacter(id: number, _signal?: AbortSignal): Promise<CharacterDetail> {
  await delay();

  return toViewerDetail(id);
}

export function getCharacterActualVersion(id: number): number {
  if (!versions[id]) throw new CharacterApiError('CHARACTER_NOT_FOUND', `Character ${id} not found`);

  return actualVersionByCharacterId[id] ?? 0;
}

export function getStoredCharacterVersion(id: number): CharacterVersion {
  const version = versions[id];
  if (!version) throw new CharacterApiError('CHARACTER_NOT_FOUND', `Character ${id} not found`);

  return cloneData(version);
}

export function captureCharacterRuntimeState(id: number): CharacterDetail {
  return cloneData(toViewerDetail(id));
}

export function restoreCharacterRuntimeState(id: number, snapshot: CharacterDetail): void {
  const character = characters.find((entry) => entry.id === id);
  if (!character) throw new CharacterApiError('CHARACTER_NOT_FOUND', `Character ${id} not found`);

  Object.assign(character, cloneData(snapshot.character));
  versions[id] = cloneData(snapshot.version);
  actualVersionByCharacterId[id] = snapshot.actualVersion;
  details[id] = {
    character: { ...character },
    version: cloneData(snapshot.version),
    actualVersion: snapshot.actualVersion,
    discussionChatId: character.discussionChatId,
    previousVersion: previousVersions[id] ?? null,
  };
}

export function replaceCharacterRuntimeVersion(
  id: number,
  version: CharacterVersion,
  expectedActualVersion: number,
): CharacterDetail {
  if (actualVersionByCharacterId[id] !== expectedActualVersion) {
    throw conflictError(id, expectedActualVersion);
  }

  const character = characters.find((entry) => entry.id === id);
  if (!character) throw new CharacterApiError('CHARACTER_NOT_FOUND', `Character ${id} not found`);

  character.name = version.name;
  character.raceId = raceIdOf(version.raceRuleCode);
  character.raceLabel = raceLabelOf(version.raceRuleCode);
  character.shortDescription = version.shortDescription;
  character.currentPoints = pointsOf(version);
  character.spaceCode = version.spaceCode;
  character.rulesRevision = version.rulesRevision;
  versions[id] = cloneData(version);
  actualVersionByCharacterId[id] = expectedActualVersion + 1;
  details[id] = {
    character: { ...character },
    version: cloneData(version),
    actualVersion: actualVersionByCharacterId[id],
    discussionChatId: character.discussionChatId,
    previousVersion: previousVersions[id] ?? null,
  };

  return captureCharacterRuntimeState(id);
}

export async function updateOwnerNotes(id: number, notes: string, _signal?: AbortSignal): Promise<CharacterDetail> {
  await delay();
  const character = characters.find((entry) => entry.id === id);
  if (!character) throw new Error(`Character ${id} not found`);
  if (getCurrentUserId() !== character.ownerId) throw new Error('Forbidden');
  const trimmed = notes.trim();
  if (trimmed) ownerNotesByCharacterId[id] = trimmed;
  else delete ownerNotesByCharacterId[id];

  return toViewerDetail(id);
}

let nextId = 6;

function raceLabelOf(raceRuleCode: string | null): string | null {
  if (raceRuleCode === null) return null;

  return ruleCatalog.find((rule) => rule.code === raceRuleCode)?.name ?? null;
}

function raceIdOf(raceRuleCode: string | null): number | null {
  if (raceRuleCode === null) return null;

  return ruleCatalog.find((entry) => entry.code === raceRuleCode)?.id ?? null;
}

function pointsOf(version: CharacterVersion): Character['currentPoints'] {
  return { os: version.points.osSpent, ol: version.points.olTotal, or: version.points.orTotal };
}

function summaryOf(id: number, version: CharacterVersion, spaceId: number, status: CharacterStatus): Character {
  const owner = getCurrentUserSummary();

  return {
    id,
    name: version.name,
    status,
    active: true,
    ownerId: owner.id,
    ownerName: owner.name,
    raceId: raceIdOf(version.raceRuleCode),
    raceLabel: raceLabelOf(version.raceRuleCode),
    gameId: null,
    gameName: null,
    spaceId,
    spaceCode: version.spaceCode,
    rulesRevision: version.rulesRevision,
    shortDescription: version.shortDescription,
    visibility: defaultVisibility(),
    currentPoints: pointsOf(version),
    discussionChatId: null,
  };
}

export async function createCharacter(data: CreateCharacterData, _signal?: AbortSignal): Promise<CharacterDetail> {
  await delay();
  const space = await fetchSpace(data.spaceId);
  if (data.spaceCode !== space.code || data.rulesRevision !== data.version.rulesRevision) {
    throw new Error('Метаданные персонажа не совпадают с версией правил');
  }
  await assertVersionMatchesSpace(data.version, data.spaceId);
  const id = nextId++;
  actualVersionByCharacterId[id] = 1;
  // Статус листа решает вызывающий контекст (редактор вне игры — 'ready'); мок дефолтит 'draft'.
  const character = summaryOf(id, data.version, data.spaceId, data.status ?? 'draft');
  characters.push(character);
  const version = cloneData(data.version);
  versions[id] = version;
  // Новому персонажу сразу создаём чат обсуждения (владелец — участник).
  const discussionChatId = mockCreateCharacterDiscussion(data.version.name);
  character.discussionChatId = discussionChatId;
  const detail: CharacterDetail = {
    character,
    version,
    actualVersion: actualVersionByCharacterId[id],
    discussionChatId,
    previousVersion: null,
  };
  details[id] = detail;

  return toViewerDetail(id);
}

async function versionFromChoices(
  choices: CharacterBuild,
  creationConfig: CharacterCreationConfig,
): Promise<CharacterVersion> {
  const revision = await fetchRevision(choices.spaceId, choices.rulesRevision);

  return characterEditorService.toVersion(choices, revision.rules, creationConfig);
}

async function validateVersion(version: CharacterVersion, spaceId: number): Promise<CharacterValidationResult> {
  try {
    await assertVersionMatchesSpace(version, spaceId);

    return { valid: true, problems: [] };
  } catch (error) {
    const problem: CharacterValidationProblem = {
      code: 'CHARACTER_INVALID',
      message: error instanceof Error ? error.message : 'Character validation failed',
    };

    return { valid: false, problems: [problem] };
  }
}

function conflictError(characterId: number, expectedActualVersion: number, commandId?: string): CharacterApiError {
  return new CharacterApiError('CHARACTER_ACTUAL_CONFLICT', 'Актуальное состояние персонажа уже изменилось', {
    kind: 'conflict',
    expectedActualVersion,
    actualVersion: actualVersionByCharacterId[characterId] ?? 0,
    commandId,
  });
}

function validationError(problems: CharacterValidationProblem[]): CharacterApiError {
  return new CharacterApiError('CHARACTER_VALIDATION_FAILED', 'Персонаж не прошёл проверку', {
    kind: 'validation',
    problems,
  });
}

function completedCommand(commandId: string, fingerprint: string): CharacterDetail | null {
  const completed = completedCommands.get(commandId);
  if (!completed) return null;
  if (completed.fingerprint !== fingerprint) {
    throw new CharacterApiError('CHARACTER_COMMAND_CONFLICT', 'CommandId уже использован с другими параметрами');
  }

  return cloneData(completed.detail);
}

export async function createCharacterFromChoices(data: CharacterCreateRequest): Promise<CharacterDetail> {
  const fingerprint = JSON.stringify(data);
  const previous = completedCommand(data.commandId, fingerprint);
  if (previous) return previous;

  const version = await versionFromChoices(data.choices, data.creationConfig);
  const validation = await validateVersion(version, data.choices.spaceId);
  if (!validation.valid) throw validationError(validation.problems);

  const detail = await createCharacter({
    spaceId: data.choices.spaceId,
    spaceCode: data.choices.spaceCode,
    rulesRevision: data.choices.rulesRevision,
    version,
  });
  completedCommands.set(data.commandId, { fingerprint, detail: cloneData(detail) });

  return detail;
}

export async function validateCharacterRequest(data: CharacterValidateRequest): Promise<CharacterValidationResult> {
  if (data.mode === 'create') {
    return validateVersion(await versionFromChoices(data.choices, data.creationConfig), data.choices.spaceId);
  }

  const version = versions[data.characterId];
  if (!version) {
    return {
      valid: false,
      problems: [{ code: 'CHARACTER_NOT_FOUND', message: `Character ${data.characterId} not found` }],
    };
  }
  if (actualVersionByCharacterId[data.characterId] !== data.patch.expectedActualVersion) {
    throw conflictError(data.characterId, data.patch.expectedActualVersion, data.patch.commandId);
  }

  try {
    const candidate = characterPatchService.applyPatch(version, data.patch.operations);
    const character = characters.find((entry) => entry.id === data.characterId);
    if (!character) {
      return {
        valid: false,
        problems: [{ code: 'CHARACTER_NOT_FOUND', message: `Character ${data.characterId} not found` }],
      };
    }

    return validateVersion(candidate, character.spaceId);
  } catch (error) {
    return {
      valid: false,
      problems: [
        { code: 'CHARACTER_PATCH_INVALID', message: error instanceof Error ? error.message : 'Invalid patch' },
      ],
    };
  }
}

export async function updateCharacterFromPatch(id: number, patch: CharacterPatch): Promise<CharacterDetail> {
  const fingerprint = JSON.stringify({ id, patch });
  const previous = completedCommand(patch.commandId, fingerprint);
  if (previous) return previous;
  if (!versions[id]) throw new CharacterApiError('CHARACTER_NOT_FOUND', `Character ${id} not found`);
  if (actualVersionByCharacterId[id] !== patch.expectedActualVersion) {
    throw conflictError(id, patch.expectedActualVersion, patch.commandId);
  }

  const candidate = characterPatchService.applyPatch(versions[id], patch.operations);
  const character = characters.find((entry) => entry.id === id);
  if (!character) throw new CharacterApiError('CHARACTER_NOT_FOUND', `Character ${id} not found`);
  const validation = await validateVersion(candidate, character.spaceId);
  if (!validation.valid) throw validationError(validation.problems);

  const detail = await updateCharacter(id, { version: candidate });
  completedCommands.set(patch.commandId, { fingerprint, detail: cloneData(detail) });

  return detail;
}

export async function updateCharacter(
  id: number,
  data: UpdateCharacterData,
  _signal?: AbortSignal,
): Promise<CharacterDetail> {
  await delay();
  const character = characters.find((entry) => entry.id === id);
  if (!character) throw new Error(`Character ${id} not found`);
  await assertVersionMatchesSpace(data.version, character.spaceId);

  character.name = data.version.name;
  character.raceId = raceIdOf(data.version.raceRuleCode);
  character.raceLabel = raceLabelOf(data.version.raceRuleCode);
  character.shortDescription = data.version.shortDescription;
  character.currentPoints = pointsOf(data.version);
  character.spaceCode = data.version.spaceCode;
  character.rulesRevision = data.version.rulesRevision;
  // Статус листа обновляет вызывающий контекст; без переданного — сохраняем текущий.
  if (data.status !== undefined) character.status = data.status;
  const version = cloneData(data.version);
  versions[id] = version;
  actualVersionByCharacterId[id] = (actualVersionByCharacterId[id] ?? 0) + 1;
  details[id] = {
    character: { ...character },
    version,
    actualVersion: actualVersionByCharacterId[id],
    discussionChatId: character.discussionChatId,
    previousVersion: previousVersions[id] ?? null,
  };

  return toViewerDetail(id);
}

/** Миграция на новую ревизию правил (расчёт, НЕ применяет): ремап по code, пересчёт, классификация. */
export async function migrateCharacter(
  characterId: number,
  target: { toSpaceId: number; toRevision: number },
  _signal?: AbortSignal,
): Promise<MigrationResult> {
  await delay();
  const character = characters.find((entry) => entry.id === characterId);
  if (!character) throw new Error(`Character ${characterId} not found`);
  const version = versions[characterId];
  const oldRules = (await fetchRevision(character.spaceId, version.rulesRevision)).rules;
  const newRevision = await fetchRevision(target.toSpaceId, target.toRevision);
  const space = await fetchSpace(target.toSpaceId);

  return characterMigrationService.migrate({
    version,
    oldRules,
    oldSpaceId: character.spaceId,
    newRules: newRevision.rules,
    newSpaceId: target.toSpaceId,
    newSpaceCode: space.code,
    newRevision: target.toRevision,
    // Standalone: реальные лимиты = стартовые лимиты версии (в игре — лимит игры + гранты, модуль игр).
    effectiveLimits: {
      osTotal: version.budgets?.osTotal ?? null,
      orTotal: version.points.orTotal,
      moneyBudget: version.budgets?.moneyBudget ?? null,
    },
  });
}

/** Применение мигрированной версии: версия обновляется, предыдущая сохраняется для сравнения. */
export async function applyMigration(
  characterId: number,
  version: CharacterVersion,
  _signal?: AbortSignal,
  expectedActualVersion?: number,
): Promise<CharacterDetail> {
  await delay();
  const character = characters.find((entry) => entry.id === characterId);
  if (!character) throw new Error(`Character ${characterId} not found`);
  if (expectedActualVersion !== undefined && actualVersionByCharacterId[characterId] !== expectedActualVersion) {
    throw conflictError(characterId, expectedActualVersion);
  }
  const targetSpace = await fetchSpaceByCode(version.spaceCode);
  await assertVersionMatchesSpace(version, targetSpace.id);
  if (expectedActualVersion !== undefined && actualVersionByCharacterId[characterId] !== expectedActualVersion) {
    throw conflictError(characterId, expectedActualVersion);
  }
  const oldVersion = versions[characterId];
  previousVersions[characterId] = oldVersion;
  const next = cloneData(version);
  versions[characterId] = next;
  actualVersionByCharacterId[characterId] = (actualVersionByCharacterId[characterId] ?? 0) + 1;
  character.name = next.name;
  character.raceId = raceIdOf(next.raceRuleCode);
  character.raceLabel = raceLabelOf(next.raceRuleCode);
  character.shortDescription = next.shortDescription;
  character.currentPoints = pointsOf(next);
  character.spaceCode = next.spaceCode;
  character.rulesRevision = next.rulesRevision;
  try {
    character.spaceId = (await fetchSpaceByCode(next.spaceCode)).id;
  } catch {
    // код пространства не найден — оставляем прежний spaceId
  }
  character.status = 'ready';
  details[characterId] = {
    character: { ...character },
    version: next,
    actualVersion: actualVersionByCharacterId[characterId],
    discussionChatId: character.discussionChatId,
    previousVersion: oldVersion,
  };

  return toViewerDetail(characterId);
}

/**
 * Перепривязка кэшированной детали листа к обновлённой версии (после versions[id] = <новый объект>).
 * `details[id].version` иначе остаётся ссылкой на старую версию — лист не видит кастом-записи и
 * инвентарь, добавленные после миграции/одобрения в игре (см. docs/specs §11, Баг B).
 */
export function syncCharacterVersion(id: number): void {
  if (!details[id]) return;
  details[id].version = versions[id];
}

/** Обновление зон видимости листа (общая настройка персонажа). */
export async function updateCharacterVisibility(
  id: number,
  visibility: SheetVisibility,
  _signal?: AbortSignal,
): Promise<Character> {
  await delay();
  const character = characters.find((entry) => entry.id === id);
  if (!character) throw new Error(`Character ${id} not found`);
  character.visibility = visibility.map((rule) => ({ audience: rule.audience, sections: [...rule.sections] }));
  details[id] = details[id] ? { ...details[id], character: { ...character } } : details[id];

  return { ...character };
}

let nextCustomRuleId = 1000;

/** Следующий идентификатор кастомного правила (общий счётчик с mockCharacters). */
export function allocateCustomRuleId(): number {
  return nextCustomRuleId++;
}

/** Добавляет запись кастомного правила в версию (возвращает новую версию, исходную не мутирует). */
export function appendCustomRule(version: CharacterVersion, data: AddCustomRuleData): CharacterVersion {
  const customRules = version.customRules ? version.customRules.map((entry) => ({ ...entry })) : [];
  customRules.push({
    id: allocateCustomRuleId(),
    kind: data.kind,
    name: data.name,
    description: data.description,
    status: 'active',
  });

  return { ...version, customRules };
}

/**
 * Правка/замена записи кастомного правила («Заменить на правило» → deprecated + replacedWithRuleCode).
 * Возвращает новую версию; исходную не мутирует. Для персиста вызывается в mock.updateCustomRule.
 */
export async function updateCustomRuleInVersion(
  version: CharacterVersion,
  entryId: number,
  data: UpdateCustomRuleData,
): Promise<CharacterVersion> {
  const customRules = version.customRules ? version.customRules.map((entry) => ({ ...entry })) : [];
  const index = customRules.findIndex((entry) => entry.id === entryId);
  if (index === -1) throw new Error(`Custom rule ${entryId} not found`);
  customRules[index] = {
    ...customRules[index],
    ...(data.name !== undefined ? { name: data.name } : {}),
    ...(data.description !== undefined ? { description: data.description } : {}),
    ...(data.status !== undefined ? { status: data.status } : {}),
    ...(data.replacedWithRuleCode !== undefined ? { replacedWithRuleCode: data.replacedWithRuleCode } : {}),
  };
  let next = { ...version, customRules };

  // «Заменить на правило»: если замена указывает на правило-предмет, материализуем его в инвентаре
  // персонажа (выдача ведущим — без списания денег/бюджета). Правила других типов (ability/simple/…)
  // инвентарь не трогают — только маркировка записи выше.
  if (data.replacedWithRuleCode !== undefined) {
    // Тип правила резолвим из ревизии версии (правило могло быть создано черновиком и закоммичено —
    // тогда его нет в ruleCatalog, но оно есть в срезе ревизии). Fallback на каталог.
    let rule: { type?: string } | undefined;
    try {
      const space = await fetchSpaceByCode(next.spaceCode);
      const revision = await fetchRevision(space.id, next.rulesRevision);
      rule = revision.rules.find((entry) => entry.code === data.replacedWithRuleCode);
    } catch {
      rule = undefined;
    }
    if (!rule) rule = ruleCatalog.find((entry) => entry.code === data.replacedWithRuleCode);
    if (rule?.type === 'item') {
      const inventory = (next.inventory ?? []).map((item) => ({ ...item }));
      const existing = inventory.find((item) => item.ruleCode === data.replacedWithRuleCode);
      if (existing) {
        existing.quantity += 1;
      } else {
        const nextItemId = inventory.reduce((max, item) => Math.max(max, item.id), 0) + 1;
        inventory.push({
          id: nextItemId,
          ruleCode: data.replacedWithRuleCode,
          quantity: 1,
          equipped: false,
        });
      }
      next = { ...next, inventory };
    }
  }

  return next;
}

/** Выдача кастомного правила («Уникальные правила») персонажу ведущим «на ходу». */
export async function addCustomRule(
  id: number,
  data: AddCustomRuleData,
  _signal?: AbortSignal,
  expectedActualVersion?: number,
): Promise<CharacterDetail> {
  await delay();
  const version = versions[id];
  if (!version) throw new Error(`Character ${id} not found`);
  if (expectedActualVersion !== undefined && actualVersionByCharacterId[id] !== expectedActualVersion) {
    throw conflictError(id, expectedActualVersion);
  }
  versions[id] = appendCustomRule(version, data);
  actualVersionByCharacterId[id] = (actualVersionByCharacterId[id] ?? 0) + 1;
  syncCharacterVersion(id);

  const character = characters.find((entry) => entry.id === id);
  if (!character) throw new Error(`Character ${id} not found`);

  return toViewerDetail(id);
}

/** Правка/замена записи кастомного правила («Заменить на правило» → deprecated + replacedWithRuleCode). */
export async function updateCustomRule(
  id: number,
  entryId: number,
  data: UpdateCustomRuleData,
  _signal?: AbortSignal,
  expectedActualVersion?: number,
): Promise<CharacterDetail> {
  await delay();
  const version = versions[id];
  if (!version) throw new Error(`Character ${id} not found`);
  if (expectedActualVersion !== undefined && actualVersionByCharacterId[id] !== expectedActualVersion) {
    throw conflictError(id, expectedActualVersion);
  }
  const next = await updateCustomRuleInVersion(version, entryId, data);
  if (expectedActualVersion !== undefined && actualVersionByCharacterId[id] !== expectedActualVersion) {
    throw conflictError(id, expectedActualVersion);
  }
  versions[id] = next;
  actualVersionByCharacterId[id] = (actualVersionByCharacterId[id] ?? 0) + 1;
  syncCharacterVersion(id);

  const character = characters.find((entry) => entry.id === id);
  if (!character) throw new Error(`Character ${id} not found`);

  return toViewerDetail(id);
}
