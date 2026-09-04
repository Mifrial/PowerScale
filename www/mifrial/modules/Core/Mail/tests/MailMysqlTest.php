<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Tests;

use Mifrial\Core\Agent\Repository\AgentRepository;
use Mifrial\Core\Agent\Schema\AgentSchema;
use Mifrial\Core\Agent\Service\AgentService;
use Mifrial\Core\Agent\Table\AgentTable;
use Mifrial\Core\Kernel\Dto\DatabaseSettings;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\Mail\Dto\MailSettings;
use Mifrial\Core\Mail\Exception\MailException;
use Mifrial\Core\Mail\Repository\MailEventRepository;
use Mifrial\Core\Mail\Repository\MailJobRepository;
use Mifrial\Core\Mail\Repository\MailTemplateRepository;
use Mifrial\Core\Mail\Schema\MailSchema;
use Mifrial\Core\Mail\Service\MailFlushHandler;
use Mifrial\Core\Mail\Service\MailFlushService;
use Mifrial\Core\Mail\Service\MailService;
use Mifrial\Core\Mail\Service\PlaceholderRenderer;
use Mifrial\Core\Mail\Setup\EnsureAuthPasswordResetMailStep;
use Mifrial\Core\Mail\Table\MailEventTable;
use Mifrial\Core\Mail\Table\MailJobTable;
use Mifrial\Core\Mail\Table\MailTemplateTable;
use Mifrial\Core\SmartTable\Dto\ListQuery;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Exception\Database\DbConfigInvalidException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateConnectionFactory;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\SmartTable\Tests\GatewayHarness;
use PHPUnit\Framework\TestCase;

final class MailMysqlTest extends TestCase
{
    private ?ISmartTableGateway $smartTableGateway = null;

    private ?MailEventRepository $eventRepository = null;

    private ?MailTemplateRepository $templateRepository = null;

    private ?MailJobRepository $jobRepository = null;

    private ?RecordingMailTransport $mailTransport = null;

    private ?MailFlushService $mailFlushService = null;

