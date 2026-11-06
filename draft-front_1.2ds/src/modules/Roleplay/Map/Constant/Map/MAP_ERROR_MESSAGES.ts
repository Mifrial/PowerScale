/** Русские подсказки по кодам отказа `map.*`. */
export const MAP_ERROR_MESSAGES: Record<string, string> = {
  AUTH_REQUIRED: 'Нужно войти в систему',
  AUTH_DENIED: 'Недостаточно прав',
  INVALID_PARAMS: 'Проверьте заполнение полей',
  MAP_INVALID: 'Недопустимые данные карты',
  MAP_NOT_FOUND: 'Карта не найдена',
  MAP_DENIED: 'Нет доступа к карте',
  MAP_NO_ROUTE: 'Между точками нет проходимого маршрута',
  MAP_VERSION_CONFLICT: 'Карту изменили с момента загрузки',
  MAP_EMPTY_RESPONSE: 'Сервер не вернул карту',
};
