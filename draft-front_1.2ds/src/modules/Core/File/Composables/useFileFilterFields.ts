import { computed, onMounted, ref } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import { displayName, getUserApi } from '@/modules/Core/User/init';
import { filterFields as baseFilterFields } from '@/modules/Core/File/Constant/Grid/files/filterFields';

/** Поля фильтра файлов с динамическим списком владельцев. */
export function useFileFilterFields() {
  const ownerOptions = ref<{ label: string; value: number }[]>([]);

  onMounted(async () => {
    try {
      const page = await getUserApi().findPage({ limit: 500, offset: 0 });
      ownerOptions.value = page.items.map((user) => ({
        label: displayName(user.name, user.surname, user.login),
        value: user.id,
      }));
    } catch {
      // Список владельцев — вспомогательный: без него фильтр по id остаётся пустым.
    }
  });

  const fields = computed<FilterField[]>(() =>
    baseFilterFields.map((field) => (field.key === 'ownerId' ? { ...field, options: ownerOptions.value } : field)),
  );

  return { fields };
}
