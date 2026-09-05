<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Keyword\Tests;

use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Roleplay\Keyword\Exception\KeywordInvalidException;
use Mifrial\Roleplay\Keyword\Exception\KeywordNotFoundException;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Keyword\Schema\KeywordSchema;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;
use PHPUnit\Framework\TestCase;

final class KeywordMysqlTest extends TestCase
{
    private ?IKeywords $keywords = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    /**
     * Подключается к MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectKeyword();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Keyword tests');
        }

        $this->dropKeywordTable();
        $this->installSchema();
    }

    /**
     * Снимает таблицу.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropKeywordTable();
    }

    /**
     * add и чтение по id и code.
     *
     * @return void
     */
    public function testAddGetAndGetByCode(): void
    {
        $keywords = $this->keywords();
        $keywordId = $keywords->add('  melee  ', '  Ближний  ', '', true);
        $byId = $keywords->get($keywordId);
        self::assertSame($keywordId, $byId->getId());
        self::assertSame('melee', $byId->getCode());
        self::assertSame('Ближний', $byId->getName());
        self::assertSame('', $byId->getDescription());
        self::assertTrue($byId->isActive());
        $byCode = $keywords->getByCode(' melee ');
        self::assertSame($keywordId, $byCode->getId());
    }

    /**
     * Список, update, deactivate.
     *
     * @return void
     */
    public function testGetListUpdateAndDeactivate(): void
    {
        $keywords = $this->keywords();
        $firstId = $keywords->add('alpha', 'Alpha', 'a');
        $secondId = $keywords->add('beta', 'Beta', '');
        $list = $keywords->getList();
        self::assertCount(2, $list);
        self::assertSame($firstId, $list[0]->getId());
        self::assertSame($secondId, $list[1]->getId());

        $keywords->update($firstId, '  Alpha 2  ', '  note  ');
        $updated = $keywords->get($firstId);
        self::assertSame('Alpha 2', $updated->getName());
        self::assertSame('note', $updated->getDescription());
        self::assertSame('alpha', $updated->getCode());

        try {
            $keywords->update($firstId, '   ', 'x');
            self::fail('empty name must fail');
        } catch (KeywordInvalidException $exception) {
            self::assertSame('KEYWORD_INVALID', $exception->getErrorCode());
        }

        $keywords->deactivate($firstId);
        self::assertFalse($keywords->get($firstId)->isActive());
        $keywords->deactivate($firstId);
        self::assertFalse($keywords->get($firstId)->isActive());
        try {
            $keywords->update(999, 'Name', '');
            self::fail('missing update must fail');
        } catch (KeywordNotFoundException $exception) {
            self::assertSame('KEYWORD_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $keywords->deactivate(999);
            self::fail('missing deactivate must fail');
        } catch (KeywordNotFoundException $exception) {
            self::assertSame('KEYWORD_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * Дубль code — KEYWORD_INVALID.
     *
     * @return void
     */
    public function testDuplicateCodeIsInvalid(): void
    {
        $keywords = $this->keywords();
        $keywords->add('melee', 'A');
        try {
            $keywords->add('melee', 'B');
            self::fail('duplicate code must fail');
        } catch (KeywordInvalidException $exception) {
            self::assertSame('KEYWORD_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Пустой code или name после trim.
     *
     * @return void
     */
    public function testEmptyCodeOrNameIsInvalid(): void
    {
        $keywords = $this->keywords();
        try {
            $keywords->add('   ', 'Name');
            self::fail('empty code must fail');
        } catch (KeywordInvalidException $exception) {
            self::assertSame('KEYWORD_INVALID', $exception->getErrorCode());
        }

        try {
            $keywords->add('code', '  ');
            self::fail('empty name must fail');
        } catch (KeywordInvalidException $exception) {
            self::assertSame('KEYWORD_INVALID', $exception->getErrorCode());
        }

        try {
            $keywords->getByCode('  ');
            self::fail('empty getByCode must fail');
        } catch (KeywordInvalidException $exception) {
            self::assertSame('KEYWORD_INVALID', $exception->getErrorCode());
        }
    }

    /**
     * Нет строки — KEYWORD_NOT_FOUND.
     *
     * @return void
     */
    public function testMissingRowIsNotFound(): void
    {
        $keywords = $this->keywords();
        try {
            $keywords->get(1);
            self::fail('missing id must fail');
        } catch (KeywordNotFoundException $exception) {
            self::assertSame('KEYWORD_NOT_FOUND', $exception->getErrorCode());
        }

        try {
            $keywords->getByCode('missing');
            self::fail('missing code must fail');
        } catch (KeywordNotFoundException $exception) {
            self::assertSame('KEYWORD_NOT_FOUND', $exception->getErrorCode());
        }
    }

    /**
     * boot() и ленивый get.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectKeyword(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $keywordContainer = $application->getLocator()->get(IKeywordContainer::class);
        $keywords = $keywordContainer->get(IKeywords::class);
        self::assertInstanceOf(IKeywords::class, $keywords);
        $this->keywords = $keywords;
        $smartTableContainer = $application->getLocator()->get(ISmartTableContainer::class);
        $smartTableGateway = $smartTableContainer->get(ISmartTableGateway::class);
        self::assertInstanceOf(ISmartTableGateway::class, $smartTableGateway);
        $this->smartTableGateway = $smartTableGateway;
        $databaseConnection = $smartTableContainer->get(IDatabaseConnection::class);
        if (!$databaseConnection instanceof IlluminateDatabaseConnection) {
            self::markTestSkipped('test connection is not Illuminate');
        }

        $databaseConnection->ping();
    }

    /**
     * Ставит таблицу keyword.
     *
     * @return void
     */
    private function installSchema(): void
    {
        $schema = $this->smartTableGateway()->open(KeywordTable::class)->schema();
        (new KeywordSchema($schema))->install();
    }

    /**
     * Сносит keyword, если есть.
     *
     * @return void
     */
    private function dropKeywordTable(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $schema = $this->smartTableGateway->open(KeywordTable::class)->schema();
        if ($schema->exists()) {
            $schema->deleteTable();
        }
    }

    /**
     * Фасад после setUp.
     *
     * @return IKeywords Фасад.
     */
    private function keywords(): IKeywords
    {
        self::assertInstanceOf(IKeywords::class, $this->keywords);

        return $this->keywords;
    }

    /**
     * Шлюз после setUp.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }
}
