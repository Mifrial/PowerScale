import type { GameCharacterMembership } from '@/modules/Roleplay/Game/Dto/GameCharacterMembership';
import type { GameModerationAction } from '@/modules/Roleplay/Game/Enum/GameModerationAction';
import type { SheetVisibility } from '@/modules/Roleplay/Character/Dto/SheetVisibility';
import type { CharacterGameContext } from '@/modules/Roleplay/Game/Dto/CharacterGameContext';
import type { CreateCharacterData } from '@/modules/Roleplay/Character/Dto/Editor/CreateCharacterData';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { ConflictChoices } from '@/modules/Roleplay/Game/Utils/reconcileVersion';
import {
  characters,
  versions,
  createCharacter as createMockCharacter,
  syncCharacterVersion,
} from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { gameDetails } from '@/modules/Roleplay/Game/Mock/mockGames';
import {
  clearCombatOverlay,
  combatKey,
  combatOverlayHasChanges,
  combatOverlaySnapshot,
  getStoredCombatOverlay,
} from '@/modules/Roleplay/Game/Mock/mockGameCombatOverlays';
import { reconcileVersion } from '@/modules/Roleplay/Game/Utils/reconcileVersion';
import { SHEET_VISIBILITY_DEFAULT } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_VISIBILITY_PRESETS';

const delay = (ms = 100) => new Promise((r) => setTimeout(r, ms));

function snapshotVersion(version: CharacterVersion): CharacterVersion {
  return JSON.parse(JSON.stringify(version)) as CharacterVersion;
}

function versionsEqual(a: CharacterVersion, b: CharacterVersion): boolean {
  return JSON.stringify(a) === JSON.stringify(b);
}

/** Сессия активна: игра `playing` (модель версий — Баг 1). */
export function isSessionActive(gameId: number): boolean {
  return gameDetails.find((detail) => detail.game.id === gameId)?.game.status === 'playing';
}

// Членства хранятся без зеркала видимости (источник — персонаж) и без оверлея (источник — боевые оверлеи).
type StoredMembership = Omit<GameCharacterMembership, 'visibility' | 'overlay'>;

// Членства персонажей в играх (ТР §3 `game_characters`). Инварианты: characterId — из mockCharacters,
// gameId — из mockGames. Снимки — ссылки на версии mockCharacters (не копии; не мутируем).
// Модель версий (Баг 1, 2026-08-20): latest = versions[id] (источник истины), active — замороженный
// снимок (меняется только модерацией), pending — производная snapshot(latest + оверлей).
// Гаррик (game 1) — пример модерации после правки карточки: latest несёт правки, active заморожен.
export const gameCharacterMemberships: StoredMembership[] = [
  {
    gameId: 1,
    characterId: 3,
    characterName: 'Гаррик из Тени',
    characterOwnerId: 2,
    characterOwnerName: 'Администратор',
    characterStatus: 'ready',
    role: 'player',
    membershipStatus: 'pending',
    activeVersion: snapshotVersion(versions[3]),
    latestVersion: versions[3],
    pendingVersion: {
      ...versions[3],
      money: 220,
      abilities: versions[3].abilities.map((ability) =>
        ability.ruleId === 'rule-21' ? { ...ability, level: 3 } : ability,
      ),
      states: [...versions[3].states, { stateRuleId: 'rule-56', value: 1 }],
    },
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: '2026-08-12T18:00:00',
  },
  {
    gameId: 1,
    characterId: 4,
    characterName: 'Морган Мёртвый Глаз',
    characterOwnerId: 3,
    characterOwnerName: 'Пётр Козлов',
    characterStatus: 'needs_fix',
    role: 'player',
    membershipStatus: 'pending',
    activeVersion: null,
    latestVersion: versions[4],
    pendingVersion: versions[4],
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: '2026-08-10T09:30:00',
  },
  {
    gameId: 2,
    characterId: 1,
    characterName: 'Торвин Стальной Кулак',
    characterOwnerId: 1,
    characterOwnerName: 'Иван Петров',
    characterStatus: 'ready',
    role: 'player',
    membershipStatus: 'approved',
    activeVersion: snapshotVersion(versions[1]),
    latestVersion: versions[1],
    pendingVersion: null,
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: '2026-07-25T14:00:00',
  },
];

function gameNameOf(gameId: number): string {
  return gameDetails.find((d) => d.game.id === gameId)?.game.name ?? 'Игра';
}

// Зеркало видимости листа и сессионного оверлея: членство несёт копии (источник — персонаж/оверлеи).
function withVisibility(membership: StoredMembership): GameCharacterMembership {
  const character = characters.find((c) => c.id === membership.characterId);
  const visibility = character ? character.visibility : SHEET_VISIBILITY_DEFAULT;
  const overlay = combatOverlaySnapshot(
    getStoredCombatOverlay(membership.gameId, combatKey('character', membership.characterId)),
  );

  return {
    ...membership,
    overlay,
    visibility: visibility.map((rule) => ({ audience: rule.audience, sections: [...rule.sections] })),
  };
}

