import type { Notification } from '@/modules/Messages/Notifications/Dto/Notification';
import type { NotificationAction } from '@/modules/Messages/Notifications/Dto/NotificationAction';
import type { NotificationFilters } from '@/modules/Messages/Notifications/Dto/NotificationFilters';
import type { NotificationPage } from '@/modules/Messages/Notifications/Dto/NotificationPage';

const delay = (ms = 150) => new Promise((r) => setTimeout(r, ms));

const ago = (ms: number) => new Date(Date.now() - ms).toISOString();

const createNotification = (
  id: number,
  title: string,
  preview: string,
  createdAt: string,
  icon: string,
  actions: NotificationAction[],
  read = false,
): Notification => ({ id, title, preview, createdAt, icon, read, actions });

const mockNotifications: Notification[] = [
  createNotification(
    1,
    'Приглашение в игру «Школа волшебства»',
    'Ведущий Анна С. приглашает вас присоединиться к игре',
    ago(5 * 60000),
    'mdi-gamepad-variant',
    [
      { label: 'Принять', key: 'accept', color: 'primary' },
      { label: 'Отклонить', key: 'decline', color: 'primary' },
    ],
  ),
  createNotification(
    2,
    'Модерация персонажа «Мэллорн»',
    'Персонаж ожидает проверки ведущим игры «Подземелье дракона»',
    ago(3600000),
    'mdi-account-clock',
    [{ label: 'Проверить', key: 'check', color: 'primary' }],
  ),
  createNotification(
    3,
    'Миграция версии завершена',
    'Персонаж «Элиандра» успешно перенесён на новую версию правил',
    ago(3 * 3600000),
    'mdi-cube-send',
    [],
    true,
  ),
  createNotification(
    4,
    'Приглашение принято',
    'Пётр К. принял приглашение в игру «Подземелье дракона»',
    ago(86400000),
    'mdi-account-check',
    [],
  ),
  createNotification(
    5,
    'Обновление правил «Кровавый рубеж»',
    'В игру добавлены новые механики и классы персонажей',
    ago(2 * 3600000),
    'mdi-book-open-variant',
    [{ label: 'Просмотреть', key: 'view', color: 'primary' }],
  ),
  createNotification(
    6,
    'Новый персонаж в игре',
    'Игрок Виталий М. создал персонажа «Гром» в игре «Школа волшебства»',
    ago(4 * 3600000),
    'mdi-account-plus',
    [
      { label: 'Одобрить', key: 'approve', color: 'primary' },
      { label: 'Отклонить', key: 'reject', color: 'error' },
    ],
  ),
  createNotification(
    7,
    'Завершён раунд «Тень дракона»',
    'Раунд 3 игры «Подземелье дракона» завершён. Ожидаются итоги.',
    ago(6 * 3600000),
    'mdi-sword-cross',
    [],
    true,
  ),
  createNotification(
    8,
    'Приглашение в игру «Звёздные странники»',
    'Ведущий Игорь Л. приглашает вас в новую кампанию',
    ago(8 * 3600000),
    'mdi-rocket-launch',
    [
      { label: 'Принять', key: 'accept', color: 'primary' },
      { label: 'Отклонить', key: 'decline', color: 'primary' },
    ],
  ),
  createNotification(
    9,
    'Изменение в расписании',
    'Сессия игры «Школа волшебства» перенесена на субботу',
    ago(12 * 3600000),
    'mdi-calendar-clock',
    [],
    true,
  ),
  createNotification(
    10,
    'Достижение разблокировано',
    'Вы получили достижение «Опытный стратег» за 10 проведённых сессий',
    ago(86400000),
    'mdi-trophy',
    [{ label: 'Посмотреть', key: 'view', color: 'primary' }],
  ),
  createNotification(
    11,
    'Новый игрок в «Подземелье дракона»',
    'К игре присоединился новый игрок — Алексей Р.',
    ago(2 * 86400000),
    'mdi-account-multiple-plus',
    [{ label: 'Поприветствовать', key: 'greet', color: 'primary' }],
  ),
  createNotification(
    12,
    'Персонаж требует доработки',
    'Ведущий отправил персонажа «Мэллорн» на доработку. Требуется исправить навыки.',
    ago(2 * 86400000),
    'mdi-alert-circle',
    [{ label: 'Редактировать', key: 'edit', color: 'primary' }],
  ),
  createNotification(
    13,
    'Системное обновление',
    'Платформа PowerScale обновлена до версии 2.4.1. Исправлены ошибки.',
    ago(3 * 86400000),
    'mdi-update',
    [],
    true,
  ),
  createNotification(
    14,
    'Архивация игры «Старый мир»',
    'Кампания «Старый мир» будет архивирована через 7 дней',
    ago(4 * 86400000),
    'mdi-archive',
    [
      { label: 'Отложить', key: 'postpone', color: 'primary' },
      { label: 'Архивировать', key: 'archive', color: 'primary' },
    ],
  ),
  createNotification(
    15,
    'Приглашение в игру «Морская одиссея»',
    'Капитан crew набирает команду для морского приключения',
    ago(5 * 86400000),
    'mdi-sail-boat',
    [
      { label: 'Принять', key: 'accept', color: 'primary' },
      { label: 'Отказаться', key: 'decline', color: 'error' },
    ],
  ),
  createNotification(
    16,
    'Голосование за нового ведущего',
    'В сообществе началось голосование за ведущего следующей кампании',
    ago(6 * 86400000),
    'mdi-ballot',
    [],
    true,
  ),
  createNotification(
    17,
    'Персонаж «Элиандра» повышен',
    'Персонаж достиг 5 уровня в игре «Подземелье дракона»',
    ago(6 * 86400000),
    'mdi-arrow-up-bold',
    [],
    true,
  ),
  createNotification(
    18,
    'Новый мод в игре',
    'Добавлен мод «Расширенная магия» для игры «Школа волшебства»',
    ago(7 * 86400000),
    'mdi-puzzle',
    [{ label: 'Подробнее', key: 'details', color: 'primary' }],
  ),
  createNotification(
    19,
    'Напоминание: сессия завтра',
    'Сессия игры «Подземелье дракона» состоится завтра в 19:00',
    ago(7 * 86400000),
    'mdi-bell-ring',
    [],
    true,
  ),
  createNotification(
    20,
    'Изменение правил игры',
    'Игра «Школа волшебства» обновила лист персонажа — добавлены новые заклинания',
    ago(8 * 86400000),
    'mdi-file-document-edit',
    [{ label: 'Просмотреть', key: 'view', color: 'primary' }],
  ),
];

export async function mockFetchNotificationsPage(filters: NotificationFilters): Promise<NotificationPage> {
  await delay();

  // Серверная фильтрация
  let filtered = [...mockNotifications];

  if (filters.filter === 'unread') {
    filtered = filtered.filter((n) => !n.read);
  } else if (filters.filter === 'action') {
    filtered = filtered.filter((n) => n.actions.length > 0);
  }

  // Серверный поиск
  if (filters.search) {
    const searchLower = filters.search.toLowerCase();
    filtered = filtered.filter(
      (n) => n.title.toLowerCase().includes(searchLower) || n.preview.toLowerCase().includes(searchLower),
    );
  }

  const total = filtered.length;
  const unreadCount = mockNotifications.filter((n) => !n.read).length;

  // Пагинация
  const items = filtered.slice(filters.offset, filters.offset + filters.limit);

  return { items, total, unreadCount };
}

export async function mockMarkAsRead(id: number): Promise<void> {
  await delay();
  const n = mockNotifications.find((x) => x.id === id);
  if (n) n.read = true;
}

export async function mockMarkAllAsRead(): Promise<void> {
  await delay();
  mockNotifications.forEach((n) => {
    n.read = true;
  });
}
