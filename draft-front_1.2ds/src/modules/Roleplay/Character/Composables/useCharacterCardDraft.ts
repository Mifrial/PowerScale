import { computed, onMounted, ref, type ComputedRef, type Ref } from 'vue';
import type { CharacterDetail } from '@/modules/Roleplay/Character/Dto/CharacterDetail';
import type { CharacterVersion } from '@/modules/Roleplay/Character/Dto/CharacterVersion';
import type { Mechanic } from '@/modules/Roleplay/Rule/Dto/Mechanic';
import type { Rule } from '@/modules/Roleplay/Rule/Dto/Rule';
import { characterBuildService } from '@/modules/Roleplay/Character/Service/Instance/characterBuildService';
import { characterEditorService } from '@/modules/Roleplay/Character/Service/Instance/characterEditorService';
import { useCharacterDraftStore } from '@/modules/Roleplay/Character/Store/characterDraft';
import { useCharacterStore } from '@/modules/Roleplay/Character/Store/characters';
import { getCharacterApi } from '@/modules/Roleplay/Character/init';
import { characterSheetValidationService } from '@/modules/Roleplay/Character/Service/Instance/characterSheetValidationService';
import { getRuleApi } from '@/modules/Roleplay/Rule/init';
import { useKeywordStore } from '@/modules/Roleplay/Rule/Store/keywords';

/**
 * Черновик и быстрое сохранение с карточки персонажа: экип (и дальше другие правки)
 * идут в тот же `character:${id}`, что standalone-редактор.
 */
export function useCharacterCardDraft(
  detail: ComputedRef<CharacterDetail | null>,
  rules: Ref<Rule[]>,
  canEdit: ComputedRef<boolean>,
  signal: ComputedRef<AbortSignal>,
) {
  const draftStore = useCharacterDraftStore();
  const characterStore = useCharacterStore();
  const keywordStore = useKeywordStore();
  const mechanics = ref<Mechanic[]>([]);
  const saving = ref(false);
  const saveError = ref<string | null>(null);
  const catalogError = ref<string | null>(null);

  const draftKey = computed(() => {
    const id = detail.value?.character.id;
    if (id == null) return null;

    return `character:${id}`;
  });

  const draft = computed(() => draftStore.draftOf(draftKey.value));

  const config = computed(() => {
    if (draft.value) return draft.value.config;
    const version = detail.value?.version;
    if (!version) return { osTotal: null, orTotal: null, moneyBudget: null };

    return {
      osTotal: version.budgets?.osTotal ?? null,
      orTotal: version.points.orTotal ?? null,
      moneyBudget: version.budgets?.moneyBudget ?? null,
    };
  });

  const build = computed(() => {
    const current = detail.value;
    if (draft.value) return draft.value.build;
    if (!current || rules.value.length === 0) return null;

    return characterBuildService.fromVersion(current.version, current.character.spaceId, rules.value);
  });

  const model = computed(() => {
    if (!build.value || rules.value.length === 0) return null;

    return characterEditorService.build(build.value, rules.value, config.value, keywordStore.keywords, mechanics.value);
  });

  const displayVersion = computed<CharacterVersion | null>(() => {
    const current = detail.value;
    if (!current) return null;
    if (!draft.value?.dirty || !build.value || rules.value.length === 0) return current.version;

    return characterEditorService.toVersion(
      build.value,
      rules.value,
      config.value,
      keywordStore.keywords,
      mechanics.value,
    );
  });

  const validationIssues = computed(() =>
    characterSheetValidationService.characterSheetValidationIssues(draft.value?.build, model.value, true),
  );

  function ensureDraft(): void {
    if (!canEdit.value) return;
    const current = detail.value;
    const key = draftKey.value;
    if (!current || key === null || draftStore.draftOf(key)) return;
    const version = current.version;
    const fromRules = rules.value;
    const nextBuild = characterBuildService.fromVersion(version, current.character.spaceId, fromRules);
    const baseline = {
      inventory: nextBuild.inventory.map((item) => ({ ...item })),
      money: nextBuild.money,
    };
    draftStore.initDraft(
      key,
      nextBuild,
      {
        osTotal: version.budgets?.osTotal ?? null,
        orTotal: version.points.orTotal ?? null,
        moneyBudget: version.budgets?.moneyBudget ?? null,
      },
      baseline,
    );
  }

  async function save(): Promise<void> {
    const current = detail.value;
    const key = draftKey.value;
    const entry = draftStore.draftOf(key);
    if (!current || key === null || !entry || !model.value || saving.value) return;
    saveError.value = null;
    if (catalogError.value) {
      saveError.value = catalogError.value;

      return;
    }
    const issues = characterSheetValidationService.characterSheetValidationIssues(entry.build, model.value, true);
    if (issues.length > 0) {
      saveError.value = `Нельзя сохранить: ${issues.join('; ')}`;

      return;
    }
    saving.value = true;
    try {
      const version = characterEditorService.toVersion(
        entry.build,
        rules.value,
        entry.config,
        keywordStore.keywords,
        mechanics.value,
      );
      const updated = await getCharacterApi().updateCharacter(
        current.character.id,
        { version, status: 'ready' },
        signal.value,
      );
      characterStore.currentCharacter = updated;
      draftStore.discard(key);
    } catch (e) {
      saveError.value = e instanceof Error ? e.message : 'Не удалось сохранить персонажа';
    } finally {
      saving.value = false;
    }
  }

  async function loadCatalog(): Promise<void> {
    catalogError.value = null;
    try {
      const pending: Promise<void>[] = [];
      if (keywordStore.keywords.length === 0 || keywordStore.error) {
        pending.push(keywordStore.fetchTags(signal.value));
      }
      pending.push(
        getRuleApi()
          .getMechanics(signal.value)
          .then((list) => {
            mechanics.value = list;
          }),
      );
      await Promise.all(pending);
      if (keywordStore.error) catalogError.value = keywordStore.error;
    } catch (e) {
      if (e instanceof DOMException && e.name === 'AbortError') return;
      mechanics.value = [];
      catalogError.value = e instanceof Error ? e.message : 'Не удалось загрузить справочники редактора';
    }
  }

  onMounted(() => {
    void loadCatalog();
  });

  return {
    draftKey,
    draft,
    build,
    model,
    displayVersion,
    validationIssues,
    saving,
    saveError,
    catalogError,
    keywords: computed(() => keywordStore.keywords),
    ensureDraft,
    save,
    retryCatalog: loadCatalog,
  };
}
