/** Режимы длительности эффекта заклинания. */
export const SPELL_DURATION_OPTIONS: { title: string; value: 'instant' | 'lingering' | 'refreshable' | 'sustained' }[] =
  [
    { title: 'Мгновенное', value: 'instant' },
    { title: 'Длительное', value: 'lingering' },
    { title: 'Обновляемое', value: 'refreshable' },
    { title: 'Поддерживаемое', value: 'sustained' },
  ];
