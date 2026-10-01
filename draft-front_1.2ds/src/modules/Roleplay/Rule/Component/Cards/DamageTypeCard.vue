<script setup lang="ts">
import type { Mechanic } from '@/modules/Roleplay/Mechanic/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { damageTypeSpecService } from '@/modules/Roleplay/Rule/Service/Instance/damageTypeSpecService';
import { computed } from 'vue';

const props = defineProps<{
  rule: Rule;
  mechanics?: Mechanic[];
}>();

const spec = computed(() => damageTypeSpecService.asDamageTypeSpec(props.rule));
const mechanicNames = computed(() =>
  props.rule.mechanics.map((row) => props.mechanics?.find((mechanic) => mechanic.id === row.mechanicId)?.name ?? String(row.mechanicId)),
);
</script>

<template>
  <v-alert v-if="!spec" type="warning" variant="tonal" class="mb-4">
    У типа урона нет спеки: в редакторе нужно заполнить родительный и дательный.
  </v-alert>
  <v-card v-else variant="tonal" class="mb-4">
    <v-card-text>
      <div class="text-body-2">
        Системное имя: <strong>{{ rule.code }}</strong>
      </div>
      <div v-if="spec.forms.genitive" class="text-body-2 mt-1">Родительный: {{ spec.forms.genitive }}</div>
      <div v-else class="text-body-2 mt-1 text-error">Родительный не заполнен</div>
      <div v-if="spec.forms.dative" class="text-body-2 mt-1">Дательный: {{ spec.forms.dative }}</div>
      <div v-else class="text-body-2 mt-1 text-error">Дательный не заполнен</div>
      <div v-if="spec.defense_ignored" class="text-body-2 mt-1">Защита не помогает</div>
      <div v-if="spec.max_success_rating" class="text-body-2 mt-1">
        Множитель РУ не больше {{ spec.max_success_rating }}
      </div>
      <div v-if="spec.modifies_spell_difficulty" class="text-body-2 mt-1">
        Сопротивление этому типу увеличивает Сложность сотворения
      </div>
      <div v-if="mechanicNames.length" class="text-body-2 mt-1">
        Механики:
        <strong>{{ mechanicNames.join(', ') }}</strong>
      </div>
      <div v-else class="text-body-2 mt-1">Механики не подвешены</div>
    </v-card-text>
  </v-card>
</template>
