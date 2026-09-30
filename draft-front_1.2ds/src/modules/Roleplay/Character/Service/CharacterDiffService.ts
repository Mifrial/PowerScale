import type { CharacterDiff } from '@/modules/Roleplay/Character/Dto/CharacterDiff';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import { CHARACTER_DIFF_SCALAR_FIELDS } from '@/modules/Roleplay/Character/Constant/CHARACTER_DIFF_SCALAR_FIELDS';

/** Строит единый semantic diff персонажа для moderation и session predicates. */
export class CharacterDiffService {
  getCharacterDiff(approved: CharacterVersion | null, actual: CharacterVersion | null): CharacterDiff {
    const availability =
      approved === null && actual === null
        ? 'missingBoth'
        : approved === null
          ? 'firstSubmission'
          : actual === null
            ? 'missingActual'
            : 'complete';
    const changes = actual === null ? [] : this.diffVersion(approved, actual);

    return {
      availability,
      hasChanges: availability === 'firstSubmission' || changes.length > 0,
      identity: {
        approved: this.identityOf(approved),
        actual: this.identityOf(actual),
        compatible:
          approved !== null &&
          actual !== null &&
          approved.spaceCode === actual.spaceCode &&
          approved.rulesRevision === actual.rulesRevision,
      },
      changes,
    };
  }

  private identityOf(version: CharacterVersion | null): {
    spaceCode: string | null;
    rulesRevision: number | null;
  } {
    return {
      spaceCode: version?.spaceCode ?? null,
      rulesRevision: version?.rulesRevision ?? null,
    };
  }

  private diffVersion(approved: CharacterVersion | null, actual: CharacterVersion): CharacterDiff['changes'] {
    const changes: CharacterDiff['changes'] = [];

    for (const field of CHARACTER_DIFF_SCALAR_FIELDS) {
      const before = this.normalizeSemanticValue(approved?.[field] ?? null);
      const after = this.normalizeSemanticValue(actual[field] ?? null);
      if (approved === null || this.serializeSemanticValue(before) !== this.serializeSemanticValue(after)) {
        changes.push({
          path: field,
          section: 'scalars',
          key: field,
          kind: approved === null ? 'added' : 'changed',
          before,
          after,
        });
      }
    }

    changes.push(
      ...this.diffCollection('characteristics', approved?.characteristics ?? [], actual.characteristics, (value) =>
        this.stringField(value, 'ruleCode'),
      ),
      ...this.diffCollection('resources', approved?.resources ?? [], actual.resources, (value) =>
        this.stringField(value, 'ruleCode'),
      ),
      ...this.diffCollection('abilities', approved?.abilities ?? [], actual.abilities, (value) => {
        const ruleCode = this.stringField(value, 'ruleCode');
        const domain = this.stringField(value, 'domain');

        return domain === '' ? ruleCode : `${ruleCode}|${domain}`;
      }),
      ...this.diffCollection(
        'inventory',
        approved?.inventory ?? [],
        actual.inventory,
        (value) => `${String(this.field(value, 'ruleCode'))}|${String(this.field(value, 'id') ?? '')}`,
      ),
      ...this.diffCollection('states', approved?.states ?? [], actual.states, (value) =>
        this.stringField(value, 'stateRuleCode'),
      ),
      ...this.diffCollection('senses', approved?.senses ?? [], actual.senses, (value) =>
        this.stringField(value, 'ruleCode'),
      ),
      ...this.diffCollection('customRules', approved?.customRules ?? [], actual.customRules ?? [], (value) =>
        String(this.field(value, 'id') ?? ''),
      ),
    );

    return changes;
  }

  private diffCollection(
    section: CharacterDiff['changes'][number]['section'],
    approvedValues: unknown[],
    actualValues: unknown[],
    keyOf: (value: unknown) => string,
  ): CharacterDiff['changes'] {
    const approvedEntries = this.collectionEntries(approvedValues, keyOf);
    const actualEntries = this.collectionEntries(actualValues, keyOf);
    const approvedByKey = new Map(approvedEntries.map((entry) => [entry.key, entry.value]));
    const actualByKey = new Map(actualEntries.map((entry) => [entry.key, entry.value]));
    const keys = [...new Set([...approvedByKey.keys(), ...actualByKey.keys()])].sort((left, right) =>
      left.localeCompare(right),
    );

    return keys.reduce<CharacterDiff['changes']>((changes, key) => {
      const before = approvedByKey.get(key);
      const after = actualByKey.get(key);
      if (before === undefined && after !== undefined) {
        changes.push({
          path: `${section}.${key}`,
          section,
          key,
          kind: 'added',
          before: null,
          after,
        });

        return changes;
      }
      if (before !== undefined && after === undefined) {
        changes.push({
          path: `${section}.${key}`,
          section,
          key,
          kind: 'removed',
          before,
          after: null,
        });

        return changes;
      }
      if (
        before !== undefined &&
        after !== undefined &&
        this.serializeSemanticValue(before) !== this.serializeSemanticValue(after)
      ) {
        changes.push({
          path: `${section}.${key}`,
          section,
          key,
          kind: 'changed',
          before,
          after,
        });
      }

      return changes;
    }, []);
  }

  private collectionEntries(values: unknown[], keyOf: (value: unknown) => string): { key: string; value: unknown }[] {
    const groupedValues = new Map<string, unknown[]>();
    for (const value of values) {
      const key = keyOf(value);
      const group = groupedValues.get(key) ?? [];
      group.push(this.normalizeSemanticValue(value));
      groupedValues.set(key, group);
    }

    return [...groupedValues.entries()]
      .sort(([left], [right]) => left.localeCompare(right))
      .flatMap(([key, group]) =>
        group
          .sort((left, right) => this.serializeSemanticValue(left).localeCompare(this.serializeSemanticValue(right)))
          .map((value, index) => ({ key: `${key}#${index + 1}`, value })),
      );
  }

  private normalizeSemanticValue(value: unknown, omitHeldBy = false): unknown {
    if (value === undefined) return null;
    if (value === null || typeof value !== 'object') return value;
    if (Array.isArray(value)) {
      return value
        .map((entry) => this.normalizeSemanticValue(entry))
        .sort((left, right) => this.serializeSemanticValue(left).localeCompare(this.serializeSemanticValue(right)));
    }

    const record = value as Record<string, unknown>;

    return Object.fromEntries(
      Object.keys(record)
        .filter((key) => !(omitHeldBy && key === 'heldBy'))
        .sort((left, right) => left.localeCompare(right))
        .map((key) => [key, this.normalizeSemanticValue(record[key], key === 'wound')]),
    );
  }

  private serializeSemanticValue(value: unknown): string {
    return JSON.stringify(value) ?? 'null';
  }

  private field(value: unknown, fieldName: string): unknown {
    if (value === null || typeof value !== 'object') return null;

    return (value as Record<string, unknown>)[fieldName] ?? null;
  }

  private stringField(value: unknown, fieldName: string): string {
    const fieldValue = this.field(value, fieldName);

    return typeof fieldValue === 'string' ? fieldValue : '';
  }
}