    private ?MailService $mailService = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectMail();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Mail tests');
        }

        $this->dropMailTables();
        $this->installMail();
    }

    /**
     * Сносит таблицы.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropMailTables();
    }

    /**
     * Неизвестный код и битый payload.
     *
     * @return void
     */
    public function testTriggerRejectsUnknownAndNestedPayload(): void
    {
        try {
            $this->mailService()->trigger('missing.event', []);
            self::fail('unknown');
        } catch (MailException $exception) {
            self::assertSame('MAIL_INVALID', $exception->getErrorCode());
        }

        $this->eventRepository()->add('demo.ping', 'Ping');
        try {
            $this->mailService()->trigger('demo.ping', ['user' => ['id' => 1]]);
            self::fail('nested');
        } catch (MailException $exception) {
            self::assertSame('MAIL_INVALID', $exception->getErrorCode());
        }

        self::assertSame([], $this->jobRepository()->listPending(0));
    }

    /**
     * trigger пишет pending; flush шлёт и ставит sent.
     *
     * @return void
     */
    public function testTriggerThenFlushSends(): void
    {
        $eventId = $this->seedPingTemplate();
        $this->mailService()->trigger('demo.ping', ['name' => 'Ann', 'to' => 'a@example.test']);
        $pending = $this->jobRepository()->listPending(0);
        self::assertCount(1, $pending);
        self::assertSame($eventId, (int) $pending[0]['event_id']);
        $this->mailFlushService()->flush();
        self::assertSame([], $this->jobRepository()->listPending(0));
        $jobRow = $this->jobRepository()->getById((int) $pending[0]['id']);
        self::assertIsArray($jobRow);
        self::assertSame('sent', $jobRow['status']);
        self::assertSame('Hello Ann', $this->mailTransport()->sent[0]['subject']);
        self::assertSame('a@example.test', $this->mailTransport()->sent[0]['to']);
    }

    /**
     * Нет шаблонов / битый from / частичный send → failed.
     *
     * @return void
     */
    public function testFlushFailuresAreTerminal(): void
    {
        $this->eventRepository()->add('demo.ping', 'Ping');
        $this->mailService()->trigger('demo.ping', []);
        $this->mailFlushService()->flush();
        self::assertSame('failed', $this->latestJob()['status']);
        $this->templateRepository()->add($this->templateValues(
            (int) $this->eventRepository()->findByCode('demo.ping')['id'],
            '{{to}}',
        ));
        $this->mailService()->trigger('demo.ping', ['to' => "a@x.test\nb@y.test"]);
        $this->mailFlushService()->flush();
        $crlfJob = $this->latestJob();
        self::assertSame('failed', $crlfJob['status']);
        $this->mailService()->trigger('demo.ping', ['name' => 'Ann', 'to' => 'a@example.test']);
        $this->mailTransport()->failNext = true;
        $this->mailFlushService()->flush();
        $failedJob = $this->latestJob();
        self::assertSame('failed', $failedJob['status']);
        $this->mailFlushService()->flush();
        self::assertSame('failed', $this->jobRepository()->getById((int) $failedJob['id'])['status']);
    }

    /**
     * Два шаблона: первый ушёл, второй нет — job failed, письмо не отзываем.
     *
     * @return void
     */
    public function testPartialTemplateSendFailsJob(): void
    {
        $eventId = $this->seedPingTemplate();
        $second = $this->templateValues($eventId, '{{to}}');
        $second['name'] = 'Ping copy';
        $this->templateRepository()->add($second);
        $this->mailService()->trigger('demo.ping', ['name' => 'Ann', 'to' => 'a@example.test']);
        $this->mailTransport()->failOnCall = 2;
        $this->mailFlushService()->flush();
        self::assertCount(1, $this->mailTransport()->sent);
        self::assertSame('failed', $this->latestJob()['status']);
        self::assertSame([], $this->jobRepository()->listPending(0));
    }

    /**
     * inline flushJob после trigger.
     *
     * @return void
     */
    public function testFlushInline(): void
    {
        $this->seedPingTemplate();
        $inlineMail = new MailService(
            $this->eventRepository(),
            $this->jobRepository(),
            MailSettings::fromSection(['flush_inline' => true]),
            $this->mailFlushService(),
        );
        $inlineMail->trigger('demo.ping', ['name' => 'Ann', 'to' => 'a@example.test']);
        self::assertSame([], $this->jobRepository()->listPending(0));
        self::assertCount(1, $this->mailTransport()->sent);
    }

    /**
     * Тик Agent вызывает MailFlushHandler.
     *
     * @return void
     */
    public function testAgentTickFlushesMail(): void
    {
        $this->dropAgentTable();
        (new AgentSchema($this->smartTableGateway()->open(AgentTable::class)->schema()))->install();
        $this->seedPingTemplate();
        $this->mailService()->trigger('demo.ping', ['name' => 'Ann', 'to' => 'a@example.test']);
        $agentService = new AgentService(new AgentRepository(
            $this->smartTableGateway()->open(AgentTable::class)->records(),
        ));
        $agentService->ensureAgent('mail.flush', 60);
        $agentService->bindHandler('mail.flush', new MailFlushHandler($this->mailFlushService()));
        $agentService->tick();
        self::assertCount(1, $this->mailTransport()->sent);
        $this->dropAgentTable();
    }

    /**
     * Seed forgot: идемпотентен; trigger+flush шлёт на email.
     *
     * @return void
     */
    public function testAuthPasswordResetSeedAndFlush(): void
    {
        $seedStep = new EnsureAuthPasswordResetMailStep(
            $this->eventRepository(),
            $this->templateRepository(),
        );
        self::assertSame('Core/Mail:seed.auth-password-reset', $seedStep->getId());
        $seedStep->run();
        $seedStep->run();
        $eventRow = $this->eventRepository()->findByCode('auth.password_reset');
        self::assertIsArray($eventRow);
        self::assertCount(
            1,
            $this->templateRepository()->listActiveByEventId((int) $eventRow['id']),
        );
        $this->mailService()->trigger('auth.password_reset', [
            'login' => 'alice',
            'token' => 'raw-token',
            'email' => 'alice@x.test',
        ]);
        $this->mailFlushService()->flush();
        self::assertSame('alice@x.test', $this->mailTransport()->sent[0]['to']);
        self::assertStringContainsString('raw-token', $this->mailTransport()->sent[0]['body']);
        self::assertSame('sent', $this->latestJob()['status']);
    }

    /**
     * Подключение MySQL.
     *
     * @return void
     */
    private function connectMail(): void
    {
        $envHost = getenv('MIFRIAL_TEST_DB_HOST');
        if (is_string($envHost) && $envHost !== '') {
            $databaseConnection = new IlluminateDatabaseConnection(
                new IlluminateConnectionFactory(),
                $this->settingsFromEnv($envHost),
            );
            $databaseConnection->ping();
            $this->bindOnGateway(GatewayHarness::make($databaseConnection));

            return;
        }

        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            throw new DbConfigInvalidException('test connection is not Illuminate');
        }

        $databaseConnection->ping();
        $this->bindOnGateway($smartTableGateway);
    }

    /**
     * Сервисы на шлюзе.
     *
     * @param ISmartTableGateway $smartTableGateway Шлюз.
     *
     * @return void
     */
    private function bindOnGateway(ISmartTableGateway $smartTableGateway): void
    {
        $this->smartTableGateway = $smartTableGateway;
        $this->eventRepository = new MailEventRepository(
            $smartTableGateway->open(MailEventTable::class)->records(),
        );
        $this->templateRepository = new MailTemplateRepository(
            $smartTableGateway->open(MailTemplateTable::class)->records(),
        );
        $this->jobRepository = new MailJobRepository($smartTableGateway->open(MailJobTable::class)->records());
        $this->mailTransport = new RecordingMailTransport();
        $this->mailFlushService = new MailFlushService(
            $this->jobRepository,
            $this->templateRepository,
            new PlaceholderRenderer(),
            $this->mailTransport,
        );
        $this->mailService = new MailService(
            $this->eventRepository,
            $this->jobRepository,
            MailSettings::fromSection(null),
            $this->mailFlushService,
        );
    }

    /**
     * DDL Mail.
     *
     * @return void
     */
    private function installMail(): void
    {
        (new MailSchema(
            $this->smartTableGateway()->open(MailEventTable::class)->schema(),
            $this->smartTableGateway()->open(MailTemplateTable::class)->schema(),
            $this->smartTableGateway()->open(MailJobTable::class)->schema(),
        ))->install();
    }

    /**
     * Событие и шаблон ping.
     *
     * @return int event id.
     */
    private function seedPingTemplate(): int
    {
        $eventId = $this->eventRepository()->add('demo.ping', 'Ping');
        $this->templateRepository()->add($this->templateValues($eventId, '{{to}}'));

        return $eventId;
    }

    /**
     * Поля шаблона.
     *
     * @param int $eventId Событие.
     * @param string $emailTo Кому.
     *
     * @return array<string, mixed> Значения.
     */
    private function templateValues(int $eventId, string $emailTo): array
    {
        return [
            'event_id' => $eventId,
            'name' => 'Ping',
            'email_from' => 'mail@example.test',
            'email_to' => $emailTo,
            'subject' => 'Hello {{name}}',
            'body' => 'Hi {{name}}',
            'active' => true,
        ];
    }

    /**
     * Последний job по id.
     *
     * @return array<string, mixed> Строка.
     */
    private function latestJob(): array
    {
        $gateway = $this->smartTableGateway();
        $rows = $gateway->open(MailJobTable::class)->records()->getList(
            ListQuery::fromOptions([
                'sort' => ['id' => 'desc'],
                'limit' => 1,
            ]),
        )->rows();
        self::assertNotSame([], $rows);

        return $rows[0];
    }

    /**
     * Drop Mail.
     *
     * @return void
     */
    private function dropMailTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        foreach ([MailJobTable::class, MailTemplateTable::class, MailEventTable::class] as $tableClass) {
            $openedSchema = $this->smartTableGateway->open($tableClass)->schema();
            if ($openedSchema->exists()) {
                $openedSchema->deleteTable();
            }
        }

        $this->dropAgentTable();
    }

    /**
     * Drop Agent.
     *
     * @return void
     */
    private function dropAgentTable(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $openedSchema = $this->smartTableGateway->open(AgentTable::class)->schema();
        if ($openedSchema->exists()) {
            $openedSchema->deleteTable();
        }
    }

    /**
     * @return MailEventRepository Репозиторий.
     */
    private function eventRepository(): MailEventRepository
    {
        self::assertInstanceOf(MailEventRepository::class, $this->eventRepository);

        return $this->eventRepository;
    }

    /**
     * @return MailTemplateRepository Репозиторий.
     */
    private function templateRepository(): MailTemplateRepository
    {
        self::assertInstanceOf(MailTemplateRepository::class, $this->templateRepository);

        return $this->templateRepository;
    }

    /**
     * @return MailJobRepository Репозиторий.
     */
    private function jobRepository(): MailJobRepository
    {
        self::assertInstanceOf(MailJobRepository::class, $this->jobRepository);

        return $this->jobRepository;
    }

    /**
     * @return RecordingMailTransport Транспорт.
     */
    private function mailTransport(): RecordingMailTransport
    {
        self::assertInstanceOf(RecordingMailTransport::class, $this->mailTransport);

        return $this->mailTransport;
    }

    /**
     * @return MailFlushService Flush.
     */
    private function mailFlushService(): MailFlushService
    {
        self::assertInstanceOf(MailFlushService::class, $this->mailFlushService);

        return $this->mailFlushService;
    }

    /**
     * @return MailService Фасад.
     */
    private function mailService(): MailService
    {
        self::assertInstanceOf(MailService::class, $this->mailService);

        return $this->mailService;
    }

    /**
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }

    /**
     * Настройки из env.
     *
     * @param string $host Хост.
     *
     * @return DatabaseSettings Настройки.
     */
    private function settingsFromEnv(string $host): DatabaseSettings
    {
        $port = getenv('MIFRIAL_TEST_DB_PORT');
        $collation = getenv('MIFRIAL_TEST_DB_COLLATION');
        $timezone = getenv('MIFRIAL_TEST_DB_TIMEZONE');

        return DatabaseSettings::fromFields(
            $host,
            is_string($port) && ctype_digit($port) ? (int) $port : 3306,
            (string) getenv('MIFRIAL_TEST_DB_DATABASE'),
            (string) getenv('MIFRIAL_TEST_DB_USERNAME'),
            (string) getenv('MIFRIAL_TEST_DB_PASSWORD'),
            (string) getenv('MIFRIAL_TEST_DB_CHARSET'),
            false,
            is_string($collation) && $collation !== '' ? $collation : 'utf8mb4_unicode_ci',
            is_string($timezone) && $timezone !== '' ? $timezone : '+00:00',
        );
    }
}
