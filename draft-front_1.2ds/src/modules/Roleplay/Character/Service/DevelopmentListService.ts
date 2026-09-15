import type { DevelopmentAbilityRow } from '@/modules/Roleplay/Character/Dto/Editor/DevelopmentAbilityRow';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { EditorAbilityInstance } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityInstance';

/**
 * Разворачивает любой multiple-навык в строку каталога и отдельные строки экземпляров,
 * чтобы улучшения висели на конкретном языке, знании или пути, как модификаторы на предмете.
 */
export class DevelopmentListService {
  expand(abilities: EditorAbility[]): DevelopmentAbilityRow[] {
    const rows: DevelopmentAbilityRow[] = [];
    for (const ability of abilities) {
      if (ability.multiple) {
        rows.push(this.catalogRow(ability));
        ability.instances.forEach((instance, index) => {
          rows.push(this.instanceRow(ability, instance, index));
        });
        continue;
      }
      rows.push({
        key: ability.ruleCode,
        ability,
        spellRowKind: null,
        instance: null,
      });
    }

    return rows;
  }

  childrenByParentKey(rows: DevelopmentAbilityRow[]): Map<string, DevelopmentAbilityRow[]> {
    const byParent = new Map<string, DevelopmentAbilityRow[]>();
    for (const row of rows) {
      const parent = this.parentRowOf(row, rows);
      if (!parent) continue;
      const list = byParent.get(parent.key) ?? [];
      list.push(row);
      byParent.set(parent.key, list);
    }

    return byParent;
  }

  roots(
    rows: DevelopmentAbilityRow[],
    childrenByParentKey: Map<string, DevelopmentAbilityRow[]>,
  ): DevelopmentAbilityRow[] {
    const nested = new Set([...childrenByParentKey.values()].flatMap((list) => list.map((row) => row.key)));

    return rows.filter((row) => !nested.has(row.key));
  }

  private catalogRow(ability: EditorAbility): DevelopmentAbilityRow {
    return {
      key: `${ability.ruleCode}:catalog`,
      ability: { ...ability, level: 0, gifted: false },
      spellRowKind: 'catalog',
      instance: null,
    };
  }

  private instanceRow(ability: EditorAbility, instance: EditorAbilityInstance, index: number): DevelopmentAbilityRow {
    return {
      key: `${ability.ruleCode}:instance:${index}:${instance.domainCode ?? ''}:${instance.domain}`,
      ability: {
        ...ability,
        level: instance.level,
        instances: [instance],
        domain: instance.domain,
        domainCode: instance.domainCode,
        gifted: instance.gifted === true,
      },
      spellRowKind: 'instance',
      instance,
    };
  }

  private parentRowOf(row: DevelopmentAbilityRow, rows: DevelopmentAbilityRow[]): DevelopmentAbilityRow | null {
    const parentCode = row.ability.parentCode;
    if (!parentCode) return null;
    const parents = rows.filter((candidate) => candidate.ability.code === parentCode);
    if (parents.length === 0) return null;
    if (row.spellRowKind === 'instance') {
      return (
        parents.find(
          (candidate) =>
            candidate.spellRowKind === 'instance' &&
            this.instanceKey(candidate.instance) === this.instanceKey(row.instance),
        ) ?? null
      );
    }
    if (row.spellRowKind === 'catalog') {
      return parents.find((candidate) => candidate.spellRowKind === 'catalog') ?? parents[0] ?? null;
    }

    return parents.find((candidate) => candidate.spellRowKind !== 'instance') ?? parents[0] ?? null;
  }

  private instanceKey(instance: EditorAbilityInstance | null): string {
    if (!instance) return ':';

    return `${instance.domainCode ?? ''}:${instance.domain ?? ''}`;
  }
}
