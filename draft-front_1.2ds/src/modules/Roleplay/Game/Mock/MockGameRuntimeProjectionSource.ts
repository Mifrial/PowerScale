import type { User } from '@/modules/Core/User/Dto/User';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { SheetAccessContext } from '@/modules/Roleplay/Character/Interface/SheetAccessContext';
import type { GameRuntimeEntityBatchRequest } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityBatchRequest';
import type { GameRuntimeEntityBatchResult } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityBatchResult';
import type { GameRuntimeEntityProjection } from '@/modules/Roleplay/Game/Dto/GameRuntimeEntityProjection';
import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
import type { IGameRuntimeProjectionSource } from '@/modules/Roleplay/Game/Interface/IGameRuntimeProjectionSource';
import { sheetAccessService } from '@/modules/Roleplay/Character/init';
import type { SHEET_VISIBLE_SECTIONS } from '@/modules/Roleplay/Character/Constant/Sheet/SHEET_SECTIONS';
import { getAuthApi } from '@/modules/Core/Auth/init';
import { getCharacterActualVersion, getStoredCharacterVersion } from '@/modules/Roleplay/Character/Mock/mockCharacters';
import { fetchGameCharacters } from '@/modules/Roleplay/Game/Mock/mockGameMemberships';
import { captureNpcRuntimeState, fetchNpcs } from '@/modules/Roleplay/Game/Mock/mockGameNpcs';

/**
 * Даёт Game UI batch/on-demand projection из mock authoritative Character/NPC state.
 */
export class MockGameRuntimeProjectionSource implements IGameRuntimeProjectionSource {
  async getRuntimeEntity(
    gameId: number,
    entityKey: CombatEntityKey,
    projectionLevel: 'summary' | 'full',
    signal?: AbortSignal,
  ): Promise<GameRuntimeEntityProjection | null> {
    const result = await this.getRuntimeEntities(
      gameId,
      { entityKeys: [entityKey], projectionLevel },
      signal,
    );

    return result.projections[0] ?? null;
  }

  async getRuntimeEntities(
    gameId: number,
    request: GameRuntimeEntityBatchRequest,
    signal?: AbortSignal,
  ): Promise<GameRuntimeEntityBatchResult> {
    if (signal?.aborted) throw new DOMException('The operation was aborted', 'AbortError');

    const requestedEntityKeys = [...new Set(request.entityKeys)];
    const requestedCharacterIds = requestedEntityKeys
      .filter((entityKey) => entityKey.startsWith('character:'))
      .map((entityKey) => Number(entityKey.slice('character:'.length)))
      .filter((id) => Number.isInteger(id));
    const requestedNpcIds = requestedEntityKeys
      .filter((entityKey) => entityKey.startsWith('npc:'))
      .map((entityKey) => Number(entityKey.slice('npc:'.length)))
      .filter((id) => Number.isInteger(id));
    const [memberships, npcs, user] = await Promise.all([
      requestedCharacterIds.length > 0 ? fetchGameCharacters(gameId, signal, requestedCharacterIds) : Promise.resolve([]),
      requestedNpcIds.length > 0 ? fetchNpcs(gameId, signal, requestedNpcIds) : Promise.resolve([]),
      this.getCurrentUser(),
    ]);
    const projections: GameRuntimeEntityProjection[] = [];
    const missingEntityKeys: CombatEntityKey[] = [];

    for (const entityKey of requestedEntityKeys) {
      const projection = this.createProjection(
        gameId,
        entityKey,
        request.projectionLevel,
        memberships,
        npcs,
        user,
      );
      if (projection) projections.push(projection);
      else missingEntityKeys.push(entityKey);
    }

    return { projections, missingEntityKeys };
  }

  private async getCurrentUser(): Promise<User | null> {
    try {
      const session = await getAuthApi().getCurrentUser();

      return session?.kind === 'user' ? session.user : null;
    } catch {
      return null;
    }
  }

