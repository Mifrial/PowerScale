<script setup lang="ts">
import { computed } from 'vue';
import type { EditorAbility } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbility';
import type { EditorAbilityZone } from '@/modules/Roleplay/Character/Dto/Editor/EditorAbilityZone';

/** Узел цепочки требований: что купить и что для этого нужно. */
interface RequirementNode {
  key: string;
  code: string;
  name: string;
  requiredLevel: number;
  ownedLevel: number;
  cost: number | null;
  buyLevel: number | null;
  multiple: boolean;
  depth: number;
  /** Требование уже выполнено: покупать нечего. */
  satisfied: boolean;
  /** Почему уровень нельзя купить прямо сейчас (пусто — можно). */
  blockedReasons: string[];
}

const props = defineProps<{
  modelValue: boolean;
  ability: EditorAbility | null;
  abilities: EditorAbility[];
  zoneCode?: string;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  buy: [ruleCode: string, level: number];
}>();

const open = computed({
  get: () => props.modelValue,
  set: (value: boolean) => emit('update:modelValue', value),
});

const targetRequirement = computed(() => props.ability?.levels.find((level) => !level.met) ?? null);

/** Все невыполненные требования цели: способности, опыт пути, признаки, характеристики. */
const targetBlockers = computed<string[]>(() => {
  const level = targetRequirement.value;
  if (level === null) return [];
  if (level.unmetReasons !== undefined && level.unmetReasons.length > 0) return level.unmetReasons;

  return splitReason(level.reason);
});

const nodes = computed<RequirementNode[]>(() => {
  const target = props.ability;
  const unmet = targetRequirement.value?.unmetAbilities ?? [];
  if (target === null || unmet.length === 0) return [];

  const rows: RequirementNode[] = [];
  const seen = new Set<string>();
  const visit = (code: string, requiredLevel: number, depth: number): void => {
    const key = `${code}:${requiredLevel}`;
    if (seen.has(key) || depth > 6) return;
    seen.add(key);
    const ability = props.abilities.find((entry) => entry.ruleCode === code);
    if (!ability) return;
    const node = buildNode(ability, requiredLevel, depth);
    if (node === null) return;
    rows.push(node);
    if (node.ownedLevel >= requiredLevel) return;
    const levelInfo = ability.levels[Math.max(0, requiredLevel - 1)];
    for (const child of levelInfo?.unmetAbilities ?? []) {
      visit(child.abilityCode, child.minLevel, depth + 1);
    }
  };
  for (const requirement of unmet) visit(requirement.abilityCode, requirement.minLevel, 0);

  return rows;
});

function splitReason(reason: string | null | undefined): string[] {
  if (!reason) return [];

  return reason
    .split('; ')
    .map((entry) => entry.trim())
    .filter((entry) => entry !== '');
}

function buildNode(ability: EditorAbility, requiredLevel: number, depth: number): RequirementNode | null {
  const zone = zoneOf(ability);
  const owned = ability.level;
  const next = owned + 1;
  const canBuy =
    !ability.multiple &&
    !ability.automatic &&
    !ability.derived &&
    !ability.gifted &&
    zone !== null &&
    owned < requiredLevel &&
    next <= zone.maxLevel &&
    ability.levels[next - 1]?.met === true;

  return {
    key: `${ability.ruleCode}:${requiredLevel}`,
    code: ability.ruleCode,
    name: ability.name,
    requiredLevel,
    ownedLevel: owned,
    cost: canBuy && zone !== null ? (zone.levelCosts[owned] ?? null) : null,
    buyLevel: canBuy ? next : null,
    multiple: ability.multiple,
    depth,
    satisfied: owned >= requiredLevel,
    blockedReasons: canBuy || owned >= requiredLevel ? [] : blockedReasonsOf(ability, requiredLevel, next, zone),
  };
}

