import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { MagicPathSpec } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { MagicStudyLearned } from '@/modules/Roleplay/Character/Dto/Editor/MagicStudyLearned';

type MagicStudyGrant = Extract<Grant, { type: 'magic_study' }>;

/**
 * Гейт изучения волшебства: по умолчанию навыки с признаком «волшебство» закрыты,
 * пока грант magic_study не откроет подходящий scope и потолок Стоимости.
 * Навыки, сами дающие путь или такое открытие, не требуют чужого гранта.
 */
export class MagicStudyUnlockService {
  isGated(spec: AbilitySpec, keywordCodes: ReadonlySet<string>): boolean {
    if (spec.type === 'group' || spec.type === 'trait') return false;
    if (!keywordCodes.has('magic')) return false;
    if (this.hasAutomaticZone(spec)) return false;

    return !this.isEntry(spec);
  }

  firstLevelCost(spec: AbilitySpec): number {
    if (spec.type === 'group') return 0;
    const zone = spec.zones.or ?? spec.zones.os ?? spec.zones.ol;
    if (!zone || zone.kind !== 'array') return 0;

    return zone.levels_cost[0] ?? 0;
  }

  learnedEntries(abilities: readonly CharacterAbility[], rules: Rule[]): MagicStudyLearned[] {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const result: MagicStudyLearned[] = [];
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      const rule = byCode.get(ability.ruleCode);
      if (rule?.type !== 'ability' || !rule.spec || !('type' in rule.spec)) continue;
      const spec = rule.spec as AbilitySpec;
      result.push({ ruleCode: ability.ruleCode, domainCode: ability.domainCode ?? null, spec });
    }

