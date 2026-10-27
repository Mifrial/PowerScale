<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import type { CharacteristicLimit } from '@/modules/Roleplay/Rule/Dto/Item/CharacteristicLimit';
import { ITEM_CATEGORIES } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_CATEGORIES';
import { DEFENSE_SOURCE_LABELS } from '@/modules/Roleplay/Rule/Constant/Item/DEFENSE_SOURCE_LABELS';
import { ruleViewLabelService } from '@/modules/Roleplay/Rule/Service/Instance/ruleViewLabelService';
import { itemSpecService } from '@/modules/Roleplay/Rule/Service/Instance/itemSpecService';
import ItemStat from '@/modules/Roleplay/Rule/Component/Cards/Item/ItemStat.vue';
import WeaponProfileCard from '@/modules/Roleplay/Rule/Component/Cards/Item/WeaponProfileCard.vue';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const emit = defineEmits<{
  'open-rule': [code: string];
}>();

const spec = computed<ItemSpec | null>(() => (props.rule.spec as ItemSpec | undefined) ?? null);

const categoryLabel = computed(() => {
  const value = spec.value?.category;
  if (!value) return null;

  return ITEM_CATEGORIES.find((entry) => entry.value === value)?.label ?? value;
});

const specialRules = computed(() => {
  const codes = spec.value?.special_rule_codes ?? [];

  return codes.map((code) => ruleViewLabelService.ruleName(props.rules, code));
});

const familyCode = computed(() => spec.value?.proficiency_family_code ?? null);
const familyName = computed(() => {
  const code = familyCode.value;
  if (!code) return null;

  return ruleViewLabelService.ruleName(props.rules, code);
});

const groupName = computed(() => {
  const code = spec.value?.group_code;
  if (!code) return null;

  return ruleViewLabelService.ruleName(props.rules, code);
});

const advantages = computed(() => spec.value?.advantages ?? []);
const checkAdvantages = computed(() => spec.value?.check_advantages ?? []);

const weapon = computed(() => spec.value?.weapon);
const armor = computed(() => spec.value?.armor);
const shield = computed(() => spec.value?.shield);
const block = computed(() => spec.value?.block_profile ?? null);

const weaponProfiles = computed(() => {
  const weaponProfiles = weapon.value?.weapon_profiles ?? [];
  if (weaponProfiles.length) return weaponProfiles;

  return shield.value?.weapon_profiles ?? [];
});
const profilesTitle = computed(() => {
  if ((weapon.value?.weapon_profiles?.length ?? 0) > 0) return 'Профили оружия';
  if ((shield.value?.weapon_profiles?.length ?? 0) > 0) return 'Профили щита';

  return 'Профили';
});

const armorLimits = computed(() => armor.value?.characteristic_limits ?? []);
const armorDefenseSlots = computed(() => armor.value?.defense_slots ?? []);
const armorResistanceSlots = computed(() => armor.value?.resistance_slots ?? []);
const shieldLimits = computed(() => shield.value?.characteristic_limits ?? []);

const weightLabel = computed(() => itemSpecService.formatWeightKilograms(spec.value?.weight));
const durabilityLabel = computed(() => {
  const value = weapon.value?.durability ?? shield.value?.durability;

  return value ? ruleViewLabelService.dimensional(value) : null;
});
const minStrengthLabel = computed(() => {
  const value = weapon.value?.min_strength ?? shield.value?.min_strength;

  return value ? ruleViewLabelService.dimensional(value) : null;
});
const minActionCost = computed(() => weapon.value?.min_action_cost ?? null);
const occupyHandsLabel = computed(() => itemSpecService.formatOccupyHands(spec.value?.occupy_hands));
const hasUsage = computed(
  () =>
    familyName.value != null ||
    minStrengthLabel.value != null ||
    minActionCost.value != null ||
    occupyHandsLabel.value != null,
);

function advantageLine(delta: number, source: string | null): string {
  const sign = delta >= 0 ? '+' : '';
  const sourceName = source ? ruleViewLabelService.ruleName(props.rules, source) : 'без источника';

  return `${sign}${delta} (${sourceName})`;
}

function openRule(code: string | null): void {
  if (code) emit('open-rule', code);
}

function defenseSourceLabel(code: string | null): string | null {
  if (!code) return null;

  return DEFENSE_SOURCE_LABELS[code] ?? ruleViewLabelService.ruleName(props.rules, code);
}

function armorLimitLabel(limit: CharacteristicLimit): string {
  return `Потолок «${ruleViewLabelService.ruleName(props.rules, limit.characteristic_code)}»`;
}
</script>

