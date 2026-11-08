import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { MagicPathSpec } from '@/modules/Roleplay/Rule/Dto/MagicPath/MagicPathSpec';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { MagicStudyLearned } from '@/modules/Roleplay/Character/Dto/Editor/MagicStudyLearned';
import type { CharacterSnapshot } from '@/modules/Roleplay/Character/Dto/Editor/CharacterSnapshot';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import type { FormulaContext } from '@/modules/Roleplay/Character/Dto/FormulaContext';

type MagicStudyGrant = Extract<Grant, { type: 'magic_study' }>;
type StudyGrant = MagicStudyGrant & { donor_code?: string };
type StudyPaths = 'any' | string[];

/**
 * Гейт изучения в рамках пути: карточка с правилом `study` открывается только
 * активным грантом `magic_study`, чей `paths` покрывает путь экземпляра.
 *
 * `scope` и `path_code` — только чтение прежних ревизий; новая логика их не делит.
 */
export class MagicStudyUnlockService {
  private readonly formula = new FormulaEvaluationService();

  resolvedMaxCost(grant: MagicStudyGrant, snapshot?: CharacterSnapshot): number {
    return this.maxCostOf(grant, snapshot);
  }

  resolvedMaxCostFromCharacteristics(
    grant: MagicStudyGrant,
    characteristicValues: Map<string, DimensionalNumberValue>,
  ): number {
    if (typeof grant.max_cost === 'number') return grant.max_cost;

    return this.studyCostLimit(grant.max_cost, {
      characteristicValues,
      abilityLevels: new Map(),
    });
  }

  isAvailableForUse(
    spec: AbilitySpec,
    domainCode: string | null,
    abilities: readonly CharacterAbility[],
    rules: Rule[],
    characteristicValues: Map<string, DimensionalNumberValue>,
  ): boolean {
    const snapshot: CharacterSnapshot = {
      abilityLevels: new Map(abilities.map((ability) => [ability.ruleCode, ability.level])),
      abilityKeywords: new Map(),
      characteristicValues,
      resourceLimits: new Map(),
      keywordCodes: new Set(),
    };
    const ruleCode = rules.find((rule) => rule.type === 'ability' && rule.spec === spec)?.code;

    return this.isJustified(spec, domainCode, this.unlocksOf(abilities, rules), [], rules, snapshot, ruleCode);
  }

  /** Карточка изучается в рамках пути. Ключевые слова больше не решают. */
  isGated(spec: AbilitySpec, _keywordCodes?: ReadonlySet<string>): boolean {
    return this.studyOf(spec) !== null;
  }

  firstLevelCost(spec: AbilitySpec, parameters: ReadonlyMap<string, number> = new Map()): number {
    if (spec.type === 'group') return 0;
    const zone = spec.zones.or ?? spec.zones.os ?? spec.zones.ol;
    if (!zone) return 0;

    if (zone.kind === 'array') return zone.levels_cost[0] ?? 0;
    if (zone.kind === 'progression') return zone.base_cost;
    if (zone.kind === 'parameter') return (parameters.get(zone.parameter_code) ?? 1) * zone.per_unit;

    return 0;
  }

  learnedEntries(abilities: readonly CharacterAbility[], rules: Rule[]): MagicStudyLearned[] {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const result: MagicStudyLearned[] = [];
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      const rule = byCode.get(ability.ruleCode);
      if (rule?.type !== 'ability' || !rule.spec || !('type' in rule.spec)) continue;
      const spec = rule.spec as AbilitySpec;
      if (this.studyOf(spec) === null) continue;
      result.push({ ruleCode: ability.ruleCode, domainCode: ability.domainCode ?? null, spec });
    }

