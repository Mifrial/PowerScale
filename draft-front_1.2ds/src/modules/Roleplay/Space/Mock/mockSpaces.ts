import type { Space } from '@/modules/Roleplay/Space/Dto/Space';
import type { SpaceCreateData } from '@/modules/Roleplay/Space/Dto/SpaceCreateData';
import type { SpaceUpdateData } from '@/modules/Roleplay/Space/Dto/SpaceUpdateData';
import type { SpaceRevisionMeta } from '@/modules/Roleplay/Space/Dto/SpaceRevisionMeta';
import type { SpaceRevision } from '@/modules/Roleplay/Space/Dto/SpaceRevision';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec';
import { ruleCatalog } from '@/modules/Roleplay/Rule/Mock/mockRules';
import { slugify } from '@/modules/Roleplay/Rule/Utils/Text/slugify';

let nextId = 3;

// Единый источник правил пространств — каталог из Rule-модуля (ID и code согласованы).
const revisionRulePool: Rule[] = ruleCatalog;

// Правила, «закоммиченные» через публикацию черновика (добавленные/изменённые поверх каталога).
// Настоящий бэк хранит версии правил и собирает ревизию как «все не удалённые правила с revision ≤
// выбранной»; здесь overlay поверх каталога даёт тот же эффект без мутации глобального каталога.
const committedRules: Rule[] = [];

// Уникальный rule-id поверх каталога + закоммиченных правил (id «rule-N» по числовому суффиксу).
let nextCommittedId = Math.max(
  0,
  ...[...revisionRulePool, ...committedRules].map((r) => {
    const m = r.id.match(/^rule-(\d+)$/);

    return m ? Number(m[1]) : 0;
  }),
);
function nextRuleId(): string {
  return `rule-${++nextCommittedId}`;
}

// Правила, «выведенные из обращения» в старших ревизиях — для демо миграции персонажей:
// способность «Ночное зрение» (night-vision) есть до ревизии 7 включительно, на ревизии ≥ 8 удалена.
const RETIRED_RULES_BY_MIN_REVISION: { code: string; minRevision: number }[] = [
  { code: 'night-vision', minRevision: 8 },
];

const retiredMarkers: { code: string; minRevision: number }[] = [...RETIRED_RULES_BY_MIN_REVISION];

function isAlwaysIncluded(rule: Rule): boolean {
  // Ресурсы, способности, очки, предметы, модификаторы предметов, расы и виды попадают в срез всегда: на них
  // ссылаются персонажи (предметы/расы) и другие правила (requirements, action_components,
  // grants, зоны способностей), поэтому их отсутствие ломает «в наличии». Источники (source)
  // тоже всегда: слоты защит/сопротивлений ссылаются на них кодом, и карточка персонажа
  // выводит имя источника. Срез по count применяется к остальным (simple и прочие).
  return (
    rule.type === 'characteristic' ||
    rule.type === 'resource' ||
    rule.type === 'ability' ||
    rule.type === 'points' ||
    rule.type === 'item' ||
    rule.type === 'item_modifier' ||
    rule.type === 'item_modifier_type' ||
    rule.type === 'race' ||
    rule.type === 'species' ||
    rule.type === 'source' ||
    rule.type === 'state' ||
    rule.type === 'poison' ||
    rule.type === 'age' ||
    rule.type === 'check' ||
    rule.type === 'damage_type' ||
    // Правило «Бросок» (дефолты бросков чата) присутствует в любой ревизии игры.
    rule.mechanic_payload?.type === 'roll' ||
    rule.code === 'strike-procedure' ||
    rule.code === 'throw-procedure' ||
    rule.code === 'shoot-procedure' ||
    rule.code === 'flanking-attack'
  );
}

function alwaysIncludedCount(): number {
  return revisionRulePool.filter(isAlwaysIncluded).length;
}

function slicedRuleCount(revision: number): number {
  return Math.min(revisionRulePool.length - alwaysIncludedCount(), 5 + Math.floor(revision * 0.5));
}

function generatedRuleCount(revision: number): number {
  return alwaysIncludedCount() + slicedRuleCount(revision);
}

const spaces: Space[] = [
  {
    id: 1,
    code: 'razrabotka',
    name: 'Разработка',
    description: 'Рабочее пространство для разработки правил',
    revision: 5,
    active: true,
    createdAt: '2026-01-15T10:00:00Z',
    rulesCount: generatedRuleCount(5),
  },
  {
    id: 2,
    code: 'actual',
    name: 'Актуальные правила',
    description: 'Опубликованные правила для игроков',
    revision: 12,
    active: true,
    createdAt: '2026-02-01T09:00:00Z',
    rulesCount: generatedRuleCount(12),
  },
];

