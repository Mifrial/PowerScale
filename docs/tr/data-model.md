# Модель данных и backend-требования

**Статус:** legacy-backed requirement; frontend/domain подтверждения указаны отдельно. Этот документ не утверждает, что backend уже реализован.

## Соглашения

- `id` — автоинкремент, если не указано иное;
- `created_at`, `updated_at` — timestamp;
- `→` — внешний ключ;
- поля и индексы из старого ТР сохраняются как `REQUIREMENT`, пока не подтверждены реальным backend.
- `BOOL` соответствует `TINYINT(1)`; `id` обычно `SERIAL/BIGINT UNSIGNED AUTO_INCREMENT`;
- индексы ниже обязательны для заявленных list/sync запросов;
- `ON DELETE CASCADE` применяется к дочерним macro rolls при удалении macro.

## Legacy-backed schema requirements

Все SQL-блоки до раздела `Current domain storage semantics` ниже являются схемой из legacy-ТР. Они фиксируют согласованные backend requirements, но не утверждают наличие текущего backend. Подтверждённые frontend/domain semantics вынесены отдельно и имеют собственные owners.

### Файлы и authentication

```sql
files(
  id,
  owner_id → users.id NOT NULL,
  filename VARCHAR,
  original_name VARCHAR,
  mime VARCHAR,
  size INT,
  path VARCHAR,
  created_at
)
INDEX (owner_id)

sessions(
  id,
  user_id → users.id NOT NULL,
  token VARCHAR UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  created_at
)
INDEX (user_id), (token)

password_reset_tokens(
  id,
  user_id → users.id NOT NULL,
  token VARCHAR UNIQUE,
  expires_at TIMESTAMP NOT NULL,
  used BOOL DEFAULT false,
  created_at
)
INDEX (token), (user_id)
```

`token` хранится как хеш refresh/одноразового токена. Storage, retention и удаление файлов — `OPEN`.

`filename` — имя файла на storage, `original_name` — имя, переданное пользователем. Доступ к файлу проверяется через `owner_id` и контекст ресурса; публичная выдача, retention и физическое удаление — `OPEN`.

### Users, groups and permissions

Канон текущей физики учётки и групп — [`user.md`](user.md) и планы User, не SQL ниже. Блок — legacy-sketch (`users`/`groups`/`super_admin`/`password_hash`/составной PK членства). SmartTable v1: одно поле unique, не PRIMARY KEY из двух колонок; пароль — Auth, не User.

```sql
users(
  id,
  login VARCHAR UNIQUE NOT NULL,
  password_hash VARCHAR NOT NULL,
  email VARCHAR UNIQUE,
  first_name VARCHAR,
  last_name VARCHAR,
  nickname VARCHAR,
  avatar_file_id → files.id NULL,
  super_admin BOOL DEFAULT false NOT NULL,
  active BOOL DEFAULT true NOT NULL,
  deactivated_until DATE NULL,
  deactivate_reason TEXT NULL,
  created_at,
  updated_at
)
INDEX (email), (login)

user_settings(
  user_id → users.id PRIMARY KEY,
  data_json JSON NOT NULL,
  updated_at
)

groups(
  id,
  name VARCHAR NOT NULL,
  active BOOL DEFAULT true NOT NULL,
  created_at
)

user_groups(
  user_id → users.id NOT NULL,
  group_id → groups.id NOT NULL,
  protected BOOL DEFAULT false NOT NULL,
  PRIMARY KEY (user_id, group_id)
)

group_permissions(
  group_id → groups.id NOT NULL,
  permission_key VARCHAR NOT NULL,
  PRIMARY KEY (group_id, permission_key)
)
```

`data_json` содержит пользовательские UI-настройки, например `notif_filter`, `table_columns` и `theme`. `protected` на `user_groups` не позволяет удалить защищённое членство super-admin. `group_permissions` — глобальные права; object-level права хранятся отдельными таблицами.

Permission catalog:

- `user.view`, `user.view_sensitive`, `user.create`, `user.edit`, `user.deactivate`;
- `user_group.view`, `user_group.create`, `user_group.edit`, `user_group.deactivate`;
- `space.create`, `space.view_all`, `space.edit_all`, `space.view`, `space.comment`, `space.edit`;
- `rule.view`, `rule.create`, `rule.edit`, `rule.delete`;
- `character.create`, `character.view`;
- `keyword.view`, `keyword.create`, `keyword.edit`, `keyword.delete`;
- `notification_template.view`, `notification_template.create`, `notification_template.edit`, `notification_template.delete`;
- `game.create`, `game.view_all`, `game.edit_all`, `game.edit`, `game.moderate`, `game.manage`, `game.edit_inventory`;
- `chat.create`, `chat.message`, `chat.delete`.

Точная backend-матрица effective permissions — `OPEN`. Frontend-реестр permission categories подтверждается `Core/User` и Rule/Notifications.

