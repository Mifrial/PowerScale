import type { AbilitySection } from '@/modules/Roleplay/Space/Dto/AbilitySection';
import type { AbilitySectionTreeNode } from '@/modules/Roleplay/Space/Dto/AbilitySectionTreeNode';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleCatalogArea } from '@/modules/Roleplay/Space/Enum/RuleCatalogArea';

export class AbilitySectionTreeService {
  normalize(sections?: AbilitySection[]): AbilitySection[] {
    const source = sections ?? [];

    return [...source]
      .map((section, index) => ({
        ...section,
        parentCode: section.parentCode ?? null,
        sortOrder: Number.isFinite(section.sortOrder) ? section.sortOrder : index,
      }))
      .sort((left, right) => left.sortOrder - right.sortOrder || left.code.localeCompare(right.code));
  }

  validate(sections?: AbilitySection[]): string[] {
    const normalized = this.normalize(sections);
    const byCode = new Map(normalized.map((section) => [section.code, section]));
    const errors: string[] = [];
    const seenCodes = new Set<string>();

    for (const section of normalized) {
      if (!section.code.trim()) errors.push('У секции отсутствует код');
      if (seenCodes.has(section.code)) errors.push(`Код секции дублируется: ${section.code}`);
      seenCodes.add(section.code);
      if (section.parentCode && !byCode.has(section.parentCode)) {
        errors.push(`Родительская секция не найдена: ${section.parentCode}`);
      }
      if (this.hasCycle(section.code, byCode)) errors.push(`Цикл в дереве секций: ${section.code}`);
    }

    return [...new Set(errors)];
  }

  flatten(sections?: AbilitySection[]): AbilitySectionTreeNode[] {
    const normalized = this.normalize(sections);
    const childrenByParent = new Map<string | null, AbilitySection[]>();
    for (const section of normalized) {
      const children = childrenByParent.get(section.parentCode) ?? [];
      children.push(section);
      childrenByParent.set(section.parentCode, children);
    }

    const result: AbilitySectionTreeNode[] = [];
    const visit = (parentCode: string | null, depth: number, parentPath: string): void => {
      for (const section of childrenByParent.get(parentCode) ?? []) {
        const path = parentPath ? `${parentPath} → ${section.name}` : section.name;
        result.push({ ...section, depth, path });
        visit(section.code, depth + 1, path);
      }
    };
    visit(null, 0, '');

    return result;
  }

  descendantCodes(sectionCode: string, sections?: AbilitySection[]): string[] {
    const normalized = this.normalize(sections);
    const descendants = new Set<string>([sectionCode]);
    let changed = true;
    while (changed) {
      changed = false;
      for (const section of normalized) {
        if (section.parentCode && descendants.has(section.parentCode) && !descendants.has(section.code)) {
          descendants.add(section.code);
          changed = true;
        }
      }
    }

    return [...descendants];
  }

  subtreeForArea(area: RuleCatalogArea, sections?: AbilitySection[]): AbilitySection[] {
    const normalized = this.normalize(sections);
    const root = normalized.find((section) => section.catalogRootFor === area);
    if (!root) return [];
    const descendants = new Set(this.descendantCodes(root.code, normalized));

    return normalized
      .filter((section) => descendants.has(section.code) && section.code !== root.code)
      .map((section) => ({
        ...section,
        parentCode: section.parentCode === root.code ? null : section.parentCode,
      }));
  }

  validateRuleSections(rules: Rule[], sections?: AbilitySection[]): string[] {
    const knownCodes = new Set(this.normalize(sections).map((section) => section.code));
    const errors: string[] = [];
    for (const rule of rules) {
      const sectionCode = rule.catalogSection;
      if (!sectionCode) continue;
      if (!knownCodes.has(sectionCode)) {
        errors.push(`Правило ${rule.code} ссылается на неизвестную секцию: ${sectionCode}`);
      }
    }

    return errors;
  }

  private hasCycle(sectionCode: string, sections: Map<string, AbilitySection>): boolean {
    const visited = new Set<string>();
    let current: string | null = sectionCode;
    while (current) {
      if (visited.has(current)) return true;
      visited.add(current);
      current = sections.get(current)?.parentCode ?? null;
    }

    return false;
  }
}
