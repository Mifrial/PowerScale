import type { AbilityCost } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityCost';
import type { AbilityParameter } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityParameter';
import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';
import type { AbilitySpecBase } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecBase';
import type { ImportedRuleNameParse } from '@/modules/Roleplay/Rule/Dto/ImportedRuleNameParse';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { IMPORT_NAME_DOMAIN_ALIASES } from '@/modules/Roleplay/Rule/Constant/Ability/IMPORT_NAME_DOMAIN_ALIASES';

const SCHEMA_TAIL = /\s*\(([^)]*)\)\s*$/;
const LEVEL_TOKEN = /^(х|x|n)(?:\s+из\s+(\d+))?$/i;

export class ImportedRuleNameService {
  constructor(private readonly domainAliases: Record<string, string> = IMPORT_NAME_DOMAIN_ALIASES) {}

  parse(name: string): ImportedRuleNameParse {
    const match = name.match(SCHEMA_TAIL);
    if (!match) {
      return { name, domainRef: null, parameterCode: null, parameterMax: null, hadSchemaTail: false };
    }
    const tokens = match[1]
      .split(',')
      .map((token) => token.trim().replace(/\s+/g, ' '))
      .filter((token) => token.length > 0);
    if (tokens.length === 0) {
      return { name, domainRef: null, parameterCode: null, parameterMax: null, hadSchemaTail: false };
    }
    let domainRef: string | null = null;
    let parameterCode: string | null = null;
    let parameterMax: number | null = null;
    for (const token of tokens) {
      const level = token.toLowerCase().replace(/ё/g, 'е').match(LEVEL_TOKEN);
      if (level) {
        parameterCode = level[1].toLowerCase() === 'n' ? 'n' : 'x';
        parameterMax = level[2] ? Number(level[2]) : null;

        continue;
      }
      const alias = this.domainAliases[token.toLowerCase().replace(/ё/g, 'е')];
      if (!alias) {
        return { name, domainRef: null, parameterCode: null, parameterMax: null, hadSchemaTail: false };
      }
      domainRef = alias;
    }

    return {
      name: name.slice(0, match.index).trimEnd(),
      domainRef,
      parameterCode,
      parameterMax,
      hadSchemaTail: true,
    };
  }

  sanitizeCatalog(rules: Rule[]): Rule[] {
    return rules.map((rule) => this.sanitizeRule(rule));
  }

  sanitizeRule(rule: Rule): Rule {
    const parsed = this.parse(rule.name);
    if (!parsed.hadSchemaTail) return rule;
    const next: Rule = { ...rule, name: parsed.name };
    if (rule.type === 'ability' && this.isZonedAbilitySpec(rule.spec)) {
      next.spec = this.fillAbilitySpec(rule.spec, parsed);
    }

    return next;
  }

  private fillAbilitySpec(
    spec: AbilitySpecBase & AbilitySpec,
    parsed: ImportedRuleNameParse,
  ): AbilitySpecBase & AbilitySpec {
    const next: AbilitySpecBase & AbilitySpec = { ...spec };
    if (parsed.domainRef && (next.domain_ref === undefined || next.domain_ref === null || next.domain_ref === '')) {
      next.domain_ref = parsed.domainRef;
      if (!next.multiple) next.multiple = true;
    }
    if (this.shouldAddParameter(next, parsed)) {
      const code = parsed.parameterCode ?? 'x';
      const max = parsed.parameterMax ?? 1;
      next.parameters = [...(next.parameters ?? []), this.purchaseParameter(code, max)];
    }

    return next;
  }

  private shouldAddParameter(spec: AbilitySpecBase, parsed: ImportedRuleNameParse): boolean {
    if (!parsed.parameterCode || parsed.parameterMax === null) return false;
    if ((spec.parameters ?? []).some((parameter) => parameter.code === parsed.parameterCode)) return false;
    const encodedMax = this.encodedZoneMax(spec);
    if (encodedMax === null) return false;

    return encodedMax === 1 && parsed.parameterMax > 1;
  }

  private encodedZoneMax(spec: AbilitySpecBase): number | null {
    const costs = Object.values(spec.zones ?? {}).filter((cost): cost is AbilityCost => cost !== undefined);
    if (
      costs.some(
        (cost) => cost.kind === 'parameter' || cost.kind === 'parameter_table' || cost.kind === 'parameter_sum_tables',
      )
    )
      return null;
    let max = 0;
    for (const cost of costs) {
      if (cost.kind === 'array') max = Math.max(max, cost.levels_cost.length);
      if (cost.kind === 'progression') max = Math.max(max, cost.max_level);
    }

    return max;
  }

  private purchaseParameter(code: string, max: number): AbilityParameter {
    return {
      code,
      label: code.toUpperCase(),
      resolution: 'purchase',
      default: 1,
      min: 1,
      max,
    };
  }

  private isZonedAbilitySpec(spec: Rule['spec']): spec is AbilitySpecBase & AbilitySpec {
    return Boolean(spec && typeof spec === 'object' && 'zones' in spec);
  }
}
