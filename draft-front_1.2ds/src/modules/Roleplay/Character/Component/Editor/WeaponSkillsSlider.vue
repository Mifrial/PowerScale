<script setup lang="ts">
import { computed, ref } from 'vue';
import SlidePanel from '@/modules/Core/UI/Component/SlidePanel.vue';
import EditorAbilityNode from '@/modules/Roleplay/Character/Component/Editor/DevelopmentAbilityNode.vue';
import { weaponProficiencyService } from '@/modules/Roleplay/Character/Service/Instance/weaponProficiencyService';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { Keyword } from '@/modules/Roleplay/Rule/Dto/Keyword';
import type { EditorAbilityZone } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityZone';
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { CharacterAbility } from '@/modules/Roleplay/Character/Dto/CharacterAbility';

const props = defineProps<{
  familyCode: string | null;
  keywordCode: string | null;
  rules: Rule[];
  keywords?: Keyword[];
  /** Текущие способности персонажа (для уровня владения семьи и определения уровня навыка). */
  abilities: CharacterAbility[];
}>();

const emit = defineEmits<{
  'set-level': [ruleId: string, level: number];
}>();

const open = defineModel<boolean>('open', { default: false });

/** Уровень владения семьи конкретного оружия (0 — не обучено). */
const familyProficiency = computed(() => {
  if (!props.familyCode) return 0;

  return weaponProficiencyService.weaponProficiencyLevels(props.abilities, props.rules).get(props.familyCode) ?? 0;
});

/** Фильтровать оружейные навыки по keyword_code и группировать по min_level. */
interface SkillGroup {
  label: string;
  abilities: EditorAbility[];
}

/**
 * Преобразовать Rule в EditorAbility формат. Требование уровня N — владение семьи >= N
 * (минимальное владение из min_weapon_mastery): уровень доступен, если masteryLevel >= N.
 */
function toEditorAbility(rule: Rule): EditorAbility {
  const spec = rule.spec as unknown as Record<string, unknown>;
  const zonesObj = (spec?.zones ?? {}) as Record<string, unknown> as Record<string, { levels_cost?: number[] }>;
  const zoneEntries = Object.entries(zonesObj) as [string, { levels_cost?: number[] }][];
  const currentZone = zoneEntries[0];
  const levelCosts = currentZone?.[1]?.levels_cost ?? [];

  const abilityZones: EditorAbilityZone[] = zoneEntries.map(([zoneCode, zoneData]) => ({
    zoneCode,
    kind: 'array' as const,
    maxLevel: zoneData.levels_cost?.length || 1,
    levelCosts: zoneData.levels_cost || [],
  }));

  // Минимальное владение, требуемое навыком (min_weapon_mastery).
  const requiredLevel = skillMinLevel(rule) ?? 0;
  const currentLevel = props.abilities.find((a) => a.ruleId === rule.id)?.level ?? 0;

  return {
    ruleId: rule.id,
    code: rule.code,
    name: rule.name,
    type: (rule.type === 'ability' ? (spec?.type as string) || null : null) as AbilityType | null,
    description: rule.description,
    processSteps: [],
    keywordIds: rule.keywordIds ?? [],
    zones: abilityZones,
    level: currentLevel,
    levels: levelCosts.map((cost, i) => {
      const level = i + 1;
      // Доступен, если уровень владения семьи достаточен (требование min_weapon_mastery).
      const met = requiredLevel > 0 ? familyProficiency.value >= requiredLevel : true;

      return {
        level,
        cost,
        met,
        reason: met ? null : `требуется владение оружием уровня ${requiredLevel}`,
      };
    }),
    automatic: false,
    gifted: false,
    giftedLevel: 0,
    derived: false,
    racial: false,
    visible: true,
    characteristic: false,
    characteristicCode: null,
    groupCode: null,
    parentCode: (spec?.parent_ability_code as string) ?? null,
    parameters: [],
    multiple: false,
    domainRef: null,
    instances: [],
    domain: null,
    domainCode: null,
    domainOptions: [],
  };
}

