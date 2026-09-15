import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { EthnicitySpec } from '@/modules/Roleplay/Rule/Dto/EthnicitySpec';
import type { EthnicityRole } from '@/modules/Roleplay/Rule/Enum/EthnicityRole';
import type { EthnicitySpecService } from '@/modules/Roleplay/Rule/Service/Spec/EthnicitySpecService';

/** Дерево народностей: сток не выбирается на листе, цикл parent_code запрещён. */
export class EthnicityTreeService {
  constructor(private readonly ethnicitySpec: EthnicitySpecService) {}

  isPickable(rule: Rule): boolean {
    if (rule.type !== 'ethnicity') return false;

    return this.ethnicitySpec.resolve(rule.spec).role === 'people';
  }

  pickableOptions(rules: Rule[]): { code: string; name: string }[] {
    return rules.filter((rule) => this.isPickable(rule)).map((rule) => ({ code: rule.code, name: rule.name }));
  }

  parentSelectItems(rules: Rule[], currentCode: string, role: EthnicityRole): { title: string; value: string }[] {
    return rules
      .filter((rule) => rule.type === 'ethnicity' && rule.code !== currentCode)
      .filter((rule) => {
        const spec = this.ethnicitySpec.resolve(rule.spec);
        if (role === 'stock' && spec.role !== 'stock') return false;

        return !this.wouldCreateCycle(currentCode, rule.code, rules);
      })
      .map((rule) => ({ title: rule.name, value: rule.code }));
  }

  wouldCreateCycle(code: string, parentCode: string | null, rules: Rule[]): boolean {
    if (!parentCode) return false;
    if (parentCode === code) return true;
    const byCode = this.ethnicityByCode(rules);
    let cursor: string | null = parentCode;
    const seen = new Set<string>();
    while (cursor) {
      if (cursor === code) return true;
      if (seen.has(cursor)) return true;
      seen.add(cursor);
      cursor = byCode.get(cursor)?.spec.parent_code ?? null;
    }

    return false;
  }

  findCycle(rules: Rule[]): string | null {
    const byCode = this.ethnicityByCode(rules);
    const color = new Map<string, number>();

    const visit = (code: string, stack: string[]): string | null => {
      const state = color.get(code) ?? 0;
      if (state === 1) {
        const start = stack.indexOf(code);

        return [...stack.slice(start), code].join(' → ');
      }
      if (state === 2) return null;
      color.set(code, 1);
      stack.push(code);
      const parent = byCode.get(code)?.spec.parent_code;
      if (parent && byCode.has(parent)) {
        const cycle = visit(parent, stack);
        if (cycle) return cycle;
      }
      stack.pop();
      color.set(code, 2);

      return null;
    };

    for (const code of byCode.keys()) {
      if ((color.get(code) ?? 0) !== 0) continue;
      const cycle = visit(code, []);
      if (cycle) return cycle;
    }

    return null;
  }

  private ethnicityByCode(rules: Rule[]): Map<string, { spec: EthnicitySpec }> {
    const map = new Map<string, { spec: EthnicitySpec }>();
    for (const rule of rules) {
      if (rule.type !== 'ethnicity') continue;
      map.set(rule.code, { spec: this.ethnicitySpec.resolve(rule.spec) });
    }

    return map;
  }
}
