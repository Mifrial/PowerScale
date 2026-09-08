import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { AbilitySectionMutation } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySectionMutation';
import type { AbilitySectionPatch } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySectionPatch';
import type { AbilitySectionTreeNode } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySectionTreeNode';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { RuleCatalogArea } from '@/modules/Roleplay/RuleSpace/Enum/RuleCatalogArea';
import type { SectionDropPlacement } from '@/modules/Roleplay/RuleSpace/Enum/SectionDropPlacement';

/**
 * Нормализует дерево секций каталога и применяет правки черновика.
 */
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

  normalizeSortOrder(sections?: AbilitySection[], parentCode?: string | null): AbilitySection[] {
    const normalized = this.normalize(sections);
    const parents =
      parentCode === undefined ? [...new Set(normalized.map((section) => section.parentCode))] : [parentCode];
    for (const parent of parents) {
      const siblings = normalized
        .filter((section) => section.parentCode === parent)
        .sort((left, right) => left.sortOrder - right.sortOrder || left.code.localeCompare(right.code));
      siblings.forEach((section, index) => {
        section.sortOrder = (index + 1) * 10;
      });
    }

    return this.normalize(normalized);
  }

  validate(sections?: AbilitySection[]): string[] {
    const normalized = this.normalize(sections);
    const byCode = new Map(normalized.map((section) => [section.code, section]));
    const errors: string[] = [];
    const seenCodes = new Set<string>();
    const rootForOwner = new Map<string, string>();

    for (const section of normalized) {
      if (!section.code.trim()) errors.push('У секции отсутствует код');
      if (!section.name.trim()) errors.push(`У секции ${section.code || '(без кода)'} отсутствует название`);
      if (seenCodes.has(section.code)) errors.push(`Код секции дублируется: ${section.code}`);
      seenCodes.add(section.code);
      if (section.parentCode && !byCode.has(section.parentCode)) {
        errors.push(`Родительская секция не найдена: ${section.parentCode}`);
      }
      if (this.hasCycle(section.code, byCode)) errors.push(`Цикл в дереве секций: ${section.code}`);
      const rootFor = section.catalogRootFor?.trim();
      if (rootFor) {
        const owner = rootForOwner.get(rootFor);
        if (owner && owner !== section.code) {
          errors.push(`Корень каталога «${rootFor}» уже задан у ${owner}`);
        } else {
          rootForOwner.set(rootFor, section.code);
        }
      }
    }

    return [...new Set(errors)];
  }

  flatten(sections?: AbilitySection[]): AbilitySectionTreeNode[] {
    const normalized = this.normalize(sections);
    const childrenByParent = this.childrenByParent(normalized);
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

  sameCatalog(left?: AbilitySection[], right?: AbilitySection[]): boolean {
    return JSON.stringify(this.normalize(left)) === JSON.stringify(this.normalize(right));
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

  addSection(sections: AbilitySection[], section: AbilitySection): AbilitySectionMutation {
    const working = this.normalize(sections);
    if (working.some((item) => item.code === section.code)) {
      return this.fail(sections, [`Код секции дублируется: ${section.code}`]);
    }
    const parentCode = section.parentCode ?? null;
    working.push({
      ...section,
      parentCode,
      sortOrder: Number.isFinite(section.sortOrder) ? section.sortOrder : this.nextSortOrder(working, parentCode),
    });

    return this.commit(sections, this.normalizeSortOrder(working, parentCode));
  }

  updateSection(sections: AbilitySection[], code: string, patch: AbilitySectionPatch): AbilitySectionMutation {
    const working = this.normalize(sections);
    const target = working.find((section) => section.code === code);
    if (!target) return this.fail(sections, [`Секция не найдена: ${code}`]);
    const oldParent = target.parentCode;
    if (patch.name !== undefined) target.name = patch.name;
    if (patch.parentCode !== undefined) target.parentCode = patch.parentCode;
    if (patch.sortOrder !== undefined) target.sortOrder = patch.sortOrder;
    if (patch.catalogRootFor !== undefined) {
      const value = patch.catalogRootFor?.trim() ?? '';
      if (value) target.catalogRootFor = value;
      else delete target.catalogRootFor;
    }
    const next =
      patch.parentCode !== undefined || patch.sortOrder !== undefined
        ? this.normalizeSortOrder(this.normalizeSortOrder(working, oldParent), target.parentCode)
        : this.normalize(working);

    return this.commit(sections, next);
  }

  placeSection(
    sections: AbilitySection[],
    code: string,
    parentCode: string | null,
    siblingIndex: number,
  ): AbilitySectionMutation {
    const working = this.normalize(sections);
    const target = working.find((section) => section.code === code);
    if (!target) return this.fail(sections, [`Секция не найдена: ${code}`]);
    if (parentCode === code) {
      return this.fail(sections, [`Цикл в дереве секций: ${code}`]);
    }
    if (parentCode && this.descendantCodes(code, working).includes(parentCode)) {
      return this.fail(sections, [`Цикл в дереве секций: ${code}`]);
    }
    const oldParent = target.parentCode;
    target.parentCode = parentCode;
    const siblings = working
      .filter((section) => section.parentCode === parentCode && section.code !== code)
      .sort((left, right) => left.sortOrder - right.sortOrder || left.code.localeCompare(right.code));
    const index = Math.max(0, Math.min(siblingIndex, siblings.length));
    siblings.splice(index, 0, target);
    siblings.forEach((section, order) => {
      section.sortOrder = (order + 1) * 10;
    });

    return this.commit(sections, this.normalizeSortOrder(this.normalizeSortOrder(working, oldParent), parentCode));
  }

  dropSection(
    sections: AbilitySection[],
    sourceCode: string,
    targetCode: string,
    placement: SectionDropPlacement,
  ): AbilitySectionMutation {
    if (sourceCode === targetCode) return { sections: this.normalize(sections), errors: [] };
    const normalized = this.normalize(sections);
    const target = normalized.find((section) => section.code === targetCode);
    if (!target) return this.fail(sections, [`Секция не найдена: ${targetCode}`]);
    if (placement === 'child') {
      const childCount = normalized.filter(
        (section) => section.parentCode === targetCode && section.code !== sourceCode,
      ).length;

      return this.placeSection(sections, sourceCode, targetCode, childCount);
    }
    const siblings = normalized.filter(
      (section) => section.parentCode === target.parentCode && section.code !== sourceCode,
    );
    const targetIndex = siblings.findIndex((section) => section.code === targetCode);
    const index = placement === 'after' ? targetIndex + 1 : targetIndex;

    return this.placeSection(sections, sourceCode, target.parentCode, index);
  }

  removeSection(sections: AbilitySection[], code: string, rules: Rule[] = []): AbilitySectionMutation {
    const working = this.normalize(sections);
    if (working.some((section) => section.parentCode === code)) {
      return this.fail(sections, [`Нельзя удалить секцию ${code}: есть дочерние секции`]);
    }
    const referenced = rules.filter((rule) => rule.catalogSection === code).map((rule) => rule.code);
    if (referenced.length > 0) {
      return this.fail(sections, [`Секцию ${code} используют правила: ${referenced.join(', ')}`]);
    }
    const next = working.filter((section) => section.code !== code);
    if (next.length === working.length) return this.fail(sections, [`Секция не найдена: ${code}`]);
    const parentCode = working.find((section) => section.code === code)?.parentCode ?? null;

    return this.commit(sections, this.normalizeSortOrder(next, parentCode));
  }

  private childrenByParent(sections: AbilitySection[]): Map<string | null, AbilitySection[]> {
    const childrenByParent = new Map<string | null, AbilitySection[]>();
    for (const section of sections) {
      const children = childrenByParent.get(section.parentCode) ?? [];
      children.push(section);
      childrenByParent.set(section.parentCode, children);
    }

    return childrenByParent;
  }

  private nextSortOrder(sections: AbilitySection[], parentCode: string | null): number {
    const siblings = sections.filter((section) => section.parentCode === parentCode);

    return siblings.reduce((max, section) => Math.max(max, section.sortOrder), 0) + 10;
  }

  private commit(original: AbilitySection[], next: AbilitySection[]): AbilitySectionMutation {
    const errors = this.validate(next);
    if (errors.length > 0) return this.fail(original, errors);

    return { sections: next, errors: [] };
  }

  private fail(original: AbilitySection[], errors: string[]): AbilitySectionMutation {
    return { sections: this.normalize(original), errors };
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
