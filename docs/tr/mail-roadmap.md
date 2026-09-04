# Нарезка Core/Mail и Core/Agent

**Статус:** план, 2026-09-03. Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Setup CLI — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). Конвейер — [`architecture.md`](architecture.md). Forgot без SMTP — [`auth-plan-02-password-flow.md`](auth-plan-02-password-flow.md).

Порядок сверху вниз. Почта не стартует раньше Agent 1 (очередь крутит агент). Auth 3 не стартует раньше Mail 1 (notifier зовёт фасад Mail).

## 0. Сделано

- Auth 2: `IPasswordResetNotifier` = лог; флаг `expose_reset_token`.
- CLI setup модулей (`bin/setup.php`). HTTP-агентов и cron в коде нет.

## 1. Agent — тик — сделано

Подробно: [`agent-plan-01-tick.md`](agent-plan-01-tick.md). Таблица агентов, регистрация обработчика из модуля-донора, CLI `bin/agent.php`. Не SMTP, не шаблоны.

## 2. Mail — события, шаблоны, очередь — сделано

Подробно: [`mail-plan-01-queue.md`](mail-plan-01-queue.md). Три карты ST, фасад `trigger`, подстановка `{{…}}`, flush очереди, агент `mail.flush`. Не Auth, не админка писем.

## 3. Auth — почта сброса — сделано

Подробно: [`auth-plan-03-mail-reset.md`](auth-plan-03-mail-reset.md). `MailPasswordResetNotifier` + seed `auth.password_reset` шагом **Mail**. Не SMTP, не rate limit, не Vue.

## Параллелить нельзя

- Mail с отсутствием Agent 1 (нет куда регистрировать тик). Inline-flush в том же запросе — режим Mail, не замена Agent.
- PHP `eval` в строке агента (как Bitrix `b_agent.NAME`).
- SMTP внутри Auth.