### Spaces, rules and revisions

```sql
spaces(
  id,
  code VARCHAR UNIQUE NOT NULL,
  name VARCHAR NOT NULL,
  description TEXT,
  owner_id → users.id NOT NULL,
  revision INT DEFAULT 0 NOT NULL,
  active BOOL DEFAULT true NOT NULL,
  created_at
)
INDEX (owner_id), (code)

space_moderators(
  space_id → spaces.id NOT NULL,
  user_id → users.id NOT NULL,
  PRIMARY KEY (space_id, user_id)
)

space_permissions(
  space_id → spaces.id NOT NULL,
  assignee_type VARCHAR NOT NULL,
  assignee_id INT NOT NULL,
  permission_key VARCHAR NOT NULL,
  UNIQUE (space_id, assignee_type, assignee_id, permission_key)
)
INDEX (space_id)

space_revisions(
  id,
  space_id → spaces.id NOT NULL,
  revision INT NOT NULL,
  published_at TIMESTAMP NOT NULL,
  changed_count INT DEFAULT 0,
  UNIQUE (space_id, revision)
)
INDEX (space_id, revision)

-- HISTORICAL: таблица tags() в legacy SQL совпадает по полям со справочником признаков.
-- Каноническое имя — keywords (DEC-017, DEC-022). tags не current contract.

keywords(
  id,
  code VARCHAR UNIQUE NOT NULL,
  name VARCHAR NOT NULL,
  description TEXT,
  active BOOL DEFAULT true NOT NULL
)

rules(
  rule_id UUID PRIMARY KEY,
  code VARCHAR UNIQUE NOT NULL,
  type VARCHAR NOT NULL
)

mechanics(
  id,
  code VARCHAR UNIQUE NOT NULL,
  name VARCHAR NOT NULL,
  description TEXT,
  version VARCHAR NOT NULL,
  created_at
)
INDEX (code, version)

rule_versions(
  id,
  rule_id → rules.rule_id NOT NULL,
  space_id → spaces.id NOT NULL,
  created_at TIMESTAMP NOT NULL,
  name VARCHAR NOT NULL,
  description_html TEXT,
  spec_json JSON,
  mechanic_id → mechanics.id NULL,
  active BOOL DEFAULT true NOT NULL
)
INDEX (space_id, rule_id, created_at DESC)
INDEX (rule_id), (space_id), (mechanic_id)

rule_keywords(
  rule_version_id → rule_versions.id NOT NULL,
  keyword_id → keywords.id NOT NULL,
  PRIMARY KEY (rule_version_id, keyword_id)
)
```

Для среза правил выбирается последняя версия конкретного `rule_id` в `space_id`, чей timestamp не превышает `publishedAt`. Удаление не переписывает старую версию: создаётся marker-version с `active=false`. Этот SQL-механизм — legacy-backed storage requirement; канонический внешний идентификатор публикации — `(spaceId, revision)` с immutable `publishedAt`.

Текстовая схема использует timestamp-версии из legacy-ТР. Каноническая внешняя ревизия — numeric `(spaceId, revision)` с immutable `publishedAt` (`DEC-006`); timestamp-схема не заменяет это решение.

### Rule Sets и языковой контент

```sql
rule_sets(id, name VARCHAR NOT NULL, slug VARCHAR UNIQUE NOT NULL, description TEXT)
rule_set_dependencies(
  rule_set_id → rule_sets.id NOT NULL,
  depends_on_set_id → rule_sets.id NOT NULL,
  PRIMARY KEY (rule_set_id, depends_on_set_id)
)
rule_set_rules(
  rule_id → rules.rule_id NOT NULL,
  rule_set_id → rule_sets.id NOT NULL,
  PRIMARY KEY (rule_id, rule_set_id)
)
game_rule_sets(
  game_id → games.id NOT NULL,
  rule_set_id → rule_sets.id NOT NULL,
  PRIMARY KEY (game_id, rule_set_id)
)

nations(id, name VARCHAR NOT NULL, description TEXT, short_description VARCHAR, keywords JSON)
nation_races(
  nation_id → nations.id NOT NULL,
  race_id → rules.rule_id NOT NULL,
  PRIMARY KEY (nation_id, race_id)
)
languages(id, name VARCHAR NOT NULL, description TEXT, language_group VARCHAR)
similar_languages(
  language_id → languages.id NOT NULL,
  similar_to_id → languages.id NOT NULL,
  PRIMARY KEY (language_id, similar_to_id)
)
nation_languages(
  nation_id → nations.id NOT NULL,
  language_id → languages.id NOT NULL,
  PRIMARY KEY (nation_id, language_id)
)
writing_systems(id, name VARCHAR NOT NULL, type VARCHAR NOT NULL)
language_writing_systems(
  language_id → languages.id NOT NULL,
  writing_system_id → writing_systems.id NOT NULL,
  PRIMARY KEY (language_id, writing_system_id)
)
```

