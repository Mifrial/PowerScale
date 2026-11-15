import type { LoreSectionInput } from '@/modules/Roleplay/Lore/Dto/LoreSectionInput';
import type { LoreSectionDropPlacement } from '@/modules/Roleplay/Lore/Enum/LoreSectionDropPlacement';

/** Локальные правки дерева разделов кодекса до `lore.saveTree`. */
export class LoreSectionTreeService {
  normalize(sections: LoreSectionInput[]): LoreSectionInput[] {
    return sections
      .map((section, index) => ({
        ...section,
        parentCode: section.parentCode ?? null,
        sortOrder: Number.isFinite(section.sortOrder) ? section.sortOrder : index,
      }))
      .sort((left, right) => left.sortOrder - right.sortOrder || left.code.localeCompare(right.code));
  }

  hasChildren(sections: LoreSectionInput[], code: string): boolean {
    return sections.some((section) => section.parentCode === code);
  }

  nextSortOrder(sections: LoreSectionInput[], parentCode: string | null): number {
    const siblings = sections.filter((section) => section.parentCode === parentCode);

    return siblings.reduce((max, section) => Math.max(max, section.sortOrder), 0) + 10;
  }

  add(sections: LoreSectionInput[], section: LoreSectionInput): LoreSectionInput[] {
    if (sections.some((item) => item.code === section.code)) return this.normalize(sections);
    const parentCode = section.parentCode ?? null;
    const next = [
      ...sections,
      {
        ...section,
        parentCode,
        sortOrder: this.nextSortOrder(sections, parentCode),
      },
    ];

    return this.normalizeSiblings(next, parentCode);
  }

  update(sections: LoreSectionInput[], code: string, patch: Partial<LoreSectionInput>): LoreSectionInput[] {
    const target = sections.find((section) => section.code === code);
    if (!target) return this.normalize(sections);
    const oldParent = target.parentCode;
    const nextParent = patch.parentCode !== undefined ? patch.parentCode : oldParent;
    const next = sections.map((section) => (section.code === code ? { ...section, ...patch, code } : section));
    const renumbered = this.normalizeSiblings(next, oldParent);

    return nextParent === oldParent ? renumbered : this.normalizeSiblings(renumbered, nextParent);
  }

  remove(sections: LoreSectionInput[], code: string): LoreSectionInput[] {
    if (this.hasChildren(sections, code)) return this.normalize(sections);
    const parentCode = sections.find((section) => section.code === code)?.parentCode ?? null;
    const next = sections.filter((section) => section.code !== code);

    return this.normalizeSiblings(next, parentCode);
  }

  drop(
    sections: LoreSectionInput[],
    sourceCode: string,
    targetCode: string,
    placement: LoreSectionDropPlacement,
  ): LoreSectionInput[] {
    if (sourceCode === targetCode) return this.normalize(sections);
    const normalized = this.normalize(sections);
    const target = normalized.find((section) => section.code === targetCode);
    if (!target) return normalized;

    const parentCode = placement === 'child' ? targetCode : target.parentCode;
    const siblings = normalized.filter((section) => section.parentCode === parentCode && section.code !== sourceCode);
    const targetIndex = siblings.findIndex((section) => section.code === targetCode);
    const insertIndex = placement === 'child' ? siblings.length : placement === 'after' ? targetIndex + 1 : targetIndex;

    return this.place(normalized, sourceCode, parentCode, Math.max(0, insertIndex));
  }

  private place(
    sections: LoreSectionInput[],
    code: string,
    parentCode: string | null,
    siblingIndex: number,
  ): LoreSectionInput[] {
    if (parentCode === code || this.descendantCodes(sections, code).includes(parentCode ?? '')) {
      return this.normalize(sections);
    }
    const target = sections.find((section) => section.code === code);
    if (!target) return this.normalize(sections);

    const oldParent = target.parentCode;
    target.parentCode = parentCode;
    const siblings = sections
      .filter((section) => section.parentCode === parentCode && section.code !== code)
      .sort((left, right) => left.sortOrder - right.sortOrder || left.code.localeCompare(right.code));
    const index = Math.max(0, Math.min(siblingIndex, siblings.length));
    siblings.splice(index, 0, target);
    siblings.forEach((section, order) => {
      section.sortOrder = (order + 1) * 10;
    });

    return this.normalizeSiblings(this.normalizeSiblings(sections, oldParent), parentCode);
  }

  descendantCodes(sections: LoreSectionInput[], code: string): string[] {
    const descendants = new Set<string>([code]);
    let changed = true;
    while (changed) {
      changed = false;
      for (const section of sections) {
        if (section.parentCode && descendants.has(section.parentCode) && !descendants.has(section.code)) {
          descendants.add(section.code);
          changed = true;
        }
      }
    }

    return [...descendants];
  }

  private normalizeSiblings(sections: LoreSectionInput[], parentCode: string | null): LoreSectionInput[] {
    const siblings = sections
      .filter((section) => section.parentCode === parentCode)
      .sort((left, right) => left.sortOrder - right.sortOrder || left.code.localeCompare(right.code));
    siblings.forEach((section, index) => {
      section.sortOrder = (index + 1) * 10;
    });

    return this.normalize(sections);
  }
}