<template>
  <div v-if="spec">
    <v-card v-if="weaponProfiles.length" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-2">{{ profilesTitle }}</div>
        <div class="d-flex flex-column ga-2">
          <WeaponProfileCard
            v-for="(profile, index) in weaponProfiles"
            :key="index"
            :profile="profile"
            :rules="rules"
          />
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="block" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-2">Блокирование</div>
        <div class="item-stat-grid">
          <ItemStat label="Эффективность" :value="ruleViewLabelService.dimensional(block.efficiency)" />
          <ItemStat label="Защита" :value="ruleViewLabelService.dimensional(block.defense)" />
        </div>
        <div v-for="(slot, index) in block.resistances" :key="`br-${index}`" class="text-body-2 mt-2">
          {{ ruleViewLabelService.resistanceLabel(slot) }}
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="armor" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-2">Доспех</div>
        <div v-for="(limit, index) in armorLimits" :key="`lim-${index}`" class="text-body-2">
          {{ armorLimitLabel(limit) }}:
          <strong>{{ ruleViewLabelService.formula(limit.limit, rules) }}</strong>
        </div>
        <div v-for="(slot, index) in armorDefenseSlots" :key="`d-${index}`" class="text-body-2">
          Защита {{ ruleViewLabelService.dimensional(slot.defense) }}, надёжность {{ slot.durability
          }}<span v-if="defenseSourceLabel(slot.source_code)"> ({{ defenseSourceLabel(slot.source_code) }})</span>
        </div>
        <div v-for="(slot, index) in armorResistanceSlots" :key="`r-${index}`" class="text-body-2">
          {{ ruleViewLabelService.resistanceLabel(slot) }}
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="shieldLimits.length" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-2">Щит</div>
        <div v-for="(limit, index) in shieldLimits" :key="`sl-${index}`" class="text-body-2">
          {{ armorLimitLabel(limit) }}:
          <strong>{{ ruleViewLabelService.formula(limit.limit, rules) }}</strong>
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="hasUsage" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-2">Использование</div>
        <div class="item-stat-grid">
          <ItemStat v-if="familyName" label="Семья владения">
            <a href="#" class="item-rule-link" @click.prevent="openRule(familyCode)">{{ familyName }}</a>
          </ItemStat>
          <ItemStat v-if="minStrengthLabel" label="Минимальная сила" :value="minStrengthLabel" />
          <ItemStat v-if="minActionCost != null" label="Минимум ОД" :value="String(minActionCost)" />
          <ItemStat v-if="occupyHandsLabel" label="Занимает" :value="occupyHandsLabel" />
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="advantages.length || checkAdvantages.length" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Помехи и преимущества</div>
        <div v-for="(entry, index) in advantages" :key="`a-${index}`" class="text-body-2">
          {{ advantageLine(entry.delta, entry.source_code) }}
        </div>
        <div v-for="(entry, index) in checkAdvantages" :key="`c-${index}`" class="text-body-2">
          Проверки ({{ entry.characteristic_codes.map((code) => ruleViewLabelService.ruleName(rules, code)).join(', ')
          }}{{ entry.includes_hit ? ', включая попадание' : '' }}): {{ entry.delta >= 0 ? '+' : '' }}{{ entry.delta }}
        </div>
      </v-card-text>
    </v-card>

    <v-card variant="tonal" class="mb-3">
      <v-card-text>
        <div class="d-flex align-center mb-2">
          <div class="text-subtitle-2">Общее</div>
          <v-chip v-if="spec.innate" size="x-small" variant="tonal" class="ml-2">Врождённый предмет</v-chip>
          <v-chip v-if="spec.projectile" size="x-small" variant="tonal" class="ml-2">Снаряд</v-chip>
        </div>
        <div class="item-stat-grid">
          <ItemStat v-if="categoryLabel" label="Категория" :value="categoryLabel" />
          <ItemStat v-if="spec.cost_gm != null" label="Цена" :value="`${spec.cost_gm} гм`" />
          <ItemStat v-if="weightLabel" label="Вес" :value="weightLabel" />
          <ItemStat v-if="durabilityLabel" label="Прочность" :value="durabilityLabel" />
          <ItemStat v-if="groupName" label="Группа" :value="groupName" />
          <ItemStat v-if="spec.magic_conductor" label="Проводник магии" :value="String(spec.magic_conductor)" />
        </div>
        <div v-if="specialRules.length" class="text-body-2 mt-2">
          Особые правила: <strong>{{ specialRules.join(', ') }}</strong>
        </div>
        <div v-for="(grant, index) in spec.grants ?? []" :key="`grant-${index}`" class="text-body-2 mt-1">
          {{ ruleViewLabelService.grant(grant, rules, []) }}
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>

<style scoped>
.item-stat-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 8px 16px;
}

.item-rule-link {
  color: rgb(var(--v-theme-primary));
  text-decoration: underline dotted;
  text-underline-offset: 3px;
}
</style>
