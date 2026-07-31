# Чеклист для ревью фронтенда

## 1. Архитектура и слои

### Разделение слоёв
- [ ] UI компоненты не обращаются к API напрямую
- [ ] Store не содержит бизнес-логику (только оркестрация)
- [ ] Service/API слой не знает о UI/Store
- [ ] Core модули не импортируют из feature-модулей

### Dependency Injection
- [ ] ServiceLocator используется корректно
- [ ] Нет прямых импортов сервисов (только через getXxx())
- [ ] Mock-режим переключается через VITE_API_MODE
- [ ] Нет циклических зависимостей между модулями

### Модульная структура
- [ ] Каждый модуль самодостаточен
- [ ] Общие утииты в Core/shared
- [ ] Нет дублирования кода между модулями

## 2. Vue 3 + Pinia

### Реактивность
- [ ] Используется `storeToRefs` при деструктуризации из store
- [ ] Нет потери реактивности в computed
- [ ] Ref/reactive используются корректно

### Watchers
- [ ] Нет `deep: true` без необходимости
- [ ] Watchers очищаются в onUnmounted
- [ ] Нет бесконечных циклов (watch → emit → watch)

### Lifecycle
- [ ] Timers очищаются в onUnmounted
- [ ] Event listeners удаляются в onUnmounted
- [ ] Subscriptions закрываются в onUnmounted
- [ ] AbortController для отмены запросов при unmount

### Computed
- [ ] Computed не делают side effects
- [ ] Computed не дублируют методы
- [ ] Computed используются вместо методов в шаблонах

## 3. TypeScript

### Типизация
- [ ] Нет злоупотребления `any` (только где действительно нужно)
- [ ] `unknown` используется с type guards
- [ ] Нет `@ts-ignore` без комментария почему
- [ ] Типы соответствуют ТР (User, Chat, и т.д.)

### Generics
- [ ] API методы типизированы через generics
- [ ] Переиспользуемые компоненты используют generics
- [ ] Нет дублирования типов

### Dead code
- [ ] Нет неиспользуемых типов
- [ ] Нет закомментированного кода
- [ ] Нет мёртвых функций

## 4. Функциональность

### Authentication
- [ ] Login работает (mock + real)
- [ ] Logout работает
- [ ] Session восстанавливается при refresh
- [ ] Guards защищают приватные роуты
- [ ] Guest mode работает корректно

### CRUD операции
- [ ] Create работает + валидация
- [ ] Read работает + loading states
- [ ] Update работает + optimistic updates
- [ ] Delete работает + подтверждение

### Навигация
- [ ] Роутинг работает корректно
- [ ] Back/forward работают
- [ ] Deep linking работает
- [ ] 404 страница есть

### Состояния UI
- [ ] Loading state показывается
- [ ] Error state обрабатывается
- [ ] Empty state показывается
- [ ] Success feedback есть

## 5. Безопасность

### Токены и сессии
- [ ] Токены НЕ в localStorage
- [ ] Токены в httpOnly cookies
- [ ] Session восстанавливается через API
- [ ] Нет токенов в URL/query params

### CSRF
- [ ] CSRF token используется для mutations
- [ ] Token в заголовке X-CSRF-Token
- [ ] Token получается из cookie

### Guards
- [ ] Приватные роуты защищены
- [ ] Admin роуты проверяют роль
- [ ] Redirect на login если не авторизован
- [ ] Redirect на home если авторизован при попытке login

## 6. Производительность

### API запросы
- [ ] Нет N+1 запросов (batch endpoints)
- [ ] Debounce на частых инпутах (search, filter)
- [ ] Throttle на scroll/resize
- [ ] Кэширование где нужно

### Рендеринг
- [ ] Большие списки оптимизированы (virtual scroll или пагинация)
- [ ] v-memo используется где нужно
- [ ] v-once для статического контента
- [ ] Нет лишних ре-рендеров

### Оптимизации
- [ ] Lazy loading для роутов
- [ ] Code splitting работает
- [ ] Assets оптимизированы

## 7. UI/UX

### Vuetify
- [ ] Компоненты используются по назначению
- [ ] Grid система соблюдается (v-row, v-col)
- [ ] Breakpoints для адаптивности
- [ ] Темы используются корректно

### Состояния
- [ ] Loading: skeleton/spinner
- [ ] Error: alert + retry button
- [ ] Empty: illustration + CTA
- [ ] Success: snackbar/toast

### Доступность
- [ ] Семантический HTML
- [ ] Keyboard navigation работает
- [ ] ARIA labels где нужно
- [ ] Contrast ratio достаточный

## 8. Code Quality

### Именование
- [ ] Компоненты в PascalCase
- [ ] Файлы в kebab-case
- [ ] Переменные в camelCase
- [ ] Константы в UPPER_SNAKE_CASE

### Структура
- [ ] Компоненты < 300 строк
- [ ] Функции < 50 строк
- [ ] Нет вложенности > 3 уровней
- [ ] DRY принцип соблюдается

### Комментарии
- [ ] Комментарии объясняют "почему", не "что"
- [ ] TODO с issue/ticket
- [ ] Нет закомментированного кода
- [ ] JSDoc для публичных API

## 9. Тесты

### Покрытие
- [ ] Store методы покрыты
- [ ] Service методы покрыты
- [ ] Утииты покрыты
- [ ] Критичные компоненты покрыты

### Качество
- [ ] Тесты читаемые
- [ ] Тесты независимые
- [ ] Тесты быстрые
- [ ] Нет flaky тестов

## 10. Документация

### Код
- [ ] README обновлён
- [ ] API задокументировано
- [ ] Компоненты задокументированы

### Процессы
- [ ] How to run
- [ ] How to test
- [ ] How to deploy
- [ ] Architecture decisions
