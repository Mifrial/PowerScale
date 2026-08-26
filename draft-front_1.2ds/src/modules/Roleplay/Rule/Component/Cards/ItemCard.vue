<script setup lang="ts">
import { computed } from 'vue';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import type { ItemSpec } from '@/modules/Roleplay/Rule/Dto/Item/ItemSpec';
import { ITEM_CATEGORIES } from '@/modules/Roleplay/Rule/Constant/Item/ITEM_CATEGORIES';
import { ruleViewLabelService } from '@/modules/Roleplay/Rule/Service/Instance/ruleViewLabelService';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
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

const familyName = computed(() => {
  const code = spec.value?.proficiency_family_code;
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

function advantageLine(delta: number, source: string | null): string {
  const sign = delta >= 0 ? '+' : '';
  const sourceName = source ? ruleViewLabelService.ruleName(props.rules, source) : 'без источника';

  return `${sign}${delta} (${sourceName})`;
}

const weapon = computed(() => spec.value?.weapon);
const armor = computed(() => spec.value?.armor);
const shield = computed(() => spec.value?.shield);
</script>

<template>
  <div v-if="spec">
    <v-card variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Общее</div>
        <div v-if="categoryLabel" class="text-body-2">
          Категория: <strong>{{ categoryLabel }}</strong>
        </div>
        <div v-if="spec.cost_gm != null" class="text-body-2">
          Цена: <strong>{{ spec.cost_gm }} гм</strong>
        </div>
        <div v-if="spec.weight" class="text-body-2">
          Вес: <strong>{{ ruleViewLabelService.dimensional(spec.weight) }}</strong>
        </div>
        <div v-if="spec.innate" class="text-body-2">Врождённый предмет</div>
        <div v-if="familyName" class="text-body-2">
          Семья владения: <strong>{{ familyName }}</strong>
        </div>
        <div v-if="groupName" class="text-body-2">
          Группа: <strong>{{ groupName }}</strong>
        </div>
        <div v-if="spec.magic_conductor" class="text-body-2">
          Проводник магии: <strong>{{ spec.magic_conductor }}</strong>
        </div>
        <div v-if="specialRules.length" class="text-body-2">
          Особые правила: <strong>{{ specialRules.join(', ') }}</strong>
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

    <v-card v-if="weapon" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Оружие</div>
        <div v-if="weapon.min_strength" class="text-body-2">
          Мин. сила: <strong>{{ ruleViewLabelService.dimensional(weapon.min_strength) }}</strong>
        </div>
        <div v-if="weapon.durability" class="text-body-2">
          Прочность: <strong>{{ ruleViewLabelService.dimensional(weapon.durability) }}</strong>
        </div>
        <div v-if="weapon.min_action_cost" class="text-body-2">
          Минимум ОД: <strong>{{ weapon.min_action_cost }}</strong>
        </div>
        <div v-if="weapon.block_profile" class="text-body-2 mt-1">
          Блок: {{ ruleViewLabelService.blockProfile(weapon.block_profile, rules) }}
        </div>
        <div v-for="(profile, index) in weapon.weapon_profiles" :key="index" class="text-body-2 mt-1">
          {{ ruleViewLabelService.weaponProfile(profile, rules) }}
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="armor" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Доспех</div>
        <div v-if="armor.max_agility" class="text-body-2">
          Макс. ловкость: <strong>{{ ruleViewLabelService.dimensional(armor.max_agility) }}</strong>
        </div>
        <div v-if="armor.strength_penalty != null" class="text-body-2">
          Штраф к силе: <strong>{{ armor.strength_penalty }}</strong>
        </div>
        <div v-for="(slot, index) in armor.defense_slots" :key="`d-${index}`" class="text-body-2">
          Защита {{ ruleViewLabelService.dimensional(slot.defense) }}, надёжность {{ slot.durability
          }}<template v-if="slot.source_code"> ({{ ruleViewLabelService.ruleName(rules, slot.source_code) }})</template>
        </div>
        <div v-for="(slot, index) in armor.resistance_slots" :key="`r-${index}`" class="text-body-2">
          Сопротивление
          {{ slot.damage_type_code ? ruleViewLabelService.ruleName(rules, slot.damage_type_code) : 'любой' }}:
          {{ ruleViewLabelService.dimensional(slot.value) }}, надёжность {{ slot.durability }}
        </div>
        <div v-for="(limit, index) in armor.characteristic_limits" :key="`l-${index}`" class="text-body-2">
          Лимит «{{ ruleViewLabelService.ruleName(rules, limit.characteristic_code) }}»:
          {{ ruleViewLabelService.formula(limit.limit, rules) }}
        </div>
      </v-card-text>
    </v-card>

    <v-card v-if="shield" variant="tonal" class="mb-3">
      <v-card-text>
        <div class="text-subtitle-2 mb-1">Щит</div>
        <div v-if="shield.min_strength" class="text-body-2">
          Мин. сила: <strong>{{ ruleViewLabelService.dimensional(shield.min_strength) }}</strong>
        </div>
        <div v-if="shield.durability" class="text-body-2">
          Прочность: <strong>{{ ruleViewLabelService.dimensional(shield.durability) }}</strong>
        </div>
        <div class="text-body-2 mt-1">Блок: {{ ruleViewLabelService.blockProfile(shield.block, rules) }}</div>
        <div v-for="(profile, index) in shield.weapon_profiles ?? []" :key="index" class="text-body-2 mt-1">
          {{ ruleViewLabelService.weaponProfile(profile, rules) }}
        </div>
        <div v-for="(limit, index) in shield.characteristic_limits ?? []" :key="`sl-${index}`" class="text-body-2">
          Лимит «{{ ruleViewLabelService.ruleName(rules, limit.characteristic_code) }}»:
          {{ ruleViewLabelService.formula(limit.limit, rules) }}
        </div>
      </v-card-text>
    </v-card>
  </div>
</template>
