<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Setup;

use Mifrial\Core\Kernel\Interface\Service\ISetupStep;
use Mifrial\Core\Mail\Repository\MailEventRepository;
use Mifrial\Core\Mail\Repository\MailTemplateRepository;

/**
 * Событие и шаблон forgot. Код — соглашение с Auth.
 */
final class EnsureAuthPasswordResetMailStep implements ISetupStep
{
    private const EVENT_CODE = 'auth.password_reset';

    private const TEMPLATE_NAME = 'default';

    /**
     * Создаёт шаг.
     *
     * @param MailEventRepository $eventRepository События.
     * @param MailTemplateRepository $templateRepository Шаблоны.
     *
     * @return void
     */
    public function __construct(
        private readonly MailEventRepository $eventRepository,
        private readonly MailTemplateRepository $templateRepository,
    ) {
    }

    /**
     * Возвращает id шага.
     *
     * @return string Ключ шага.
     */
    public function getId(): string
    {
        return 'Core/Mail:seed.auth-password-reset';
    }

    /**
     * Пишет событие и шаблон, если их нет.
     *
     * @return void
     */
    public function run(): void
    {
        $eventId = $this->ensureEvent();
        if ($this->templateRepository->findByEventIdAndName($eventId, self::TEMPLATE_NAME) !== null) {
            return;
        }

        $this->templateRepository->add($this->templateValues($eventId));
    }

    /**
     * Id события.
     *
     * @return int Id.
     */
    private function ensureEvent(): int
    {
        $eventRow = $this->eventRepository->findByCode(self::EVENT_CODE);
        if ($eventRow !== null) {
            return (int) $eventRow['id'];
        }

        return $this->eventRepository->add(self::EVENT_CODE, 'Сброс пароля');
    }

    /**
     * Поля шаблона по умолчанию.
     *
     * @param int $eventId Событие.
     *
     * @return array<string, mixed> Значения.
     */
    private function templateValues(int $eventId): array
    {
        return [
            'event_id' => $eventId,
            'name' => self::TEMPLATE_NAME,
            'email_from' => 'noreply@localhost',
            'email_to' => '{{email}}',
            'subject' => 'Сброс пароля {{login}}',
            'body' => "Логин: {{login}}\nТокен: {{token}}\n"
                . '/reset-password?login={{login}}&token={{token}}',
            'active' => true,
        ];
    }
}