Связь Rule Sets с текущим каталогом и ревизионным срезом — `OPEN`.

### Games

```sql
games(
  id,
  name VARCHAR NOT NULL,
  description TEXT,
  short_description VARCHAR,
  owner_id → users.id NOT NULL,
  space_id → spaces.id NOT NULL,
  rules_version_at TIMESTAMP NOT NULL,
  status VARCHAR DEFAULT 'draft' NOT NULL,
  visibility VARCHAR DEFAULT 'all' NOT NULL,
  join_policy VARCHAR DEFAULT 'anyone' NOT NULL,
  image_file_id → files.id NULL,
  os_points_limit INT NULL,
  ol_points_limit INT NULL,
  or_points_limit INT NULL,
  money_limit INT NULL,
  tags_json JSON,
  spec_json JSON,
  active BOOL DEFAULT true NOT NULL,
  created_at
)
INDEX (owner_id), (space_id), (status)

game_members(
  game_id → games.id NOT NULL,
  user_id → users.id NOT NULL,
  role VARCHAR DEFAULT 'player' NOT NULL,
  PRIMARY KEY (game_id, user_id)
)
INDEX (user_id)

game_member_permissions(
  game_id → games.id NOT NULL,
  user_id → users.id NOT NULL,
  permission_key VARCHAR NOT NULL,
  PRIMARY KEY (game_id, user_id, permission_key)
)

game_invitations(
  id,
  game_id → games.id NOT NULL,
  inviter_id → users.id NOT NULL,
  invitee_id → users.id NOT NULL,
  status VARCHAR NOT NULL,
  created_at
)
INDEX (invitee_id, status), (game_id, status)

game_loot(
  id,
  game_id → games.id NOT NULL,
  item_rule_id → rules.rule_id NOT NULL,
  quantity INT NOT NULL,
  notes TEXT,
  status VARCHAR DEFAULT 'available' NOT NULL,
  created_at
)
INDEX (game_id, status)

game_personal_notes(
  game_id → games.id NOT NULL,
  user_id → users.id NOT NULL,
  notes TEXT NOT NULL,
  PRIMARY KEY (game_id, user_id)
)

game_loot_interest(
  loot_id → game_loot.id NOT NULL,
  user_id → users.id NOT NULL,
  created_at,
  PRIMARY KEY (loot_id, user_id)
)
```

`games.status`, `visibility` и `join_policy` независимы. Invitation statuses: `sent`, `viewed`, `accepted`, `declined`; loot statuses: `prepared`, `available`, `distributed`. Интерес игрока к loot хранится отдельно в `game_loot_interest`. `game_personal_notes` принадлежат конкретной паре `(game_id, user_id)` и не являются общими заметками игры.

Внешняя модель правил игры — `spaceId + revision`; legacy `games.rules_version_at` сохраняется здесь как `REQUIREMENT/HISTORICAL`, пока backend-контракт не подтверждён.

### Characters и inventory

Целевой backend-контракт хранит одно актуальное состояние персонажа (`actualCharacter`) и не создаёт историю CharacterVersion. `CharacterVersion` остаётся формой полного листа/snapshot в frontend и membership, но не означает таблицу исторических версий персонажа. Точная физическая схема хранения — `CODE_GAP / implementation OPEN`.

```sql
characters(
  id,
  name VARCHAR NOT NULL,
  owner_id → users.id NOT NULL,
  space_id → spaces.id NOT NULL,
  rules_version_at TIMESTAMP NOT NULL,
  status VARCHAR DEFAULT 'draft' NOT NULL, -- UI/legacy label; не moderation lifecycle
  state_json JSON NULL,
  heir_of → characters.id NULL,
  owner_notes TEXT NULL, -- личные заметки владельца; не входит в character_versions
  active BOOL DEFAULT true NOT NULL,
  created_at
)
INDEX (owner_id), (status)

character_versions( -- target: snapshot payload, не history персонажа
  id,
  character_id → characters.id NOT NULL,
  created_at TIMESTAMP NOT NULL,
  race_rule_id → rules.rule_id NOT NULL,
  draft_of → character_versions.id NULL,
  data_json JSON NOT NULL,
  validated BOOL DEFAULT false NOT NULL
)
INDEX (character_id, created_at DESC), (draft_of)

character_native_language(
  character_version_id → character_versions.id PRIMARY KEY,
  language_id → languages.id NOT NULL
)

character_inventory(
  id,
  character_version_id → character_versions.id NOT NULL,
  item_rule_id → rules.rule_id NOT NULL,
  quantity INT NOT NULL,
  durability_left INT NULL,
  equipped BOOL DEFAULT false NOT NULL
)
INDEX (character_version_id)
```