const delay = (ms = 300) => new Promise((r) => setTimeout(r, ms));

export async function fetchSpaces(_signal?: AbortSignal): Promise<Space[]> {
  await delay();

  return spaces.map((s) => ({ ...s }));
}

export async function fetchSpace(id: number, _signal?: AbortSignal): Promise<Space> {
  await delay();
  const space = spaces.find((s) => s.id === id);
  if (!space) throw new Error(`Space ${id} not found`);

  return { ...space };
}

export async function fetchSpaceByCode(code: string, _signal?: AbortSignal): Promise<Space> {
  await delay();
  const space = spaces.find((s) => s.code === code);
  if (!space) throw new Error(`Space ${code} not found`);

  return { ...space };
}

export async function createSpace(data: SpaceCreateData, _signal?: AbortSignal): Promise<Space> {
  await delay();

  let rulesCount = 0;
  let inheritedRules: Rule[] | null = null;

  if (data.inheritFrom) {
    const parent = spaces.find((s) => s.id === data.inheritFrom);
    if (parent) {
      inheritedRules = generateRevisionRules(parent.id, parent.revision);
      rulesCount = inheritedRules.length;
    }
  }

  const space: Space = {
    id: nextId++,
    code: slugify(data.name),
    name: data.name,
    description: data.description,
    revision: 0,
    active: true,
    createdAt: new Date().toISOString(),
    rulesCount,
  };
  spaces.push(space);

  if (inheritedRules) {
    revisionRulesCache.set(`${space.id}:0`, {
      revision: 0,
      publishedAt: new Date().toISOString(),
      spaceCode: space.code,
      spaceName: space.name,
      rules: inheritedRules.map((r) => ({ ...r, spaceId: space.id })),
    });
  }

  return { ...space };
}

export async function updateSpace(id: number, data: SpaceUpdateData, _signal?: AbortSignal): Promise<Space> {
  await delay();
  const space = spaces.find((s) => s.id === id);
  if (!space) throw new Error(`Space ${id} not found`);
  if (data.name !== undefined) space.name = data.name;
  if (data.description !== undefined) space.description = data.description;

  return { ...space };
}

export async function deactivateSpace(id: number, _signal?: AbortSignal): Promise<void> {
  await delay();
  const space = spaces.find((s) => s.id === id);
  if (space) space.active = false;
}

// ——— SpaceRevision mocks ———

function collectReferencedCodes(spec: RuleSpec | undefined): string[] {
  const refs = new Set<string>();
  const walk = (node: unknown) => {
    if (!node || typeof node !== 'object') return;
    if (Array.isArray(node)) {
      node.forEach(walk);

      return;
    }
    for (const [key, value] of Object.entries(node)) {
      if (typeof value === 'string' && /_code$/.test(key)) {
        refs.add(value);
      } else if (Array.isArray(value) && /_codes$/.test(key)) {
        for (const item of value) {
          if (typeof item === 'string') refs.add(item);
        }
      } else if (typeof value === 'object') {
        walk(value);
      }
    }
  };
  walk(spec);

  return Array.from(refs);
}

// Экспортируется для теста согласованности ссылок мок-персонажей с составом ревизии.
export function generateRevisionRules(spaceId: number, revision: number): Rule[] {
  // Overlay закоммиченных правил поверх каталога: изменённые переопределяют по code,
  // добавленные появляются. Эмулирует ревизию «все не удалённые правила ≤ выбранной».
  const poolByCode = new Map(revisionRulePool.map((r) => [r.code, r]));
  for (const rule of committedRules) poolByCode.set(rule.code, rule);
  const pool = Array.from(poolByCode.values());

  const included = new Map<string, Rule>();
  const addRule = (rule: Rule) => {
    if (included.has(rule.code)) return;
    included.set(rule.code, rule);
    for (const refCode of collectReferencedCodes(rule.spec)) {
      const refRule = poolByCode.get(refCode);
      if (refRule) addRule(refRule);
    }
  };

  // Ресурсы, способности и очки попадают в срез всегда — на них ссылаются
  // из других правил (requirements, action_components, grants, зоны способностей),
  // поэтому их отсутствие ломает «в наличии». Срез по count применяется к остальным.
  const alwaysIncluded = pool.filter(isAlwaysIncluded);
  const sliced = pool.filter((r) => !isAlwaysIncluded(r)).slice(0, slicedRuleCount(revision));

  for (const rule of alwaysIncluded) addRule(rule);
  for (const rule of sliced) addRule(rule);

  // Вывод правил из обращения в старших ревизиях (демо миграции персонажей).
  for (const retired of retiredMarkers) {
    if (revision >= retired.minRevision) included.delete(retired.code);
  }

  return Array.from(included.values()).map((r) => ({
    ...r,
    spaceId,
    updatedAt: new Date(2026, 0, 15 + revision).toISOString(),
  }));
}