/** Минимальное владение оружием (min_weapon_mastery.min_level) из требований правила. */
function skillMinLevel(rule: Rule): number | null {
  const spec = rule.spec as { requirements?: unknown[] };
  if (!Array.isArray(spec?.requirements)) return null;
  for (const req of spec.requirements) {
    const inner = (req as { requirements?: unknown[] } | undefined)?.requirements;
    if (!Array.isArray(inner)) continue;
    for (const r of inner) {
      if ((r as { type?: string } | undefined)?.type === 'min_weapon_mastery') {
        return (r as { min_level?: number } | undefined)?.min_level ?? null;
      }
    }
  }

  return null;
}

const groupedSkills = computed<SkillGroup[]>(() => {
  if (!props.keywordCode) return [];

  const result: Record<number, EditorAbility[]> = {};
  for (const rule of props.rules) {
    if (rule.type !== 'ability') continue;
    const spec = rule.spec as { requirements?: unknown[] };
    if (!Array.isArray(spec?.requirements)) continue;
    for (const req of spec.requirements) {
      const inner = (req as { requirements?: unknown[] } | undefined)?.requirements;
      if (!Array.isArray(inner)) continue;
      for (const r of inner) {
        const type = (r as { type?: string } | undefined)?.type;
        if (type === 'min_weapon_mastery') {
          const kw = (r as { keyword_code?: string } | undefined)?.keyword_code ?? '';
          if (kw !== props.keywordCode) continue;
          const level = (r as { min_level?: number } | undefined)?.min_level ?? 0;
          if (!result[level]) result[level] = [];
          result[level].push(toEditorAbility(rule));
        }
      }
    }
  }

  return Object.entries(result)
    .sort(([a], [b]) => Number(a) - Number(b))
    .map(([level, skills]) => ({ label: `Мин. владение: ${level}`, abilities: skills }));
});

/** Все ruleId раскрыты по умолчанию. */
const openSet = ref(new Set<string>());

/** childrenByCode для развития навыков-улучшений. */
const childrenByCode = computed(() => {
  const map = new Map<string, EditorAbility[]>();
  for (const group of groupedSkills.value) {
    for (const ability of group.abilities) {
      if (!ability.parentCode) continue;
      const list = map.get(ability.parentCode) ?? [];
      list.push(ability);
      map.set(ability.parentCode, list);
    }
  }

  return map;
});

/** Верхний уровень дерева. */
const rootAbilities = computed(() => {
  const all = groupedSkills.value.flatMap((g) => g.abilities);

  return all.filter((ability) => !ability.parentCode || !childrenByCode.value.has(ability.parentCode));
});
</script>

<template>
  <SlidePanel v-model="open" width="720px">
    <div v-if="groupedSkills.length === 0" class="pa-4 text-body-2 text-medium-emphasis">
      Нет оружейных навыков для этого оружия.
    </div>

    <div v-for="group in groupedSkills" :key="group.label" class="skill-group pa-4">
      <div class="text-subtitle-2 font-weight-bold mb-2">{{ group.label }}</div>
      <EditorAbilityNode
        v-for="ability in rootAbilities.filter((a) => group.abilities.includes(a))"
        :key="ability.ruleId"
        :ability="ability"
        :children-by-code="childrenByCode"
        :keywords="keywords ?? []"
        :rules="rules"
        :open-set="openSet"
        @update:open="
          (ruleId, o) => {
            if (o) openSet.add(ruleId);
            else openSet.delete(ruleId);
          }
        "
        @set-level="(ruleId, level) => emit('set-level', ruleId, level)"
        @add-instance="() => {}"
        @set-instance-level="() => {}"
        @set-instance-domain="() => {}"
        @remove-instance="() => {}"
        @set-ability-domain="() => {}"
      />
    </div>
  </SlidePanel>
</template>

<style scoped>
.skill-group + .skill-group {
  margin-top: 8px;
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