    return result;
  }

  unlocksOf(
    abilities: readonly CharacterAbility[],
    rules: Rule[],
    keywordCodeById: ReadonlyMap<number, string> = new Map(),
  ): MagicStudyGrant[] {
    const pathRuleCodes = new Set(rules.filter((rule) => rule.type === 'magic_path').map((rule) => rule.code));
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const unlocks: MagicStudyGrant[] = [];
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      const rule = byCode.get(ability.ruleCode);
      if (rule?.type !== 'ability' || !rule.spec || !('type' in rule.spec)) continue;
      const spec = rule.spec as AbilitySpec;
      if (spec.type === 'group') continue;
      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        const siblingPath = entry.grants.find((grant) => grant.type === 'magic_path')?.path_code;
        for (const grant of entry.grants) {
          if (grant.type !== 'magic_study') continue;
          const permanent = grant.permanent !== false;
          if (!permanent && entry.level !== ability.level) continue;
          unlocks.push(this.withStudyPath(grant, siblingPath, rule, pathRuleCodes, keywordCodeById));
        }
      }
    }

    return unlocks;
  }

  grantedPathCodes(abilities: readonly CharacterAbility[], rules: Rule[]): string[] {
    const codes: string[] = [];
    const seen = new Set<string>();
    for (const grant of this.activeGrants(abilities, rules)) {
      if (grant.type !== 'magic_path' || seen.has(grant.path_code)) continue;
      seen.add(grant.path_code);
      codes.push(grant.path_code);
    }

    return codes;
  }

  pathCodesOf(unlocks: readonly MagicStudyGrant[]): string[] {
    const codes: string[] = [];
    const seen = new Set<string>();
    for (const grant of unlocks) {
      if (!grant.path_code || seen.has(grant.path_code)) continue;
      seen.add(grant.path_code);
      codes.push(grant.path_code);
    }

    return codes;
  }

  openPathCodes(
    spec: AbilitySpec,
    unlocks: readonly MagicStudyGrant[],
    grantedPathCodes: readonly string[],
    learned: readonly MagicStudyLearned[],
    occupying: { ruleCode: string; domainCode: string | null } | null = null,
  ): string[] {
    const cost = this.firstLevelCost(spec);
    const scope = spec.type === 'spell' ? 'spell' : 'non_spell';
    const codes: string[] = [];
    const seen = new Set<string>();
    const add = (code: string): void => {
      if (!code || seen.has(code)) return;
      seen.add(code);
      codes.push(code);
    };
    for (const grant of unlocks) {
      if (grant.scope !== scope || cost > grant.max_cost) continue;
      if (grant.max_instances != null) {
        const used = this.usedByGrant(grant, learned).filter((entry) => !this.sameOccupying(entry, occupying));
        if (used.length >= grant.max_instances) continue;
      }
      if (grant.path_code) add(grant.path_code);
    }

    return codes;
  }

  isJustified(
    spec: AbilitySpec,
    domainCode: string | null,
    unlocks: readonly MagicStudyGrant[],
    _grantedPathCodes: readonly string[] = [],
    rules: Rule[] = [],
  ): boolean {
    const cost = this.firstLevelCost(spec);
    const scope = spec.type === 'spell' ? 'spell' : 'non_spell';

    return unlocks.some((grant) => {
      if (grant.scope !== scope || cost > grant.max_cost) return false;
      if (!grant.path_code) return spec.type !== 'group';

      return grant.path_code === domainCode || this.pathCovers(rules, grant.path_code, domainCode);
    });
  }

  isBound(
    unlocks: readonly MagicStudyGrant[],
    learned: readonly MagicStudyLearned[],
    ruleCode: string,
    domainCode: string | null,
  ): boolean {
    return unlocks.some((grant) => {
      if (grant.max_instances == null) return false;

      return this.usedByGrant(grant, learned).some(
        (entry) => entry.ruleCode === ruleCode && entry.domainCode === domainCode,
      );
    });
  }

  failureReason(
    spec: AbilitySpec,
    keywordCodes: ReadonlySet<string>,
    unlocks: readonly MagicStudyGrant[],
    learned: readonly MagicStudyLearned[] = [],
    currentRuleCode?: string,
  ): string | null {
    if (!this.isGated(spec, keywordCodes)) return null;
    if (currentRuleCode && learned.some((entry) => entry.ruleCode === currentRuleCode)) return null;
    const cost = this.firstLevelCost(spec);
    const scope = spec.type === 'spell' ? 'spell' : 'non_spell';
    const open = unlocks.some((grant) => this.grantOpens(grant, spec, cost, scope, learned));
    if (open) return null;

    return scope === 'spell'
      ? 'изучение заклинаний этого пути ещё не открыто'
      : 'изучение навыков волшебства этого пути ещё не открыто';
  }

  paidCostOverride(
    spec: AbilitySpec,
    unlocks: readonly MagicStudyGrant[],
    learned: readonly MagicStudyLearned[],
    domainCode: string | null,
    occupying: { ruleCode: string; domainCode: string | null } | null = null,
  ): number | null {
    const cost = this.firstLevelCost(spec);
    const scope = spec.type === 'spell' ? 'spell' : 'non_spell';
    for (const grant of unlocks) {
      if (grant.paid_cost == null) continue;
      if (!this.matchesGrant(grant, spec, cost, scope, domainCode)) continue;
      if (grant.max_instances == null) return grant.paid_cost;
      const used = this.usedByGrant(grant, learned).filter((entry) => !this.sameOccupying(entry, occupying));
      if (used.length < grant.max_instances) return grant.paid_cost;
    }

    return null;
  }

  pathExperience(
    abilities: readonly CharacterAbility[],
    rules: Rule[],
    pathCode: string,
    keywordCodeById: ReadonlyMap<number, string>,
  ): number {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const covered = this.coveredPathCodes(rules, pathCode);
    let sum = 0;
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      const rule = byCode.get(ability.ruleCode);
      if (rule?.type !== 'ability' || !rule.spec || !('type' in rule.spec)) continue;
      const spec = rule.spec as AbilitySpec;
      if (spec.type === 'group') continue;
      const keywords = this.keywordCodesOf(rule, keywordCodeById);
      const onPath =
        (ability.domainCode != null && covered.has(ability.domainCode)) ||
        [...keywords].some((code) => covered.has(code));
      if (!onPath) continue;
      sum += this.purchasedCost(spec, ability.level);
    }

    return sum;
  }

  /** Пути, которые owner считает своими: сам и includes_path_codes (транзитивно, без циклов). */
  coveredPathCodes(rules: Rule[], ownerPath: string): Set<string> {
    const includesByPath = new Map<string, string[]>();
    for (const rule of rules) {
      if (rule.type !== 'magic_path') continue;
      const spec = rule.spec as MagicPathSpec | undefined;
      includesByPath.set(
        rule.code,
        (spec?.includes_path_codes ?? []).filter((code) => code !== rule.code),
      );
    }
    const covered = new Set<string>([ownerPath]);
    const queue = [ownerPath];
    while (queue.length > 0) {
      const current = queue.shift();
      if (!current) break;
      for (const included of includesByPath.get(current) ?? []) {
        if (covered.has(included)) continue;
        covered.add(included);
        queue.push(included);
      }
    }

    return covered;
  }

  pathCovers(rules: Rule[], ownerPath: string | null | undefined, learnedPath: string | null | undefined): boolean {
    if (!ownerPath || !learnedPath) return false;

    return this.coveredPathCodes(rules, ownerPath).has(learnedPath);
  }

  knownOnPath(
    abilities: readonly CharacterAbility[],
    ruleCode: string,
    ownerPath: string,
    rules: Rule[],
    minLevel = 1,
  ): boolean {
    const covered = this.coveredPathCodes(rules, ownerPath);

    return abilities.some(
      (ability) =>
        ability.ruleCode === ruleCode &&
        ability.level >= minLevel &&
        ability.domainCode != null &&
        covered.has(ability.domainCode),
    );
  }

  keywordCodesOf(rule: Rule, keywordCodeById: ReadonlyMap<number, string>): Set<string> {
    return new Set(
      (rule.keywordIds ?? []).map((id) => keywordCodeById.get(id)).filter((code): code is string => !!code),
    );
  }

  private withStudyPath(
    grant: MagicStudyGrant,
    siblingPath: string | undefined,
    rule: Rule,
    pathRuleCodes: ReadonlySet<string>,
    keywordCodeById: ReadonlyMap<number, string>,
  ): MagicStudyGrant {
    if (grant.path_code) return grant;
    const fromKeyword = [...this.keywordCodesOf(rule, keywordCodeById)].find((code) => pathRuleCodes.has(code));
    const pathCode = siblingPath ?? fromKeyword;
    if (!pathCode) return grant;

    return { ...grant, path_code: pathCode };
  }

  private sameOccupying(
    entry: MagicStudyLearned,
    occupying: { ruleCode: string; domainCode: string | null } | null,
  ): boolean {
    if (!occupying) return false;

    return entry.ruleCode === occupying.ruleCode && entry.domainCode === occupying.domainCode;
  }

  private activeGrants(abilities: readonly CharacterAbility[], rules: Rule[]): Grant[] {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const grants: Grant[] = [];
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      const rule = byCode.get(ability.ruleCode);
      if (rule?.type !== 'ability' || !rule.spec || !('type' in rule.spec)) continue;
      const spec = rule.spec as AbilitySpec;
      if (spec.type === 'group') continue;
      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          const permanent = grant.permanent !== false;
          if (!permanent && entry.level !== ability.level) continue;
          grants.push(grant);
        }
      }
    }

    return grants;
  }

  private purchasedCost(spec: AbilitySpec, level: number): number {
    if (spec.type === 'group') return 0;
    const zone = spec.zones.or ?? spec.zones.os ?? spec.zones.ol;
    if (!zone || zone.kind !== 'array') return 0;

    return zone.levels_cost.slice(0, level).reduce((sum, cost) => sum + cost, 0);
  }

  private grantOpens(
    grant: MagicStudyGrant,
    spec: AbilitySpec,
    cost: number,
    scope: MagicStudyGrant['scope'],
    learned: readonly MagicStudyLearned[],
  ): boolean {
    if (grant.scope !== scope || cost > grant.max_cost) return false;
    if (grant.max_instances == null) return true;

    return this.usedByGrant(grant, learned).length < grant.max_instances;
  }

  private matchesGrant(
    grant: MagicStudyGrant,
    spec: AbilitySpec,
    cost: number,
    scope: MagicStudyGrant['scope'],
    domainCode: string | null,
  ): boolean {
    if (grant.scope !== scope || cost > grant.max_cost) return false;
    if (grant.path_code) return grant.path_code === domainCode;

    return spec.type !== 'group';
  }

  private usedByGrant(grant: MagicStudyGrant, learned: readonly MagicStudyLearned[]): MagicStudyLearned[] {
    return learned.filter((entry) => {
      const cost = this.firstLevelCost(entry.spec);
      const scope = entry.spec.type === 'spell' ? 'spell' : 'non_spell';

      return this.matchesGrant(grant, entry.spec, cost, scope, entry.domainCode);
    });
  }

  private isEntry(spec: AbilitySpec): boolean {
    if (spec.type === 'group') return false;

    return spec.grants.some((level) =>
      level.grants.some(
        (grant) => grant.type === 'magic_path' || grant.type === 'magic_study' || grant.type === 'ability',
      ),
    );
  }

  private hasAutomaticZone(spec: AbilitySpec): boolean {
    if (spec.type === 'group') return false;

    return Object.values(spec.zones).some((zone) => zone?.kind === 'automatic');
  }
}
