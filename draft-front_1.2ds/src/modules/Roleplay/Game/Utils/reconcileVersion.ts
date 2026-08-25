import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { GameCombatOverlay } from '@/modules/Roleplay/Game/Dto/GameCombatOverlay';
import type { MembershipDiff, DiffSectionKey } from '@/modules/Roleplay/Game/Utils/membershipDiff';
import { membershipDiff } from '@/modules/Roleplay/Game/Utils/membershipDiff';
import { mergeCombatOverlay } from '@/modules/Roleplay/Game/Utils/mergeCombatOverlay';

/**
 * Three-way reconcile версий персонажа (модель версий — Баг 1, 2026-08-20).
 * При approve версия, которая станет `activeVersion`, собирается из трёх источников:
 * - active — одобренная (заморожена);
 * - latest — `versions[id]`, приёмник всех изменений вне сессии;
 * - overlay — сессионный слой (боевые правки и/или полная копия листа из редактора).
 * Поле берётся из overlay, если его не трогал latest; из latest, если его не трогал overlay;
 * иначе (конфликт) — выбор ведущего (по умолчанию overlay).
 */

export type ConflictChoice = 'latest' | 'overlay';

/** Выборы ведущего по полям листа при approve (ключи — поля CharacterVersion). */
export type ConflictChoices = Record<string, ConflictChoice>;

import type { DiffChange } from '@/modules/Roleplay/Game/Utils/membershipDiff';

/** Конфликт модерации: latest и overlay оба изменили поле относительно active. */
export interface VersionConflict {
  /** Ключ поля CharacterVersion (например, `money` или `resources`). */
  field: keyof CharacterVersion;
  label: string;
  /** По-элементные изменения latest → overlay для поля (из membershipDiff). */
  changes: DiffChange[];
  choice: ConflictChoice;
}

interface FieldDescriptor {
  key: keyof CharacterVersion;
  label: string;
  /** Ключ в MembershipDiff (скаляр или секция); отсутствует — поле не участвует в показе конфликта. */
  diffKey?: string;
}

const FIELDS: FieldDescriptor[] = [
  { key: 'name', label: 'Имя', diffKey: 'name' },
  { key: 'shortDescription', label: 'Краткое описание', diffKey: 'shortDescription' },
  { key: 'fullDescription', label: 'Полное описание', diffKey: 'fullDescription' },
  { key: 'spaceCode', label: 'Пространство' },
  { key: 'rulesRevision', label: 'Ревизия правил' },
  { key: 'raceRuleId', label: 'Раса', diffKey: 'race' },
  { key: 'ageYears', label: 'Возраст', diffKey: 'age' },
  { key: 'points', label: 'Очки', diffKey: 'points' },
  { key: 'money', label: 'Деньги', diffKey: 'money' },
  { key: 'characteristics', label: 'Характеристики', diffKey: 'characteristics' },
  { key: 'resources', label: 'Ресурсы', diffKey: 'resources' },
  { key: 'abilities', label: 'Способности', diffKey: 'abilities' },
  { key: 'inventory', label: 'Инвентарь', diffKey: 'inventory' },
  { key: 'states', label: 'Состояния', diffKey: 'states' },
  { key: 'senses', label: 'Чувства', diffKey: 'senses' },
  { key: 'customRules', label: 'Уникальные правила', diffKey: 'customRules' },
  { key: 'budgets', label: 'Лимиты создания' },
];

function equals(a: unknown, b: unknown): boolean {
  return JSON.stringify(a) === JSON.stringify(b);
}

/**
 * Версия-кандидат оверлея: при наличии `sheet` — полная копия листа из редактора с боевыми
 * правками поверх; иначе — активная версия с применёнными боевыми ресурсами/состояниями.
 * Возвращает null при отсутствии реальных изменений оверлея.
 */
export function effectiveOverlayVersion(
  active: CharacterVersion | null,
  overlay: GameCombatOverlay | null,
): CharacterVersion | null {
  if (!overlay || overlay.updatedAt === '') return null;
  const base = overlay.sheet ?? active;
  if (!base) return null;

  return mergeCombatOverlay(base, overlay);
}

/**
 * Версия после approve: three-way по полям (overlay-кандидат из effectiveOverlayVersion).
 * Без active (первая подача) и без изменений оверлея — latest. Исходные не мутирует.
 */
export function reconcileVersion(
  active: CharacterVersion | null,
  latest: CharacterVersion,
  overlay: GameCombatOverlay | null,
  choices: ConflictChoices = {},
): CharacterVersion {
  if (!active) return JSON.parse(JSON.stringify(latest)) as CharacterVersion;
  const candidate = effectiveOverlayVersion(active, overlay);
  if (!candidate) return JSON.parse(JSON.stringify(latest)) as CharacterVersion;

  const result: Record<string, unknown> = {};
  for (const field of FIELDS) {
    const a = active[field.key];
    const l = latest[field.key];
    const o = candidate[field.key];
    let value: unknown;
    if (equals(a, o)) value = l;
    else if (equals(a, l)) value = o;
    else if (equals(l, o)) value = l;
    else value = choices[field.key] === 'latest' ? l : o;
    result[field.key] = value;
  }

  return result as unknown as CharacterVersion;
}

/**
 * Конфликты модерации: поля, которые изменили И latest, И оверлей относительно active
 * (по умолчанию при approve побеждает оверлей). Для UI показываются по-элементные изменения
 * latest → overlay (membershipDiff), чтобы ведущий выбрал нужный вариант.
 */
export function versionConflicts(
  active: CharacterVersion | null,
  latest: CharacterVersion,
  overlay: GameCombatOverlay | null,
  choices: ConflictChoices = {},
  resolve: (ruleId: string) => string = (ruleId) => ruleId,
): VersionConflict[] {
  if (!active) return [];
  const candidate = effectiveOverlayVersion(active, overlay);
  if (!candidate) return [];

  const diff: MembershipDiff = membershipDiff(latest, candidate, resolve);
  const scalars = new Map(diff.scalars.map((change) => [change.key, change]));
  const sections = new Map(diff.sections.map((section) => [section.key, section]));

  const conflicts: VersionConflict[] = [];
  for (const field of FIELDS) {
    const a = active[field.key];
    const l = latest[field.key];
    const o = candidate[field.key];
    if (equals(a, l) || equals(a, o) || equals(l, o)) continue;

    let changes: DiffChange[] = [];
    if (field.diffKey) {
      const scalar = scalars.get(field.diffKey);
      changes = scalar ? [scalar] : (sections.get(field.diffKey as DiffSectionKey)?.changes ?? []);
    }
    conflicts.push({ field: field.key, label: field.label, changes, choice: choices[field.key] ?? 'overlay' });
  }

  return conflicts;
}
