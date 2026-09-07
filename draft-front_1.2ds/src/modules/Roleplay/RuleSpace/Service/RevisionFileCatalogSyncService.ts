import type { Keyword } from '@/modules/Roleplay/Keyword/Dto/Keyword';
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { IKeywordApi } from '@/modules/Roleplay/Keyword/Interface/IKeywordApi';
import type { IMechanicApi } from '@/modules/Roleplay/Mechanic/Interface/IMechanicApi';
import type { RevisionFile } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFile';
import type { RevisionFileCatalogPlan } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileCatalogPlan';
import type { RevisionFileKeyword } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileKeyword';
import type { RevisionFileMechanic } from '@/modules/Roleplay/RuleSpace/Dto/RevisionFileMechanic';

/**
 * Снимки файла ревизии → create/update/deactivate глобального справочника.
 */
export class RevisionFileCatalogSyncService {
  constructor(
    private readonly getKeywordsApi: () => IKeywordApi,
    private readonly getMechanicsApi: () => IMechanicApi,
  ) {}

  plan(
    file: RevisionFile,
    liveKeywords: readonly Keyword[],
    liveMechanics: readonly Mechanic[],
  ): RevisionFileCatalogPlan {
    const keywordsByCode = new Map(liveKeywords.map((row) => [row.code, row]));
    const mechanicsByKey = new Map(liveMechanics.map((row) => [`${row.code}\0${row.version}`, row]));
    const keywordCreates: RevisionFileKeyword[] = [];
    const keywordUpdates: RevisionFileCatalogPlan['keywordUpdates'] = [];
    const keywordDeactivates: number[] = [];
    const cannotReactivate: string[] = [];
    let keywordUnchangedCount = 0;
    for (const snapshot of file.keywords) {
      const live = keywordsByCode.get(snapshot.code);
      if (!live) {
        keywordCreates.push(snapshot);
        continue;
      }
      const sameText = live.name === snapshot.name && live.description === snapshot.description;
      const sameActive = live.active === snapshot.active;
      if (!sameText) {
        keywordUpdates.push({ id: live.id, name: snapshot.name, description: snapshot.description });
      }
      if (live.active && !snapshot.active) keywordDeactivates.push(live.id);
      if (!live.active && snapshot.active) cannotReactivate.push(snapshot.code);
      if (sameText && sameActive) keywordUnchangedCount += 1;
    }
    const mechanicCreates: RevisionFileMechanic[] = [];
    const mechanicUpdates: RevisionFileCatalogPlan['mechanicUpdates'] = [];
    let mechanicUnchangedCount = 0;
    for (const snapshot of file.mechanics) {
      const live = mechanicsByKey.get(`${snapshot.code}\0${snapshot.version}`);
      if (!live) {
        mechanicCreates.push(snapshot);
        continue;
      }
      if (live.name === snapshot.name && live.description === snapshot.description) {
        mechanicUnchangedCount += 1;
        continue;
      }
      mechanicUpdates.push({ id: live.id, name: snapshot.name, description: snapshot.description });
    }

    return {
      keywordCreates,
      keywordUpdates,
      keywordDeactivates,
      keywordUnchangedCount,
      cannotReactivate,
      mechanicCreates,
      mechanicUpdates,
      mechanicUnchangedCount,
    };
  }

  merge(
    file: RevisionFile,
    liveKeywords: readonly Keyword[],
    liveMechanics: readonly Mechanic[],
  ): { keywords: Keyword[]; mechanics: Mechanic[] } {
    const keywords = liveKeywords.map((row) => ({ ...row }));
    const byCode = new Map(keywords.map((row) => [row.code, row]));
    let nextKeywordId = -1;
    for (const snapshot of file.keywords) {
      const live = byCode.get(snapshot.code);
      if (live) {
        live.name = snapshot.name;
        live.description = snapshot.description;
        if (live.active && !snapshot.active) live.active = false;
        continue;
      }
      const created: Keyword = {
        id: nextKeywordId,
        code: snapshot.code,
        name: snapshot.name,
        description: snapshot.description,
        active: snapshot.active,
      };
      nextKeywordId -= 1;
      keywords.push(created);
      byCode.set(created.code, created);
    }
    const mechanics = liveMechanics.map((row) => ({ ...row }));
    const byKey = new Map(mechanics.map((row) => [`${row.code}\0${row.version}`, row]));
    let nextMechanicId = -1;
    for (const snapshot of file.mechanics) {
      const key = `${snapshot.code}\0${snapshot.version}`;
      const live = byKey.get(key);
      if (live) {
        live.name = snapshot.name;
        live.description = snapshot.description;
        continue;
      }
      const created: Mechanic = {
        id: nextMechanicId,
        code: snapshot.code,
        name: snapshot.name,
        description: snapshot.description,
        version: snapshot.version,
      };
      nextMechanicId -= 1;
      mechanics.push(created);
      byKey.set(key, created);
    }

    return { keywords, mechanics };
  }

  isDirty(plan: RevisionFileCatalogPlan): boolean {
    return (
      plan.keywordCreates.length +
        plan.keywordUpdates.length +
        plan.keywordDeactivates.length +
        plan.mechanicCreates.length +
        plan.mechanicUpdates.length >
      0
    );
  }

  formatSummary(plan: RevisionFileCatalogPlan): string {
    const cannot = plan.cannotReactivate.length > 0 ? `; нельзя включить: ${plan.cannotReactivate.join(', ')}` : '';

    return (
      `Признаки: +${plan.keywordCreates.length} / ~${plan.keywordUpdates.length} / выкл. ${plan.keywordDeactivates.length} / без изменений ${plan.keywordUnchangedCount}` +
      `${cannot}. Механики: +${plan.mechanicCreates.length} / ~${plan.mechanicUpdates.length} / без изменений ${plan.mechanicUnchangedCount}`
    );
  }

  async apply(plan: RevisionFileCatalogPlan, signal?: AbortSignal): Promise<void> {
    for (const snapshot of plan.keywordCreates) {
      const created = await this.getKeywordsApi().createKeyword(
        { code: snapshot.code, name: snapshot.name, description: snapshot.description },
        signal,
      );
      if (!snapshot.active) await this.getKeywordsApi().deactivate(created.id, signal);
    }
    for (const patch of plan.keywordUpdates) {
      await this.getKeywordsApi().updateKeyword(patch.id, { name: patch.name, description: patch.description }, signal);
    }
    for (const id of plan.keywordDeactivates) {
      await this.getKeywordsApi().deactivate(id, signal);
    }
    for (const snapshot of plan.mechanicCreates) {
      await this.getMechanicsApi().createMechanic(
        {
          code: snapshot.code,
          name: snapshot.name,
          version: snapshot.version,
          description: snapshot.description,
        },
        signal,
      );
    }
    for (const patch of plan.mechanicUpdates) {
      await this.getMechanicsApi().updateMechanic(
        patch.id,
        { name: patch.name, description: patch.description },
        signal,
      );
    }
  }
}
