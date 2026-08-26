<script setup lang="ts">
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { checkResolutionService } from '@/modules/Roleplay/Rule/Service/Instance/checkResolutionService';
import { checkLaunchService } from '@/modules/Roleplay/Rule/Service/Instance/checkLaunchService';
import { computed } from 'vue';

const props = defineProps<{
  rule: Rule;
  rules: Rule[];
}>();

const spec = computed(() => checkResolutionService.asCheckSpec(props.rule));
const parent = computed(() => {
  const code = spec.value?.parent_check_code;
  if (!code) return undefined;

  return props.rules.find((candidate) => candidate.code === code);
});
const characteristic = computed(() => {
  const code = spec.value?.characteristic_code;
  if (!code) return undefined;

  return props.rules.find((candidate) => candidate.code === code);
});

const attachedRules = computed(() => {
  const codes = spec.value?.attached_rule_codes ?? [];

  return codes
    .map((code) => props.rules.find((candidate) => candidate.code === code))
    .filter((rule): rule is Rule => rule !== undefined);
});

const versusLabel = computed(() => checkLaunchService.checkVersusLabel(props.rule, props.rules));

function difficultyLabel(): string {
  const current = spec.value;
  if (!current) return '—';
  const input = current.difficulty_input;
  if (input.kind === 'ask') return 'задаёт мастер (быстро — против 0)';
  if (input.kind === 'none') return 'нет своей (чужие итоги / порядок)';
  const state = props.rules.find((candidate) => candidate.code === input.state_code);

  return `из состояния «${state?.name ?? input.state_code}»`;
}

function modesLabel(): string {
  const current = spec.value;
  if (!current) return '—';
  if (current.allowed_modes === 'solo') return 'только одиночная';
  if (current.allowed_modes === 'joint') return 'только совместная';

  return 'одиночная и совместная';
}
</script>

<template>
  <v-card v-if="spec" variant="tonal" class="mb-4">
    <v-card-text>
      <div class="text-body-2">
        Системное имя: <strong>{{ rule.code }}</strong>
      </div>
      <div v-if="parent" class="text-body-2 mt-1">
        Наследует: <strong>{{ parent.name }}</strong> ({{ parent.code }})
      </div>
      <div v-if="characteristic" class="text-body-2 mt-1">
        Пул: <strong>{{ characteristic.name }}</strong>
      </div>
      <div v-if="versusLabel" class="text-body-2 mt-1">{{ versusLabel }}</div>
      <div class="text-body-2 mt-1">Сложность: {{ difficultyLabel() }}</div>
      <div class="text-body-2 mt-1">Режимы: {{ modesLabel() }}</div>
      <div v-if="attachedRules.length" class="text-body-2 mt-1">
        Правила броска:
        <strong>{{ attachedRules.map((item) => item.name).join(', ') }}</strong>
      </div>
      <div v-else-if="spec.attached_rule_codes" class="text-body-2 mt-1">Правила броска: нет (не наследует)</div>
      <div v-if="spec.allow_characteristic_override" class="text-body-2 mt-1">
        На запуске можно подменить характеристику.
      </div>
    </v-card-text>
  </v-card>
</template>
