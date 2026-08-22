<script setup lang="ts">
import { computed } from 'vue';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterOverviewService } from '@/modules/Roleplay/Character/Service/Instance/characterOverviewService';
import RuleLink from '@/modules/Roleplay/Character/Component/Detail/RuleLink.vue';

const props = defineProps<{
  version: CharacterVersion;
  rules: Rule[];
  rulesLoading: boolean;
}>();

const items = computed(() => characterOverviewService.build(props.version, props.rules).inventory);
</script>

<template>
  <v-card>
    <v-card-title>Инвентарь</v-card-title>
    <div v-if="rulesLoading && rules.length === 0" class="pa-4 text-medium-emphasis">Загружаем правила…</div>
    <v-table v-else-if="items.length">
      <thead>
        <tr>
          <th>Название</th>
          <th>Категория</th>
          <th class="text-right">Кол-во</th>
          <th>Описание</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id">
          <td>
            <RuleLink v-if="item.ruleId" :rule-id="item.ruleId">{{ item.name }}</RuleLink>
            <template v-else>{{ item.name }}</template>
            <v-chip
              v-for="modifierName in item.modifierNames"
              :key="modifierName"
              class="ml-1"
              size="x-small"
              variant="outlined"
            >
              {{ modifierName }}
            </v-chip>
          </td>
          <td>
            <v-chip size="x-small" variant="tonal">{{ item.categoryLabel }}</v-chip>
          </td>
          <td class="text-right">{{ item.quantity }}</td>
          <td class="text-medium-emphasis">{{ item.note ?? '—' }}</td>
        </tr>
      </tbody>
    </v-table>
    <v-card-text v-else class="text-medium-emphasis">Инвентарь пуст</v-card-text>
  </v-card>
</template>
