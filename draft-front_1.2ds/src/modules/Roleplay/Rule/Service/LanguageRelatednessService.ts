import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { LanguageSpec } from '@/modules/Roleplay/Rule/Dto/LanguageSpec';
import type { LanguageKinship } from '@/modules/Roleplay/Rule/Dto/LanguageKinship';
import type { LanguageRole } from '@/modules/Roleplay/Rule/Enum/LanguageRole';
import type { LanguageSpecService } from '@/modules/Roleplay/Rule/Service/Spec/LanguageSpecService';

/** Близость языков по дереву parent_code: точный / родственник / семья / нет. */
export class LanguageRelatednessService {
  constructor(private readonly languageSpec: LanguageSpecService) {}

  isSpeakable(rule: Rule): boolean {
    if (rule.type !== 'language') return false;

    return this.languageSpec.resolve(rule.spec).role === 'language';
  }

  speakableOptions(rules: Rule[]): { code: string; name: string }[] {
    return rules.filter((rule) => this.isSpeakable(rule)).map((rule) => ({ code: rule.code, name: rule.name }));
  }

  parentSelectItems(rules: Rule[], currentCode: string, role: LanguageRole): { title: string; value: string }[] {
    return rules
      .filter((rule) => rule.type === 'language' && rule.code !== currentCode)
      .filter((rule) => {
        const spec = this.languageSpec.resolve(rule.spec);
        if (role === 'stock' && spec.role !== 'stock') return false;

        return !this.wouldCreateCycle(currentCode, rule.code, rules);
      })
      .map((rule) => ({ title: rule.name, value: rule.code }));
  }

  wouldCreateCycle(code: string, parentCode: string | null, rules: Rule[]): boolean {
    if (!parentCode) return false;
    if (parentCode === code) return true;
    const byCode = this.languageByCode(rules);
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
    const byCode = this.languageByCode(rules);
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

  kinship(ownedCode: string, targetCode: string, rules: Rule[]): LanguageKinship {
    if (!ownedCode || !targetCode) return 'none';
    if (ownedCode === targetCode) return 'exact';
    const byCode = this.languageByCode(rules);
    const owned = byCode.get(ownedCode);
    const target = byCode.get(targetCode);
    if (!owned || !target) return 'none';
    if (owned.spec.role !== 'language' || target.spec.role !== 'language') return 'none';
    if (
      this.isLanguageAncestor(ownedCode, targetCode, byCode) ||
      this.isLanguageAncestor(targetCode, ownedCode, byCode)
    ) {
      return 'related';
    }
    const ownedStock = this.nearestStock(ownedCode, byCode);
    const targetStock = this.nearestStock(targetCode, byCode);
    if (ownedStock && ownedStock === targetStock) return 'related';
    const ownedRoot = this.rootCode(ownedCode, byCode);
    const targetRoot = this.rootCode(targetCode, byCode);
    if (ownedRoot && ownedRoot === targetRoot) return 'family';

    return 'none';
  }

  effectiveSpoken(
    instances: readonly { domainCode?: string | null; level: number }[],
    targetCode: string,
    rules: Rule[],
  ): number {
    let best = 0;
    for (const instance of instances) {
      const code = instance.domainCode;
      if (!code) continue;
      const kin = this.kinship(code, targetCode, rules);
      if (kin === 'none') continue;
      const cut = kin === 'exact' ? 0 : kin === 'related' ? 1 : 2;
      best = Math.max(best, Math.max(0, instance.level - cut));
    }

    return best;
  }

  private languageByCode(rules: Rule[]): Map<string, { name: string; spec: LanguageSpec }> {
    const map = new Map<string, { name: string; spec: LanguageSpec }>();
    for (const rule of rules) {
      if (rule.type !== 'language') continue;
      map.set(rule.code, { name: rule.name, spec: this.languageSpec.resolve(rule.spec) });
    }

    return map;
  }

  private nearestStock(code: string, byCode: Map<string, { spec: LanguageSpec }>): string | null {
    let cursor: string | null = code;
    const seen = new Set<string>();
    while (cursor && !seen.has(cursor)) {
      seen.add(cursor);
      const node = byCode.get(cursor);
      if (!node) return null;
      if (node.spec.role === 'stock') return cursor;
      cursor = node.spec.parent_code;
    }

    return null;
  }

  private rootCode(code: string, byCode: Map<string, { spec: LanguageSpec }>): string | null {
    let cursor: string | null = code;
    let last: string | null = null;
    const seen = new Set<string>();
    while (cursor && !seen.has(cursor)) {
      seen.add(cursor);
      last = cursor;
      cursor = byCode.get(cursor)?.spec.parent_code ?? null;
    }

    return last;
  }

  private isLanguageAncestor(
    ancestor: string,
    descendant: string,
    byCode: Map<string, { spec: LanguageSpec }>,
  ): boolean {
    let cursor: string | null = descendant;
    const seen = new Set<string>();
    while (cursor && !seen.has(cursor)) {
      seen.add(cursor);
      const node = byCode.get(cursor);
      if (!node) return false;
      const parent = node.spec.parent_code;
      if (!parent) return false;
      if (parent === ancestor) {
        const parentNode = byCode.get(parent);

        return parentNode?.spec.role === 'language';
      }
      cursor = parent;
    }

    return false;
  }
}