    return result;
  }

  unlocksOf(
    abilities: readonly CharacterAbility[],
    rules: Rule[],
    _keywordCodeById: ReadonlyMap<number, string> = new Map(),
  ): StudyGrant[] {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const unlocks: StudyGrant[] = [];
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      const rule = byCode.get(ability.ruleCode);
      if (rule?.type !== 'ability' || !rule.spec || !('type' in rule.spec)) continue;
      const spec = rule.spec as AbilitySpec;
      if (spec.type === 'group') continue;
      for (const entry of spec.grants ?? []) {
        if (entry.level > ability.level) continue;
        for (const grant of entry.grants) {
          if (grant.type !== 'magic_study') continue;
          const permanent = grant.permanent !== false;
          if (!permanent && entry.level !== ability.level) continue;
          unlocks.push({ ...grant, donor_code: rule.code });
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
      for (const code of this.coveredPathCodes(rules, grant.path_code)) {
        if (seen.has(code)) continue;
        seen.add(code);
        codes.push(code);
      }
    }

    return codes;
  }

  pathCodesOf(unlocks: readonly MagicStudyGrant[]): string[] {
    const codes: string[] = [];
    const seen = new Set<string>();
    for (const grant of unlocks) {
      for (const code of this.grantPathCodes(grant)) {
        if (seen.has(code)) continue;
        seen.add(code);
        codes.push(code);
      }
    }

    return codes;
  }

  openPathCodes(
    spec: AbilitySpec,
    unlocks: readonly StudyGrant[],
    grantedPathCodes: readonly string[],
    learned: readonly MagicStudyLearned[],
    occupying: { ruleCode: string; domainCode: string | null } | null = null,
    snapshot?: CharacterSnapshot,
    rules: Rule[] = [],
    ruleCode?: string,
  ): string[] {
    const study = this.studyOf(spec);
    if (study === null) return [];
    const cost = this.firstLevelCost(spec);
    const codes: string[] = [];
    const seen = new Set<string>();
    const add = (code: string): void => {
      if (!code || seen.has(code) || !this.studyAllows(study, code)) return;
      if (rules.length > 0 && !this.isPath(rules, code)) return;
      seen.add(code);
      codes.push(code);
    };
    for (const grant of unlocks) {
      if (this.sameDonor(grant, ruleCode)) continue;
      if (!this.costWithinLimit(grant, cost, snapshot)) continue;
      if (grant.max_instances != null) {
        const used = this.usedByGrant(grant, learned).filter((entry) => !this.sameOccupying(entry, occupying));
        if (used.length >= grant.max_instances) continue;
      }

      const paths = this.grantPaths(grant);
      if (paths === 'any') {
        for (const code of grantedPathCodes) add(code);
        continue;
      }

      for (const owner of paths) {
        if (rules.length === 0) {
          add(owner);
          continue;
        }
        for (const code of this.coveredPathCodes(rules, owner)) add(code);
      }
    }

    return codes;
  }

  isJustified(
    spec: AbilitySpec,
    domainCode: string | null,
    unlocks: readonly StudyGrant[],
    _grantedPathCodes: readonly string[] = [],
    rules: Rule[] = [],
    snapshot?: CharacterSnapshot,
    ruleCode?: string,
  ): boolean {
    const study = this.studyOf(spec);
    if (study === null) return true;
    if (!domainCode || !this.studyAllows(study, domainCode)) return false;
    if (rules.length > 0 && !this.isPath(rules, domainCode)) return false;

    const cost = this.firstLevelCost(spec);

    return unlocks.some((grant) => {
      if (this.sameDonor(grant, ruleCode)) return false;
      if (!this.costWithinLimit(grant, cost, snapshot)) return false;

      return this.grantAllowsPath(grant, domainCode, rules);
    });
  }

  isBound(
    unlocks: readonly StudyGrant[],
    learned: readonly MagicStudyLearned[],
    ruleCode: string,
    domainCode: string | null,
  ): boolean {
    return unlocks.some((grant) => {
      if (grant.max_instances == null) return false;
      if (!this.grantAllowsPath(grant, domainCode)) return false;

      return this.usedByGrant(grant, learned).some(
        (entry) => entry.ruleCode === ruleCode && entry.domainCode === domainCode,
      );
    });
  }

  failureReason(
    spec: AbilitySpec,
    _keywordCodes: ReadonlySet<string>,
    unlocks: readonly StudyGrant[],
    learned: readonly MagicStudyLearned[] = [],
    currentRuleCode?: string,
    snapshot?: CharacterSnapshot,
  ): string | null {
    if (this.studyOf(spec) === null) return null;
    if (currentRuleCode && this.isAlreadyLearned(currentRuleCode, learned)) return null;
    const cost = this.firstLevelCost(spec);
    const open = unlocks.some((grant) => {
      if (this.sameDonor(grant, currentRuleCode) || !this.costWithinLimit(grant, cost, snapshot)) return false;
      if (grant.max_instances == null) return true;

      return this.usedByGrant(grant, learned).length < grant.max_instances;
    });
    if (open) return null;

    return 'изучение этой карточки в рамках пути ещё не открыто';
  }

  paidCostOverride(
    spec: AbilitySpec,
    unlocks: readonly StudyGrant[],
    learned: readonly MagicStudyLearned[],
    domainCode: string | null,
    occupying: { ruleCode: string; domainCode: string | null } | null = null,
    snapshot?: CharacterSnapshot,
    ruleCode?: string,
  ): number | null {
    const cost = this.firstLevelCost(spec);
    for (const grant of unlocks) {
      if (grant.paid_cost == null) continue;
      if (this.sameDonor(grant, ruleCode)) continue;
      if (!this.costWithinLimit(grant, cost, snapshot)) continue;
      if (!this.grantAllowsPath(grant, domainCode)) continue;
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
    _keywordCodeById: ReadonlyMap<number, string>,
  ): number {
    const byCode = new Map(rules.map((rule) => [rule.code, rule]));
    const covered = this.coveredPathCodes(rules, pathCode);
    let sum = 0;
    for (const ability of abilities) {
      if (ability.level < 1) continue;
      const rule = byCode.get(ability.ruleCode);
      if (rule?.type !== 'ability' || !rule.spec || !('type' in rule.spec)) continue;
      const spec = rule.spec as AbilitySpec;
      if (spec.type === 'group' || this.studyOf(spec) === null) continue;
      const domainCode = ability.domainCode ?? '';
      if (!domainCode || !covered.has(domainCode)) continue;
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

  private studyOf(spec: AbilitySpec): { paths: StudyPaths } | null {
    if (!('study' in spec)) return null;
    const study = (spec as { study?: { paths: StudyPaths } | null }).study;
    if (!study) return null;

    return study;
  }

  private studyAllows(study: { paths: StudyPaths }, path: string | null): boolean {
    if (!path) return false;

    return study.paths === 'any' || study.paths.includes(path);
  }

  private grantPaths(grant: MagicStudyGrant): StudyPaths {
    if (grant.paths == null) {
      if (grant.path_code) return [grant.path_code];

      return [];
    }

    return grant.paths;
  }

  private grantPathCodes(grant: MagicStudyGrant): string[] {
    const paths = this.grantPaths(grant);

    return paths === 'any' ? [] : paths;
  }

  private grantAllowsPath(grant: MagicStudyGrant, domainCode: string | null, rules: Rule[] = []): boolean {
    if (!domainCode) return false;
    const paths = this.grantPaths(grant);
    if (paths === 'any') return true;
    if (rules.length > 0) {
      return paths.some((owner) => this.coveredPathCodes(rules, owner).has(domainCode));
    }

    return paths.includes(domainCode);
  }

  private isPath(rules: Rule[], code: string): boolean {
    return rules.some((rule) => rule.type === 'magic_path' && rule.code === code);
  }

  private sameDonor(grant: StudyGrant, ruleCode?: string): boolean {
    return !!ruleCode && grant.donor_code === ruleCode;
  }

  private isAlreadyLearned(ruleCode: string, learned: readonly MagicStudyLearned[]): boolean {
    return learned.some((entry) => entry.ruleCode === ruleCode);
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
    if (!zone) return 0;

    if (zone.kind === 'array') {
      return zone.levels_cost.slice(0, level).reduce((sum, cost) => sum + cost, 0);
    }

    if (zone.kind === 'progression') {
      const steps = Math.min(level, zone.max_level);
      let sum = 0;
      for (let index = 0; index < steps; index += 1) {
        sum += zone.base_cost + index * zone.step;
      }

      return sum;
    }

    if (zone.kind === 'parameter') return level * zone.per_unit;

    return 0;
  }

  private usedByGrant(grant: MagicStudyGrant, learned: readonly MagicStudyLearned[]): MagicStudyLearned[] {
    return learned.filter((entry) => {
      const cost = this.firstLevelCost(entry.spec);

      return this.grantAllowsPath(grant, entry.domainCode) && this.costWithinLimit(grant, cost);
    });
  }

  private maxCostOf(grant: MagicStudyGrant, snapshot?: CharacterSnapshot): number {
    if (typeof grant.max_cost === 'number') return grant.max_cost;
    if (!snapshot) throw new Error('Нет характеристики для потолка изучения');

    return this.studyCostLimit(grant.max_cost, {
      characteristicValues: snapshot.characteristicValues,
      abilityLevels: snapshot.abilityLevels,
    });
  }

  private studyCostLimit(formula: Exclude<MagicStudyGrant['max_cost'], number>, context: FormulaContext): number {
    return this.formula.evaluate(formula, context);
  }

  private costWithinLimit(grant: MagicStudyGrant, cost: number, snapshot?: CharacterSnapshot): boolean {
    if (typeof grant.max_cost !== 'number' && !snapshot) return true;

    return cost <= this.maxCostOf(grant, snapshot);
  }
}
