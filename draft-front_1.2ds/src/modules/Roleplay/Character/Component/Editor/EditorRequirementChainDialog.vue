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
  };
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
        <div v-if="nodes.length === 0" class="text-body-2 text-medium-emphasis">Все требования выполнены.</div>
        <div v-for="node in nodes" :key="node.key" class="chain-row" :style="{ paddingLeft: `${node.depth * 20}px` }">
          <div class="chain-row__text">
            <div class="text-body-2">{{ node.name }}</div>
            <div class="text-caption text-medium-emphasis">
              уровень {{ node.ownedLevel }} из {{ node.requiredLevel }}
              <template v-if="node.multiple"> · изучается на пути в каталоге</template>
            </div>
          </div>
          <v-btn v-if="node.buyLevel !== null" size="small" variant="tonal" color="primary" @click="buy(node)">
            Купить ур. {{ node.buyLevel }}<template v-if="node.cost !== null"> · {{ node.cost }}</template>
          </v-btn>
          <v-chip v-else-if="node.multiple" size="small" variant="tonal">нужен экземпляр</v-chip>
          <v-chip v-else size="small" variant="tonal" color="warning">сначала предпосылки</v-chip>
        </div>
      </v-card-text>
    </v-card>
  </v-dialog>
</template>

<style scoped>
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