  private createProjection(
    gameId: number,
    entityKey: CombatEntityKey,
    projectionLevel: 'summary' | 'full',
    memberships: Awaited<ReturnType<typeof fetchGameCharacters>>,
    npcs: Awaited<ReturnType<typeof fetchNpcs>>,
    user: User | null,
  ): GameRuntimeEntityProjection | null {
    const [kind, rawId] = entityKey.split(':');
    const id = Number(rawId);
    if (!Number.isInteger(id)) return null;

    if (kind === 'character') {
      const membership = memberships.find((entry) => entry.characterId === id);
      if (!membership || membership.membershipStatus === 'left' || !user) return null;

      const version = getStoredCharacterVersion(id);
      const visibleSections = this.getVisibleSections(user, membership.visibility, {
        ownerId: membership.characterOwnerId,
        characterId: id,
        gameId,
      });
      if (visibleSections.length === 0) return null;

      return this.toProjection(
        entityKey,
        'character',
        'characterActual',
        id,
        getCharacterActualVersion(id),
        version.spaceCode,
        version.rulesRevision,
        version.name,
        version.shortDescription,
        version,
        visibleSections,
        projectionLevel,
      );
    }

    if (kind === 'npc') {
      const npc = npcs.find((entry) => entry.id === id && entry.status === 'active');
      if (!npc || !user) return null;

      const version = captureNpcRuntimeState(id).version;
      const visibleSections = this.getVisibleSections(user, npc.visibility, {
        ownerId: null,
        characterId: id,
        gameId,
      });
      if (visibleSections.length === 0) return null;

      return this.toProjection(
        entityKey,
        'npc',
        'npcActual',
        id,
        npc.actualVersion,
        version?.spaceCode ?? null,
        version?.rulesRevision ?? null,
        npc.name,
        npc.shortDescription,
        version,
        visibleSections,
        projectionLevel,
      );
    }

    return null;
  }

  private getVisibleSections(
    user: User | null,
    visibility: Parameters<typeof sheetAccessService.visibleSheetSections>[1],
    context: Omit<SheetAccessContext, 'user'>,
  ): ReturnType<typeof sheetAccessService.visibleSheetSections> {
    return user ? sheetAccessService.visibleSheetSections(user, visibility, { ...context, user }) : [];
  }

  private toProjection(
    entityKey: CombatEntityKey,
    kind: 'character' | 'npc',
    source: 'characterActual' | 'npcActual',
    id: number,
    actualVersion: number,
    actualSpaceCode: string | null,
    actualRulesRevision: number | null,
    name: string,
    shortDescription: string | null,
    version: CharacterVersion | null,
    visibleSections: ReturnType<typeof sheetAccessService.visibleSheetSections>,
    projectionLevel: 'summary' | 'full',
  ): GameRuntimeEntityProjection {
    const baseProjection = {
      entityKey,
      kind,
      source,
      id,
      actualVersion,
      actualSpaceCode,
      actualRulesRevision,
      projectionLevel,
      summary: {
        name,
        shortDescription: visibleSections.includes('shortDescription') ? shortDescription : null,
      },
      visibleSections,
    };

    if (projectionLevel === 'summary') {
      return { ...baseProjection, projectionLevel, version: null };
    }

    return {
      ...baseProjection,
      projectionLevel,
      version: version ? this.projectVersion(version, visibleSections) : null,
    };
  }

  private projectVersion(
    version: CharacterVersion,
    visibleSections: ReturnType<typeof sheetAccessService.visibleSheetSections>,
  ): CharacterVersion {
    const isVisible = (section: (typeof SHEET_VISIBLE_SECTIONS)[number]): boolean =>
      visibleSections.includes(section);

    return {
      ...version,
      shortDescription: isVisible('shortDescription') ? version.shortDescription : null,
      fullDescription: isVisible('fullDescription') ? version.fullDescription : null,
      raceRuleCode: isVisible('race') ? version.raceRuleCode : null,
      characteristics: isVisible('characteristics') ? version.characteristics : [],
      resources: isVisible('resources') ? version.resources : [],
      abilities: isVisible('abilities') ? version.abilities : [],
      inventory: isVisible('inventory') ? version.inventory : [],
      states: isVisible('states') ? version.states : [],
      points: { osSpent: 0, olSpent: 0, olTotal: 0, orSpent: 0, orTotal: null },
      money: 0,
      ageYears: null,
      senses: [],
      customRules: undefined,
      budgets: undefined,
      ethnicityCode: undefined,
      ethnicityText: undefined,
      nativeLanguageCode: undefined,
      nativeLanguageText: undefined,
    };
  }
}