export async function fetchGameCharacters(gameId: number, _signal?: AbortSignal): Promise<GameCharacterMembership[]> {
  await delay(150);

  return gameCharacterMemberships.filter((membership) => membership.gameId === gameId).map(withVisibility);
}

/** Подача готового персонажа в игру (D117: подача готового → pending → approve). Только статус листа 'ready'. */
export async function submitCharacter(
  gameId: number,
  characterId: number,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const character = characters.find((c) => c.id === characterId);
  if (!character) throw new Error('Персонаж не найден');
  if (character.status !== 'ready') throw new Error('В игру можно подать только готового персонажа');
  if (!character.active) throw new Error('Персонаж деактивирован');
  if (gameCharacterMemberships.some((m) => m.gameId === gameId && m.characterId === characterId)) {
    throw new Error('Персонаж уже связан с этой игрой');
  }
  const membership: StoredMembership = {
    gameId,
    characterId,
    characterName: character.name,
    characterOwnerId: character.ownerId,
    characterOwnerName: character.ownerName,
    characterStatus: character.status,
    role: 'player',
    membershipStatus: 'pending',
    activeVersion: null,
    latestVersion: versions[characterId] ?? null,
    pendingVersion: versions[characterId] ?? null,
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: new Date().toISOString(),
  };
  gameCharacterMemberships.push(membership);

  return withVisibility(membership);
}

/**
 * Создание персонажа «через игру» (D118): персонаж рождается сразу с членством в игре
 * (pending, activeVersion null). Лимиты/пространство/ревизию даёт игра; модерацию несёт
 * членство, статус листа — 'ready' (созданный лист полный).
 */
export async function createGameCharacter(
  gameId: number,
  data: CreateCharacterData,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const detail = await createMockCharacter(data);
  const character = detail.character;
  const membership: StoredMembership = {
    gameId,
    characterId: character.id,
    characterName: character.name,
    characterOwnerId: character.ownerId,
    characterOwnerName: character.ownerName,
    characterStatus: character.status,
    role: 'player',
    membershipStatus: 'pending',
    activeVersion: null,
    latestVersion: versions[character.id] ?? null,
    pendingVersion: versions[character.id] ?? null,
    osBonus: 0,
    orBonus: 0,
    olBonus: 0,
    updatedAt: new Date().toISOString(),
  };
  gameCharacterMemberships.push(membership);

  return withVisibility(membership);
}

/**
 * Синхронизация членств персонажа после изменения latest (`versions[id]` вне сессии): перепривязывает
 * latestVersion и, если версия реально изменилась относительно active, автоподаёт на модерацию
 * (pending = snapshot(reconcile(active, latest, оверлей))). 'left' не трогается.
 */
export function syncCharacterVersionToMemberships(characterId: number): void {
  const latest = versions[characterId] ?? null;
  for (const membership of gameCharacterMemberships) {
    if (membership.characterId !== characterId) continue;
    membership.latestVersion = latest;
    if (membership.membershipStatus === 'left' || !latest) continue;
    if (membership.activeVersion && versionsEqual(membership.activeVersion, latest)) continue;
    const overlay = getStoredCombatOverlay(membership.gameId, combatKey('character', characterId));
    membership.pendingVersion = snapshotVersion(reconcileVersion(membership.activeVersion, latest, overlay));
    membership.membershipStatus = 'pending';
    membership.updatedAt = new Date().toISOString();
  }
}

/**
 * Модерация (D117, модель версий — Баг 1). approve → версия = reconcile(active, latest, оверлей)
 * с учётом выборов ведущего при конфликтах; становится latest (versions[id]) и активной (заморожена).
 * reject → сброс оверлея и pending, latest не трогается. При активной сессии с изменениями оверлея
 * модерация заблокирована (изменения сессии уходят на модерацию только при её остановке).
 */