const revisionMetaCache = new Map<string, SpaceRevisionMeta[]>();

function buildRevisionsMeta(space: Space): SpaceRevisionMeta[] {
  const key = `meta:${space.id}`;
  const cached = revisionMetaCache.get(key);
  if (cached) return cached;

  const items: SpaceRevisionMeta[] = [];
  for (let r = 1; r <= space.revision; r++) {
    items.push({
      revision: r,
      publishedAt: new Date(2026, 0, 10 + r * 5).toISOString(),
      ruleCount: generatedRuleCount(r),
      changedCount: 1 + (r % 3),
    });
  }
  revisionMetaCache.set(key, items);

  return items;
}

export async function fetchRevisions(spaceId: number, _signal?: AbortSignal): Promise<SpaceRevisionMeta[]> {
  await delay(200);
  const space = spaces.find((s) => s.id === spaceId);
  if (!space) throw new Error(`Space ${spaceId} not found`);

  return buildRevisionsMeta(space);
}

const revisionRulesCache = new Map<string, SpaceRevision<Rule>>();

export async function fetchRevision(
  spaceId: number,
  revision: number,
  _signal?: AbortSignal,
): Promise<SpaceRevision<Rule>> {
  await delay(300);
  const key = `${spaceId}:${revision}`;
  const cached = revisionRulesCache.get(key);
  if (cached) return cached;

  const space = spaces.find((s) => s.id === spaceId);
  if (!space) throw new Error(`Space ${spaceId} not found`);
  if (revision > space.revision) throw new Error(`Revision ${revision} not found for space ${spaceId}`);
  if (space.revision === 0) {
    const empty: SpaceRevision<Rule> = {
      revision: 0,
      publishedAt: space.createdAt,
      spaceCode: space.code,
      spaceName: space.name,
      rules: [],
    };

    return empty;
  }

  const result: SpaceRevision<Rule> = {
    revision,
    publishedAt: new Date(2026, 0, 10 + revision * 5).toISOString(),
    spaceCode: space.code,
    spaceName: space.name,
    rules: generateRevisionRules(spaceId, revision),
  };
  revisionRulesCache.set(key, result);

  return result;
}

export async function commitDraft(
  spaceId: number,
  rules: Rule[],
  _signal?: AbortSignal,
  removedCodes: string[] = [],
): Promise<SpaceRevision<Rule>> {
  await delay(500);
  const space = spaces.find((s) => s.id === spaceId);
  if (!space) throw new Error(`Space ${spaceId} not found`);

  const now = new Date().toISOString();
  const poolByCode = new Map([...revisionRulePool, ...committedRules].map((r) => [r.code, r]));

  // Вносим черновик в overlay: новые правила (code не встречается в пуле) получают стабильный
  // rule-id, изменённые сохраняют id существующего правила. Эмуляция бэка: версия правила
  // пишется в пространство, ревизия собирается из актуального набора.
  committedRules.length = 0;
  const removed = new Set(removedCodes);
  for (const draftRule of rules) {
    if (removed.has(draftRule.code)) continue;
    const existing = poolByCode.get(draftRule.code);
    const id = existing ? existing.id : nextRuleId();
    committedRules.push({ ...draftRule, id, updatedAt: now });
  }

  const hadPublishedSnapshot = space.revision >= 1 || revisionRulesCache.has(`${spaceId}:0`);
  space.revision++;
  const revision = space.revision;
  for (const code of removedCodes) {
    retiredMarkers.push({ code, minRevision: revision });
  }

  const snapshotRules = hadPublishedSnapshot
    ? generateRevisionRules(spaceId, revision)
    : committedRules.map((rule) => ({ ...rule, spaceId }));
  if (!hadPublishedSnapshot) space.rulesCount = snapshotRules.length;

  const result: SpaceRevision<Rule> = {
    revision,
    publishedAt: now,
    spaceCode: space.code,
    spaceName: space.name,
    rules: snapshotRules,
  };

  const key = `${spaceId}:${revision}`;
  revisionRulesCache.set(key, result);

  const metaKey = `meta:${spaceId}`;
  revisionMetaCache.delete(metaKey);

  return result;
}