function blockedReasonsOf(
  ability: EditorAbility,
  requiredLevel: number,
  next: number,
  zone: EditorAbilityZone | null,
): string[] {
  if (ability.level >= requiredLevel) return [];
  if (ability.multiple) return ['множественный навык: изучите экземпляр в каталоге (выберите путь или домен)'];
  if (ability.automatic) return ['выдаётся автоматически'];
  if (ability.gifted) return ['получено даром от особенности и не покупается'];
  if (ability.derived) return ['уровень вычисляется автоматически'];
  if (zone === null) return ['нет зоны покупки за ОР'];
  if (next > zone.maxLevel) return [`достигнут максимальный уровень (${zone.maxLevel})`];

  const reasons = splitReason(ability.levels[next - 1]?.reason);
  if (reasons.length > 0) return reasons;
  const unmet = ability.levels[next - 1]?.unmetAbilities ?? [];
  if (unmet.length > 0) return unmet.map((entry) => `сначала ${entry.label}`);

  return ['требования уровня не выполнены'];
}

function zoneOf(ability: EditorAbility): EditorAbilityZone | null {
  return (
    ability.zones.find((zone) => zone.zoneCode === (props.zoneCode ?? 'or')) ??
    ability.zones.find((zone) => zone.levelCosts.length > 0) ??
    null
  );
}

function buy(node: RequirementNode): void {
  if (node.buyLevel === null) return;
  emit('buy', node.code, node.buyLevel);
}
</script>

<template>
  <v-dialog v-model="open" max-width="620">
    <v-card>
      <v-card-title class="d-flex align-center ga-2">
        <span>Цепочка требований</span>
        <v-spacer />
        <v-btn icon="mdi-close" variant="text" density="compact" @click="open = false" />
      </v-card-title>
      <v-card-subtitle v-if="ability">
        {{ ability.name }} · требования уровня {{ targetRequirement?.level ?? 1 }}
      </v-card-subtitle>
      <v-card-text>
        <div v-if="targetBlockers.length > 0" class="chain-blockers">
          <div class="text-caption text-medium-emphasis mb-1">Что мешает:</div>
          <v-chip
            v-for="reason in targetBlockers"
            :key="reason"
            size="small"
            variant="tonal"
            color="warning"
            class="mr-1 mb-1"
          >
            {{ reason }}
          </v-chip>
        </div>
        <div v-if="nodes.length === 0 && targetBlockers.length === 0" class="text-body-2 text-medium-emphasis">
          Все требования выполнены.
        </div>
        <div v-for="node in nodes" :key="node.key" class="chain-row" :style="{ paddingLeft: `${node.depth * 20}px` }">
          <div class="chain-row__text">
            <div class="text-body-2">{{ node.name }}</div>
            <div class="text-caption text-medium-emphasis">
              уровень {{ node.ownedLevel }} из {{ node.requiredLevel }}
              <template v-if="node.multiple"> · изучается на пути в каталоге</template>
            </div>
            <div v-if="node.blockedReasons.length > 0" class="text-caption text-warning">
              {{ node.blockedReasons.join('; ') }}
            </div>
          </div>
          <v-btn v-if="node.buyLevel !== null" size="small" variant="tonal" color="primary" @click="buy(node)">
            Купить ур. {{ node.buyLevel }}<template v-if="node.cost !== null"> · {{ node.cost }}</template>
          </v-btn>
          <v-chip v-else-if="node.satisfied" size="small" variant="tonal" color="success">выполнено</v-chip>
          <v-tooltip v-else-if="node.blockedReasons.length > 0" location="top" max-width="420">
            <template #activator="{ props: tipProps }">
              <v-chip v-bind="tipProps" size="small" variant="tonal" color="warning">нельзя купить</v-chip>
            </template>
            <div v-for="reason in node.blockedReasons" :key="reason">{{ reason }}</div>
          </v-tooltip>
          <v-chip v-else size="small" variant="tonal" color="warning">требования не выполнены</v-chip>
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
.chain-blockers {
  padding-bottom: 8px;
  margin-bottom: 4px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.chain-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding-top: 6px;
  padding-bottom: 6px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.chain-row:last-child {
  border-bottom: none;
}

.chain-row__text {
  flex: 1 1 auto;
  min-width: 0;
}
</style>