Старая `game_characters` JSON-модель, `character_moderation`, timestamp storage и A/L/O/P не являются текущей целевой моделью membership; они сохранены в `history.md`. Целевой membership хранит `characterId`, статус `submitted | active | left`, immutable `approvedCharacterVersion`, `gameOverlay` и review metadata; физическая backend-схема — `CODE_GAP / implementation OPEN`.

### Notifications, Chat и chronicle

```sql
notification_templates(
  id,
  key VARCHAR UNIQUE NOT NULL,
  title_template VARCHAR NOT NULL,
  body_template TEXT NOT NULL,
  buttons_json JSON,
  active BOOL DEFAULT true NOT NULL
)

notifications(
  id,
  from_user_id → users.id NULL,
  to_user_id → users.id NOT NULL,
  template_key → notification_templates.key NOT NULL,
  data_json JSON NOT NULL,
  read BOOL DEFAULT false NOT NULL,
  read_at TIMESTAMP NULL,
  created_at
)
INDEX (to_user_id, read, created_at DESC), (to_user_id, read)

chats(
  id,
  type VARCHAR NOT NULL,
  game_id → games.id NULL,
  name VARCHAR NULL,
  created_at,
  updated_at
)

chat_members(
  chat_id → chats.id NOT NULL,
  user_id → users.id NOT NULL,
  joined_at,
  last_read_message_id INT NULL,
  PRIMARY KEY (chat_id, user_id)
)
INDEX (user_id)

chat_messages(
  id,
  chat_id → chats.id NOT NULL,
  user_id → users.id NOT NULL,
  content TEXT NOT NULL,
  dice_result JSON NULL,
  created_at,
  updated_at TIMESTAMP
)
INDEX (chat_id, created_at DESC), (chat_id, updated_at)

user_macros(
  id,
  user_id → users.id NOT NULL,
  name VARCHAR NOT NULL,
  text_template TEXT NOT NULL DEFAULT '',
  created_at
)
INDEX (user_id)

user_macro_rolls(
  id,
  macro_id → user_macros.id NOT NULL,
  position INT NOT NULL,
  roll_formula VARCHAR NOT NULL,
  efficiency INT NOT NULL DEFAULT 3,
  adv INT NOT NULL DEFAULT 0,
  die_size INT NOT NULL DEFAULT 0,
  roll_label VARCHAR NULL,
  variable_adv BOOLEAN NOT NULL DEFAULT false
)
INDEX (macro_id, position)

chronicles(
  id,
  name VARCHAR,
  game_id → games.id NULL,
  character_id → characters.id NULL,
  region_id INT NULL,
  created_at
)

chronicle_entries(
  id,
  chronicle_id → chronicles.id NOT NULL,
  title VARCHAR NOT NULL,
  content TEXT NOT NULL,
  event_time VARCHAR NOT NULL,
  sort_order INT NOT NULL,
  created_by → users.id NOT NULL,
  created_at
)
INDEX (chronicle_id, sort_order)
```

Для `chronicles` должен быть ровно один owner FK из `game_id`, `character_id`, `region_id`; это проверяется SQL CHECK либо application invariant. `chat_members.last_read_message_id` определяет unread как видимые сообщения с большим id, исключая собственные сообщения. `chat_messages.updated_at` нужен для sync по изменениям visibility/content.

## Current domain storage semantics

Current frontend/domain state is represented by DTOs, game overlays, typed operations and visibility rules in the canonical domain documents. These are current semantics, not a claim that the legacy SQL tables already exist.

### Mapping старой membership-схемы

Legacy `game_characters.active_json`, `pending_json` и `draft_json` не являются текущим трёхслойным контрактом. Их disposition:

- `active_json` → immutable `approvedCharacterVersion` membership snapshot;
- `pending_json` → не отдельное хранимое состояние; moderation определяется diff approved snapshot и `actualCharacter`;
- `draft_json` → browser draft или session `gameOverlay` в зависимости от контекста;
- `latestVersion` не является частью целевой модели; актуальным состоянием является `actualCharacter`;
- approve/reject, session commit и optimistic version checks описаны в `character-system.md`, а backend persistence остаётся `OPEN`.

Legacy `character_moderation` как отдельная таблица также не является владельцем moderation state: moderation принадлежит membership Game и его version/concurrency contract.

`dice_result` — legacy field; текущая модель сообщения использует `ChatAttachment[]`. `event_time`/`sort_order` — legacy storage; frontend использует `GameTime`. SSE, unread, visibility, notification generation и ownership constraints требуют backend-подтверждения (`OPEN`).

Логические сущности battleground (`GameScene`, occupancy blob, openings) — [`battleground-system.md`](battleground-system.md). Таблиц сцены в legacy SQL нет. Candidate для подложек — существующий `files()`; этого недостаточно как схемы сцены (`OPEN`).
