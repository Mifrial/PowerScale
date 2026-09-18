import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';
import type { PendingActionEffect } from '@/modules/Roleplay/Game/Dto/PendingActionEffect';
import type { LastStrikeSnapshot } from '@/modules/Roleplay/Game/Dto/LastStrikeSnapshot';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { actionEffectService } from '@/modules/Roleplay/Game/Service/Instance/actionEffectService';

/**
 * Снимок прошлого удара и добивание РУ (Смертельный удар).
 */
export class LastStrikeService {
  snapshotOf(pendingEffects: PendingActionEffect[]): LastStrikeSnapshot | null {
    const pending = [...pendingEffects].reverse().find((item) => item.effect.type === 'last_strike_snapshot');
    if (!pending || pending.effect.type !== 'last_strike_snapshot') return null;

    return { kind: pending.effect.kind, hits: pending.effect.hits };
  }

  previousSr(snapshot: LastStrikeSnapshot | null, targetKey: string): number {
    if (!snapshot) return 0;

    return snapshot.hits.find((hit) => hit.targetKey === targetKey)?.attackSr ?? 0;
  }

  canFollowUp(rule: Rule | null | undefined, snapshot: LastStrikeSnapshot | null, targetKey: string | null): boolean {
    const allowed = this.followUpTargetKeys(rule, snapshot);
    if (allowed === null) return true;
    if (!targetKey) return false;

    return allowed.includes(targetKey);
  }

  excludeForSelect(
    rule: Rule | null | undefined,
    snapshot: LastStrikeSnapshot | null,
    actorKey: string | null,
    candidateKeys: readonly string[],
  ): string[] {
    const actor = actorKey ? [actorKey] : [];
    const allowed = this.followUpTargetKeys(rule, snapshot);
    if (allowed === null) return actor;
    const allowedSet = new Set(allowed);

    return [...new Set([...actor, ...candidateKeys.filter((key) => !allowedSet.has(key))])];
  }

  followUpTargetKeys(rule: Rule | null | undefined, snapshot: LastStrikeSnapshot | null): string[] | null {
    const previousAttack = actionEffectService
      .effectsOf(rule)
      .find((effect) => effect.type === 'require_previous_attack');
    if (previousAttack) return snapshot ? snapshot.hits.map((hit) => hit.targetKey) : [];
    const gate = actionEffectService.effectsOf(rule).find((effect) => effect.type === 'require_previous_strike');
    if (!gate || gate.type !== 'require_previous_strike') return null;
    if (!snapshot || snapshot.kind === gate.not_kind) return [];
    const hits = snapshot.hits.filter((hit) => hit.attackSr >= gate.min_sr);

    return hits.map((hit) => hit.targetKey);
  }

  reactionOf(snapshot: LastStrikeSnapshot | null, targetKey: string | null): string | null {
    if (!snapshot || !targetKey) return null;

    return snapshot.hits.find((hit) => hit.targetKey === targetKey)?.reaction ?? null;
  }

  boostedSr(thisSr: number, previousSr: number, rule: Rule | null | undefined): number {
    const boost = actionEffectService
      .effectsOf(rule)
      .find((effect): effect is Extract<ActionEffect, { type: 'attack_sr_from_previous' }> => {
        return effect.type === 'attack_sr_from_previous';
      });
    if (!boost || thisSr <= 0 || previousSr <= 0) return thisSr;
    const bonus = Math.floor(previousSr / boost.floor_div);
    const capped = boost.cap === 'double_this' ? thisSr * 2 : thisSr + bonus;

    return Math.min(thisSr + bonus, capped);
  }

  kindOf(rule: Rule | null | undefined): 'lethal' | 'other' {
    return actionEffectService.effectsOf(rule).some((effect) => effect.type === 'attack_sr_from_previous')
      ? 'lethal'
      : 'other';
  }

  describe(snapshot: LastStrikeSnapshot, nameOf: (targetKey: string) => string): string {
    if (snapshot.hits.length === 0) return 'прошлый удар: нет целей';
    const hits = snapshot.hits
      .map((hit) => {
        const name = nameOf(hit.targetKey);
        return hit.attackSr > 0 ? `${name}: попал, РУ ${hit.attackSr}` : `${name}: промах`;
      })
      .join('; ');

    return `прошлый удар (${hits})`;
  }

  replaceOnPending(
    pendingEffects: PendingActionEffect[],
    snapshot: LastStrikeSnapshot,
    sourceRuleCode: string,
  ): PendingActionEffect[] {
    const without = pendingEffects.filter((item) => item.effect.type !== 'last_strike_snapshot');

    return [
      ...without,
      {
        sourceRuleCode,
        effect: { type: 'last_strike_snapshot', kind: snapshot.kind, hits: snapshot.hits },
      },
    ];
  }
}