export async function moderateCharacter(
  gameId: number,
  characterId: number,
  action: GameModerationAction,
  choices: ConflictChoices = {},
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const membership = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (!membership) throw new Error('Членство не найдено');
  const entityKey = combatKey('character', characterId);
  const overlay = getStoredCombatOverlay(gameId, entityKey);
  if (isSessionActive(gameId) && combatOverlayHasChanges(membership.activeVersion, overlay)) {
    throw new Error('Сессия активна: изменения персонажа уйдут на модерацию после остановки сессии');
  }
  if (action === 'approve') {
    const latest = versions[characterId] ?? membership.activeVersion;
    if (latest) {
      const final = reconcileVersion(membership.activeVersion, latest, overlay, choices);
      versions[characterId] = final;
      syncCharacterVersion(characterId);
      membership.activeVersion = snapshotVersion(final);
      membership.latestVersion = versions[characterId];
    }
    clearCombatOverlay(gameId, entityKey);
    membership.pendingVersion = null;
    membership.membershipStatus = 'approved';
    const character = characters.find((c) => c.id === characterId);
    if (character) {
      character.gameId = gameId;
      character.gameName = gameNameOf(gameId);
    }
  } else {
    clearCombatOverlay(gameId, entityKey);
    membership.pendingVersion = null;
    membership.membershipStatus = 'rejected';
  }
  membership.updatedAt = new Date().toISOString();

  return withVisibility(membership);
}

/**
 * Сбор изменений при «Остановить сессию» (CD-2, модель версий — Баг 1): пока сессия активна
 * (игра `playing`) изменения живут в оверлее, pending не создаётся. При остановке сессии для
 * approved-персонажей и членств с хранимым оверлеем строится pendingVersion =
 * snapshot(reconcile(active, latest, оверлей)) → на модерацию ГМ. Оверлей сохраняется как
 * источник при approve.
 */
export async function submitCombatChanges(gameId: number, _signal?: AbortSignal): Promise<void> {
  await delay(150);
  if (isSessionActive(gameId)) return;
  for (const membership of gameCharacterMemberships) {
    if (membership.gameId !== gameId || membership.membershipStatus === 'left' || !membership.activeVersion) {
      continue;
    }
    const overlay = getStoredCombatOverlay(gameId, combatKey('character', membership.characterId));
    if (!combatOverlayHasChanges(membership.activeVersion, overlay)) continue;
    const latest = versions[membership.characterId] ?? membership.activeVersion;
    membership.pendingVersion = snapshotVersion(reconcileVersion(membership.activeVersion, latest, overlay));
    if (membership.membershipStatus === 'approved') membership.membershipStatus = 'pending';
    membership.updatedAt = new Date().toISOString();
  }
}

/**
 * Настройка видимости листа персонажа (владелец/ГМ). Видимость — общая (на персонаже),
 * поэтому обновление в любой игре меняет её везде; членство несёт зеркало.
 */
export async function updateMembershipVisibility(
  gameId: number,
  characterId: number,
  visibility: SheetVisibility,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const membership = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (!membership) throw new Error('Членство не найдено');
  const character = characters.find((c) => c.id === characterId);
  if (!character) throw new Error('Персонаж не найден');
  character.visibility = visibility.map((rule) => ({ audience: rule.audience, sections: [...rule.sections] }));

  return withVisibility(membership);
}

/**
 * Выдача бонусных очков ГМ конкретному персонажу (os/or/ol) сверх общих лимитов игры.
 * Реальные лимиты персонажа = лимит игры + бонус; деньги выдаются через добычу.
 */
export async function updateCharacterGrants(
  gameId: number,
  characterId: number,
  data: { osBonus: number; orBonus: number; olBonus: number },
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const membership = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (!membership) throw new Error('Членство не найдено');
  membership.osBonus = data.osBonus;
  membership.orBonus = data.orBonus;
  membership.olBonus = data.olBonus;
  membership.updatedAt = new Date().toISOString();

  return withVisibility(membership);
}

/**
 * Подача мигрированной версии персонажа (перевод на новую ревизию игры, модель версий — Баг 1):
 * унификация — мигрированная версия становится latest (`versions[id]`) и автоподаётся
 * (pending = snapshot(reconcile(active, latest, оверлей))); active остаётся замороженным до approve.
 */
export async function submitCharacterMigration(
  gameId: number,
  characterId: number,
  version: CharacterVersion,
  _signal?: AbortSignal,
): Promise<GameCharacterMembership> {
  await delay(200);
  const membership = gameCharacterMemberships.find((m) => m.gameId === gameId && m.characterId === characterId);
  if (!membership) throw new Error('Членство не найдено');
  versions[characterId] = version;
  syncCharacterVersion(characterId);
  syncCharacterVersionToMemberships(characterId);

  return withVisibility(membership);
}

/** Игры, где участвует персонаж, с их участниками (для карточки персонажа). */
export async function fetchCharacterGameContexts(
  characterId: number,
  _signal?: AbortSignal,
): Promise<CharacterGameContext[]> {
  await delay(150);

  return gameCharacterMemberships
    .filter((membership) => membership.characterId === characterId)
    .map((membership) => {
      const detail = gameDetails.find((detail) => detail.game.id === membership.gameId);

      return {
        gameId: membership.gameId,
        gameName: detail?.game.name ?? 'Игра',
        members: detail?.members ?? [],
      };
    });
}
