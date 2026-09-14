import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';
import type { CharacterKnowledgeSlot } from '@/modules/Roleplay/Character/Dto/CharacterKnowledgeSlot';
import { DOMAIN_STATIC_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/DOMAIN_STATIC_OPTIONS';
import { KNOWLEDGE_ABILITY_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/KNOWLEDGE_ABILITY_CODE';
import { MEDICAL_PRACTICE_COLLAPSE_CODES } from '@/modules/Roleplay/Rule/Constant/Ability/MEDICAL_PRACTICE_COLLAPSE_CODES';
import { PHYSIOLOGY_SEED_PRACTICE_CODES } from '@/modules/Roleplay/Rule/Constant/Ability/PHYSIOLOGY_SEED_PRACTICE_CODES';
import { KNOWLEDGE_FIELDS } from '@/modules/Roleplay/Rule/Constant/Knowledge/KNOWLEDGE_FIELDS';
import { KNOWLEDGE_SLOT_INPUT_LABELS } from '@/modules/Roleplay/Rule/Constant/Knowledge/KNOWLEDGE_SLOT_INPUT_LABELS';
import { KNOWLEDGE_TEMPLATE_FIELD_BY_CODE } from '@/modules/Roleplay/Rule/Constant/Knowledge/KNOWLEDGE_TEMPLATE_FIELD_BY_CODE';
import type { KnowledgeField } from '@/modules/Roleplay/Rule/Dto/Knowledge/KnowledgeField';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';

/**
 * Экземпляры «Знание о…»: подпись, тождество слотов, разовая миграция старых кодов.
 */
export class KnowledgeInstanceService {
  fieldOf(fieldCode: string | null | undefined): KnowledgeField | null {
    if (!fieldCode) return null;

    return KNOWLEDGE_FIELDS.find((field) => field.code === fieldCode) ?? null;
  }

  isKnowledgeRule(ruleCode: string): boolean {
    return ruleCode === KNOWLEDGE_ABILITY_CODE;
  }

  isKnowledgeTemplate(ruleCode: string): boolean {
    return ruleCode in KNOWLEDGE_TEMPLATE_FIELD_BY_CODE;
  }

  fieldSelectItems(): { title: string; value: string }[] {
    return KNOWLEDGE_FIELDS.map((field) => ({ title: field.name, value: field.code }));
  }

  primarySlotKey(fieldCode: string | null | undefined): string {
    return this.fieldOf(fieldCode)?.slots[0]?.key ?? 'topic';
  }

  slotInputLabel(fieldCode: string | null | undefined): string {
    const key = this.primarySlotKey(fieldCode);

    return KNOWLEDGE_SLOT_INPUT_LABELS[key] ?? 'Тема';
  }

  slotOptions(fieldCode: string | null | undefined, rules: readonly Rule[]): { code: string; name: string }[] {
    const domainRef = this.fieldOf(fieldCode)?.slots[0]?.domain_ref;
    if (!domainRef) return [];
    const staticOptions = DOMAIN_STATIC_OPTIONS[domainRef];
    if (staticOptions) return staticOptions;
    if (domainRef === 'species') {
      return rules.filter((rule) => rule.type === 'species').map((rule) => ({ code: rule.code, name: rule.name }));
    }

    return [];
  }

  composeSlots(
    fieldCode: string | null | undefined,
    text: string,
    rules: readonly Rule[],
  ): Record<string, CharacterKnowledgeSlot> | null {
    const trimmed = text.trim();
    const field = this.fieldOf(fieldCode);
    if (!field || !trimmed) return null;
    const key = this.primarySlotKey(fieldCode);
    const code = this.slotOptions(fieldCode, rules).find((option) => option.name === trimmed)?.code ?? null;

    return { [key]: { code, text: trimmed } };
  }

  label(fieldCode: string | null | undefined, slots: Record<string, CharacterKnowledgeSlot> | undefined): string {
    const field = this.fieldOf(fieldCode);
    const slotText = Object.values(slots ?? {})
      .map((slot) => slot.text.trim())
      .find((text) => text.length > 0);
    const topic = slotText ?? '';
    if (!field) return topic || 'Знание';

    return topic ? `Знание о ${field.name.toLowerCase()} · ${topic}` : `Знание о ${field.name.toLowerCase()}`;
  }

  sameSlots(
    left: Record<string, CharacterKnowledgeSlot> | undefined,
    right: Record<string, CharacterKnowledgeSlot> | undefined,
  ): boolean {
    const leftKeys = Object.keys(left ?? {});
    const rightKeys = Object.keys(right ?? {});
    if (leftKeys.length !== rightKeys.length) return false;

    return leftKeys.every((key) => this.sameSlot(left?.[key], right?.[key]));
  }

  sameInstance(left: CharacterAbility, right: CharacterAbility): boolean {
    if (left.ruleCode !== right.ruleCode) return false;
    if ((left.fieldCode ?? null) !== (right.fieldCode ?? null)) return false;

    return this.sameSlots(left.slots, right.slots);
  }

  hasDuplicate(abilities: readonly CharacterAbility[], candidate: CharacterAbility): boolean {
    return abilities.some((ability) => this.sameInstance(ability, candidate));
  }

  hasFieldAtLeast(abilities: readonly CharacterAbility[], fieldCode: string, minLevel = 1): boolean {
    return abilities.some(
      (ability) =>
        this.isKnowledgeRule(ability.ruleCode) && ability.fieldCode === fieldCode && ability.level >= minLevel,
    );
  }

  hasFieldDuplicate(
    instances: readonly { fieldCode?: string | null; slots?: Record<string, CharacterKnowledgeSlot> }[],
    fieldCode: string,
    slots: Record<string, CharacterKnowledgeSlot>,
  ): boolean {
    return instances.some(
      (instance) => (instance.fieldCode ?? null) === fieldCode && this.sameSlots(instance.slots, slots),
    );
  }

  slotsFilled(fieldCode: string, slots: Record<string, CharacterKnowledgeSlot> | undefined): boolean {
    const field = this.fieldOf(fieldCode);
    if (!field) return false;

    return field.slots.every((slot) => {
      if (!slot.required) return true;
      const value = slots?.[slot.key];

      return Boolean(value && (value.code || value.text.trim()));
    });
  }

  remapAbilities(abilities: readonly CharacterAbility[]): CharacterAbility[] {
    const withoutDeleted = abilities.filter((ability) => ability.ruleCode !== 'predpisaniya-o-lechenii');
    const collapsed = this.collapsePractice(withoutDeleted);
    const asKnowledge = this.rewriteKnowledge(collapsed);

    return this.seedPhysiology(asKnowledge, withoutDeleted);
  }

  private sameSlot(left: CharacterKnowledgeSlot | undefined, right: CharacterKnowledgeSlot | undefined): boolean {
    const leftCode = left?.code ?? null;
    const rightCode = right?.code ?? null;
    if (leftCode || rightCode) return leftCode === rightCode;

    return (left?.text ?? '').trim() === (right?.text ?? '').trim();
  }

  private collapsePractice(abilities: readonly CharacterAbility[]): CharacterAbility[] {
    const kept: CharacterAbility[] = [];
    const maxByCode = new Map<string, CharacterAbility>();
    for (const ability of abilities) {
      if (!MEDICAL_PRACTICE_COLLAPSE_CODES.includes(ability.ruleCode)) {
        kept.push(ability);
        continue;
      }
      const current = maxByCode.get(ability.ruleCode);
      if (!current || ability.level > current.level) {
        maxByCode.set(ability.ruleCode, {
          ruleCode: ability.ruleCode,
          level: ability.level,
          zone: ability.zone,
          gifted: ability.gifted,
        });
      }
    }

    return [...kept, ...maxByCode.values()];
  }

  private rewriteKnowledge(abilities: readonly CharacterAbility[]): CharacterAbility[] {
    const result: CharacterAbility[] = [];
    for (const ability of abilities) {
      const fieldCode = KNOWLEDGE_TEMPLATE_FIELD_BY_CODE[ability.ruleCode];
      if (!fieldCode) {
        result.push(ability);
        continue;
      }
      const field = this.fieldOf(fieldCode);
      const slotKey = field?.slots[0]?.key ?? 'topic';
      const rewritten: CharacterAbility = {
        ruleCode: KNOWLEDGE_ABILITY_CODE,
        level: Math.min(ability.level, 3),
        zone: ability.zone ?? 'or',
        fieldCode,
        slots: {
          [slotKey]: { code: ability.domainCode ?? null, text: ability.domain ?? '' },
        },
        domain: this.label(fieldCode, {
          [slotKey]: { code: ability.domainCode ?? null, text: ability.domain ?? '' },
        }),
        domainCode: ability.domainCode ?? null,
        gifted: ability.gifted,
      };
      if (this.hasDuplicate(result, rewritten)) {
        const index = result.findIndex((entry) => this.sameInstance(entry, rewritten));
        if (index >= 0 && result[index].level < rewritten.level) result[index] = rewritten;
        continue;
      }
      result.push(rewritten);
    }

    return result;
  }

  private seedPhysiology(abilities: CharacterAbility[], original: readonly CharacterAbility[]): CharacterAbility[] {
    const seeded = [...abilities];
    for (const ability of original) {
      if (!PHYSIOLOGY_SEED_PRACTICE_CODES.includes(ability.ruleCode)) continue;
      const text = (ability.domain ?? '').trim();
      const code = ability.domainCode ?? null;
      if (!code && !text) continue;
      const slots = { species: { code, text: text || code || '' } };
      const candidate: CharacterAbility = {
        ruleCode: KNOWLEDGE_ABILITY_CODE,
        level: 1,
        zone: 'or',
        fieldCode: 'physiology',
        slots,
        domain: this.label('physiology', slots),
        domainCode: code,
      };
      if (!this.hasDuplicate(seeded, candidate)) seeded.push(candidate);
    }

    return seeded;
  }
}
