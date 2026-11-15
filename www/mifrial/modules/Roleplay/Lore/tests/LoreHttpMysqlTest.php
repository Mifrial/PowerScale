<?php

declare(strict_types=1);

namespace Mifrial\Roleplay\Lore\Tests;

use Mifrial\Core\Kernel\Dto\RequestActor;
use Mifrial\Core\Kernel\Interface\Container\IKernelContainer;
use Mifrial\Core\Kernel\Interface\Http\IRequestContext;
use Mifrial\Core\Kernel\Interface\Service\IApplication;
use Mifrial\Core\Kernel\Service\ApplicationFactory;
use Mifrial\Core\SmartTable\Exception\Database\DatabaseException;
use Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer;
use Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection;
use Mifrial\Core\SmartTable\Interface\Service\IOpenedTable;
use Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway;
use Mifrial\Core\SmartTable\Service\Connection\IlluminateDatabaseConnection;
use Mifrial\Core\User\Interface\Container\IUserContainer;
use Mifrial\Core\User\Interface\Service\IUserAccounts;
use Mifrial\Core\User\Schema\UserSchema;
use Mifrial\Core\User\Table\UserAvatarTable;
use Mifrial\Core\User\Table\UserGroupMemberTable;
use Mifrial\Core\User\Table\UserGroupTable;
use Mifrial\Core\User\Table\UserTable;
use Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer;
use Mifrial\Roleplay\Keyword\Interface\Service\IKeywords;
use Mifrial\Roleplay\Keyword\Table\KeywordTable;
use Mifrial\Roleplay\Lore\Schema\LoreSchema;
use Mifrial\Roleplay\Lore\Service\LorePermissionKeys;
use PHPUnit\Framework\TestCase;

// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods
// phpcs:disable MifrialCodingStandard.Metrics.ClassQuality.TooManyConstructorDependencies

/**
 * HTTP-сценарии кодекса на MySQL.
 */
final class LoreHttpMysqlTest extends TestCase
{
    private ?IApplication $application = null;

    private ?IRequestContext $requestContext = null;

    private ?IUserAccounts $userAccounts = null;

    private ?IKeywords $keywords = null;

    private ?ISmartTableGateway $smartTableGateway = null;

    /**
     * MySQL или skip.
     *
     * @return void
     */
    protected function setUp(): void
    {
        try {
            $this->connectPorts();
        } catch (DatabaseException $exception) {
            self::markTestSkipped($exception->getErrorCode() . ': MySQL is not available for Lore HTTP tests');
        }

        $this->dropTables();
        $this->installSchemas();
    }

    /**
     * Снос таблиц.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $this->dropTables();
    }

    /**
     * Аноним и пользователь без права создания.
     *
     * @return void
     */
    public function testAuthRequiredAndDenied(): void
    {
        $anonymous = $this->dispatch('lore.findWorldPage', ['limit' => 10, 'offset' => 0]);
        self::assertFalse($anonymous['success']);
        self::assertSame('AUTH_REQUIRED', $anonymous['error']['code']);

        $alice = $this->addUser('alice');
        $this->setActor($alice);
        $denied = $this->dispatch('lore.saveWorld', ['world' => $this->worldPayload()]);
        self::assertFalse($denied['success']);
        self::assertSame('AUTH_DENIED', $denied['error']['code']);

        $empty = $this->dispatch('lore.findWorldPage', ['limit' => 10, 'offset' => 0]);
        self::assertTrue($empty['success']);
        self::assertSame(0, $empty['data']['total']);
    }

    /**
     * Мир, дерево, статьи, ссылки, версии и архив.
     *
     * @return void
     */
    public function testWorldTreeArticleFlow(): void
    {
        $alice = $this->addUser('alice');
        $this->setActor($alice, [LorePermissionKeys::CREATE]);
        $created = $this->dispatch('lore.saveWorld', ['world' => $this->worldPayload()]);
        self::assertTrue($created['success'], (string) ($created['error']['message'] ?? ''));
        self::assertSame('austerland', $created['data']['code']);
        self::assertSame(1, $created['data']['version']);
        self::assertTrue($created['data']['canEdit']);
        self::assertSame('private', $created['data']['visibility']);

        $duplicate = $this->dispatch('lore.saveWorld', ['world' => $this->worldPayload()]);
        self::assertFalse($duplicate['success']);
        self::assertSame('LORE_INVALID', $duplicate['error']['code']);

        $world = $this->dispatch('lore.getWorld', ['code' => 'austerland']);
        self::assertTrue($world['success']);
        self::assertSame(0, $world['data']['sectionCount']);
        self::assertSame(1, $world['data']['version']);

        $withoutVersion = $this->dispatch('lore.saveTree', [
            'code' => 'austerland',
            'sections' => $this->sectionsPayload(),
        ]);
        self::assertFalse($withoutVersion['success']);
        self::assertSame('LORE_INVALID', $withoutVersion['error']['code']);

        $tree = $this->dispatch('lore.saveTree', [
            'code' => 'austerland',
            'sections' => $this->sectionsPayload(),
            'expectedWorldVersion' => 1,
        ]);
        self::assertTrue($tree['success'], (string) ($tree['error']['message'] ?? ''));
        self::assertSame(3, $tree['data']['sectionCount']);
        self::assertSame(2, $tree['data']['version']);

        $staleTree = $this->dispatch('lore.saveTree', [
            'code' => 'austerland',
            'sections' => $this->sectionsPayload(),
            'expectedWorldVersion' => 1,
        ]);
        self::assertFalse($staleTree['success']);
        self::assertSame('LORE_VERSION_CONFLICT', $staleTree['error']['code']);
        self::assertSame(2, $staleTree['error']['details']['currentVersion']);

        $fall = $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('fall', 'Падение империи', 'history'),
        ]);
        self::assertTrue($fall['success'], (string) ($fall['error']['message'] ?? ''));
        self::assertSame(1, $fall['data']['version']);
        self::assertCount(1, $fall['data']['links']);
        self::assertSame('old-capital', $fall['data']['links'][0]['code']);
        self::assertSame([], $fall['data']['backlinks']);

        $capital = $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('old-capital', 'Старая столица', 'places', ''),
        ]);
        self::assertTrue($capital['success']);

        $capitalView = $this->dispatch('lore.getArticle', [
            'worldCode' => 'austerland',
            'articleCode' => 'old-capital',
        ]);
        self::assertTrue($capitalView['success']);
        self::assertCount(1, $capitalView['data']['backlinks']);
        self::assertSame('fall', $capitalView['data']['backlinks'][0]['code']);

        $duplicateArticle = $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('fall', 'Ещё падение', 'history'),
        ]);
        self::assertFalse($duplicateArticle['success']);
        self::assertSame('LORE_INVALID', $duplicateArticle['error']['code']);

        $stale = $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('fall', 'Падение империи', 'history'),
            'expectedVersion' => 5,
        ]);
        self::assertFalse($stale['success']);
        self::assertSame('LORE_VERSION_CONFLICT', $stale['error']['code']);
        self::assertSame(1, $stale['error']['details']['currentVersion']);

        $keywordId = $this->keywords()->add('north', 'Север');
        $updated = $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload(
                'fall',
                'Падение империи',
                'history',
                '<p>См. <a data-lore-code="old-capital">столицу</a>.</p>',
                ['keywordIds' => [$keywordId]],
            ),
            'expectedVersion' => 1,
        ]);
        self::assertTrue($updated['success'], (string) ($updated['error']['message'] ?? ''));
        self::assertSame(2, $updated['data']['version']);
        self::assertSame([$keywordId], $updated['data']['keywordIds']);

        $search = $this->dispatch('lore.findArticlePage', [
            'worldCode' => 'austerland',
            'limit' => 10,
            'offset' => 0,
            'q' => 'Падение',
        ]);
        self::assertTrue($search['success']);
        self::assertSame(1, $search['data']['total']);

        $byKeyword = $this->dispatch('lore.findArticlePage', [
            'worldCode' => 'austerland',
            'limit' => 10,
            'offset' => 0,
            'keywordIds' => [$keywordId],
        ]);
        self::assertTrue($byKeyword['success']);
        self::assertSame(1, $byKeyword['data']['total']);

        $unknownSection = $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('orphan', 'Сирота', 'missing'),
        ]);
        self::assertFalse($unknownSection['success']);
        self::assertSame('LORE_INVALID', $unknownSection['error']['code']);

        $removedSection = $this->dispatch('lore.saveTree', [
            'code' => 'austerland',
            'sections' => [$this->sectionsPayload()[0]],
            'expectedWorldVersion' => 2,
        ]);
        self::assertFalse($removedSection['success']);
        self::assertSame('LORE_INVALID', $removedSection['error']['code']);

        $archived = $this->dispatch('lore.archiveArticle', [
            'worldCode' => 'austerland',
            'articleCode' => 'fall',
            'archived' => true,
            'expectedVersion' => 2,
        ]);
        self::assertTrue($archived['success']);
        self::assertTrue($archived['data']['isArchived']);

        $active = $this->dispatch('lore.findArticlePage', [
            'worldCode' => 'austerland',
            'limit' => 10,
            'offset' => 0,
        ]);
        self::assertSame(1, $active['data']['total']);

        $withArchived = $this->dispatch('lore.findArticlePage', [
            'worldCode' => 'austerland',
            'limit' => 10,
            'offset' => 0,
            'includeArchived' => true,
        ]);
        self::assertSame(2, $withArchived['data']['total']);

        $archivedWorld = $this->dispatch('lore.archiveWorld', [
            'code' => 'austerland',
            'archived' => true,
            'expectedVersion' => 2,
        ]);
        self::assertTrue($archivedWorld['success']);
        self::assertTrue($archivedWorld['data']['isArchived']);

        $activeWorlds = $this->dispatch('lore.findWorldPage', ['limit' => 10, 'offset' => 0]);
        self::assertSame(0, $activeWorlds['data']['total']);

        $archiveWorlds = $this->dispatch('lore.findWorldPage', [
            'limit' => 10,
            'offset' => 0,
            'state' => 'archived',
        ]);
        self::assertSame(1, $archiveWorlds['data']['total']);
    }

    /**
     * draft и secret видны только редактору, админам и readers.
     *
     * @return void
     */
    public function testArticleVisibilityAndReaders(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $charlie = $this->addUser('charlie');

        $this->setActor($alice, [LorePermissionKeys::CREATE]);
        $this->dispatch('lore.saveWorld', ['world' => $this->worldPayload('public')]);
        $this->dispatch('lore.saveTree', [
            'code' => 'austerland',
            'sections' => $this->sectionsPayload(),
            'expectedWorldVersion' => 1,
        ]);
        $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('public-lore', 'Открытая', 'history', '', [
                'status' => 'published',
                'visibility' => 'public',
            ]),
        ]);
        $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('secret-for-bob', 'Тайна для Боба', 'wars', '', [
                'status' => 'published',
                'visibility' => 'secret',
                'readers' => [$bob],
            ]),
        ]);
        $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('secret', 'Тайна', 'wars', '', [
                'status' => 'published',
                'visibility' => 'secret',
            ]),
        ]);
        $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('draft', 'Черновик', 'wars', '', [
                'status' => 'draft',
                'visibility' => 'public',
            ]),
        ]);

        $ownerView = $this->dispatch('lore.getWorld', ['code' => 'austerland']);
        self::assertSame(4, $ownerView['data']['articleCount']);

        $this->setActor($bob);
        $bobWorld = $this->dispatch('lore.getWorld', ['code' => 'austerland']);
        self::assertTrue($bobWorld['success']);
        $bobCodes = array_column($bobWorld['data']['articles'], 'code');
        self::assertContains('public-lore', $bobCodes);
        self::assertContains('secret-for-bob', $bobCodes);
        self::assertNotContains('secret', $bobCodes);
        self::assertNotContains('draft', $bobCodes);

        $bobSecret = $this->dispatch('lore.getArticle', [
            'worldCode' => 'austerland',
            'articleCode' => 'secret',
        ]);
        self::assertFalse($bobSecret['success']);
        self::assertSame('LORE_NOT_FOUND', $bobSecret['error']['code']);

        $bobDraft = $this->dispatch('lore.getArticle', [
            'worldCode' => 'austerland',
            'articleCode' => 'draft',
        ]);
        self::assertFalse($bobDraft['success']);

        $bobSearch = $this->dispatch('lore.findArticlePage', [
            'worldCode' => 'austerland',
            'limit' => 10,
            'offset' => 0,
        ]);
        self::assertSame(2, $bobSearch['data']['total']);

        $bobCandidates = $this->dispatch('lore.findReaderCandidates', [
            'worldCode' => 'austerland',
            'q' => 'alice',
        ]);
        self::assertFalse($bobCandidates['success']);
        self::assertSame('LORE_DENIED', $bobCandidates['error']['code']);

        $this->setActor($alice, [LorePermissionKeys::CREATE]);
        $candidates = $this->dispatch('lore.findReaderCandidates', [
            'worldCode' => 'austerland',
            'q' => 'bob',
        ]);
        self::assertTrue($candidates['success'], (string) ($candidates['error']['message'] ?? ''));
        self::assertNotEmpty($candidates['data']['items']);
        self::assertArrayHasKey('id', $candidates['data']['items'][0]);
        self::assertArrayNotHasKey('email', $candidates['data']['items'][0]);

        $this->setActor($charlie);
        $publicWorlds = $this->dispatch('lore.findWorldPage', ['limit' => 10, 'offset' => 0]);
        self::assertSame(1, $publicWorlds['data']['total']);
        self::assertFalse($publicWorlds['data']['items'][0]['canEdit']);

        $this->setActor($alice, [LorePermissionKeys::CREATE]);
        $this->dispatch('lore.saveWorld', ['world' => $this->worldPayload('private', 'hidden')]);
        $this->setActor($charlie);
        $hidden = $this->dispatch('lore.getWorld', ['code' => 'hidden']);
        self::assertFalse($hidden['success']);
        self::assertSame('LORE_NOT_FOUND', $hidden['error']['code']);
    }

    /**
     * Экспорт, сухой прогон, импорт, слияние и предупреждения.
     *
     * @return void
     */
    public function testExportImportRoundTrip(): void
    {
        $alice = $this->addUser('alice');
        $bob = $this->addUser('bob');
        $this->setActor($alice, [LorePermissionKeys::CREATE]);
        $this->dispatch('lore.saveWorld', ['world' => $this->worldPayload('public')]);
        $this->dispatch('lore.saveTree', [
            'code' => 'austerland',
            'sections' => $this->sectionsPayload(),
            'expectedWorldVersion' => 1,
        ]);
        $keywordId = $this->keywords()->add('north', 'Север');
        $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('fall', 'Падение империи', 'history', '', [
                'keywordIds' => [$keywordId],
            ]),
        ]);
        $this->dispatch('lore.saveArticle', [
            'worldCode' => 'austerland',
            'article' => $this->articlePayload('secret-for-bob', 'Тайна для Боба', 'wars', '', [
                'visibility' => 'secret',
                'readers' => [$bob],
            ]),
        ]);

        $exported = $this->dispatch('lore.exportWorld', ['worldCode' => 'austerland']);
        self::assertTrue($exported['success'], (string) ($exported['error']['message'] ?? ''));
        $file = $exported['data'];
        self::assertSame('powerscale.lore', $file['format']);
        self::assertSame(1, $file['formatVersion']);
        self::assertSame('austerland', $file['world']['code']);
        self::assertCount(3, $file['sections']);
        self::assertCount(2, $file['articles']);
        self::assertCount(1, $file['keywords']);
        $articlesByCode = array_column($file['articles'], null, 'code');
        self::assertSame(['north'], $articlesByCode['fall']['keywordCodes']);
        self::assertArrayNotHasKey('keywordIds', $articlesByCode['fall']);
        self::assertArrayNotHasKey('id', $articlesByCode['fall']);
        $bobLogin = $this->userAccounts()->getById($bob)->getLogin();
        self::assertSame([$bobLogin], $articlesByCode['secret-for-bob']['readers']);

        $dryRun = $this->dispatch('lore.importWorld', [
            'payload' => $file,
            'mode' => 'new',
            'dryRun' => true,
        ]);
        self::assertTrue($dryRun['success'], (string) ($dryRun['error']['message'] ?? ''));
        self::assertTrue($dryRun['data']['dryRun']);
        self::assertSame('austerland-2', $dryRun['data']['worldCode']);
        self::assertSame(3, $dryRun['data']['created']['sections']);
        self::assertSame(2, $dryRun['data']['created']['articles']);

        $broken = $file;
        $broken['formatVersion'] = 2;
        $brokenImport = $this->dispatch('lore.importWorld', ['payload' => $broken, 'mode' => 'new']);
        self::assertFalse($brokenImport['success']);
        self::assertSame('LORE_INVALID', $brokenImport['error']['code']);

        $imported = $this->dispatch('lore.importWorld', ['payload' => $file, 'mode' => 'new']);
        self::assertTrue($imported['success'], (string) ($imported['error']['message'] ?? ''));
        self::assertSame('austerland-2', $imported['data']['worldCode']);
        self::assertSame(3, $imported['data']['created']['sections']);
        self::assertSame(2, $imported['data']['created']['articles']);

        $copy = $this->dispatch('lore.getWorld', ['code' => 'austerland-2']);
        self::assertTrue($copy['success']);
        self::assertSame(3, $copy['data']['sectionCount']);
        self::assertSame(2, $copy['data']['articleCount']);

        $copiedArticle = $this->dispatch('lore.getArticle', [
            'worldCode' => 'austerland-2',
            'articleCode' => 'secret-for-bob',
        ]);
        self::assertTrue($copiedArticle['success']);
        self::assertSame('secret', $copiedArticle['data']['visibility']);
        self::assertSame([$bob], $copiedArticle['data']['readerIds']);

        $copiedFall = $this->dispatch('lore.getArticle', [
            'worldCode' => 'austerland-2',
            'articleCode' => 'fall',
        ]);
        self::assertSame([$keywordId], $copiedFall['data']['keywordIds']);

        $reExported = $this->dispatch('lore.exportWorld', ['worldCode' => 'austerland-2']);
        self::assertTrue($reExported['success']);
        self::assertSame($file['articles'], $reExported['data']['articles']);
        self::assertSame($file['sections'], $reExported['data']['sections']);

        $merged = $file;
        $fallIndex = array_search('fall', array_column($merged['articles'], 'code'), true);
        self::assertIsInt($fallIndex);
        $merged['articles'][$fallIndex]['title'] = 'Падение империи II';
        $overwritten = $this->dispatch('lore.importWorld', [
            'payload' => $merged,
            'mode' => 'merge',
            'targetWorldCode' => 'austerland',
            'conflictPolicy' => 'overwrite',
        ]);
        self::assertTrue($overwritten['success'], (string) ($overwritten['error']['message'] ?? ''));
        self::assertSame(2, $overwritten['data']['updated']['articles']);
        self::assertSame(3, $overwritten['data']['updated']['sections']);

        $updated = $this->dispatch('lore.getArticle', [
            'worldCode' => 'austerland',
            'articleCode' => 'fall',
        ]);
        self::assertSame('Падение империи II', $updated['data']['title']);

        $skipped = $this->dispatch('lore.importWorld', [
            'payload' => $merged,
            'mode' => 'merge',
            'targetWorldCode' => 'austerland',
            'conflictPolicy' => 'skip',
        ]);
        self::assertTrue($skipped['success']);
        self::assertSame(2, $skipped['data']['skipped']['articles']);

        $withUnknownReader = $file;
        $withUnknownReader['articles'][0]['readers'] = ['ghost_login'];
        $warned = $this->dispatch('lore.importWorld', [
            'payload' => $withUnknownReader,
            'mode' => 'new',
            'conflictPolicy' => 'skip',
        ]);
        self::assertTrue($warned['success'], (string) ($warned['error']['message'] ?? ''));
        $warningCodes = array_column($warned['data']['warnings'], 'code');
        self::assertContains('reader-unknown', $warningCodes);

        $unsafe = $file;
        $unsafe['articles'][0]['bodyHtml'] = '<p><img src="x"><script>alert(1)</script>'
            . '<a href="https://example.com" data-lore-code="secret-for-bob">ссылка</a></p>';
        $cleaned = $this->dispatch('lore.importWorld', ['payload' => $unsafe, 'mode' => 'new']);
        self::assertTrue($cleaned['success'], (string) ($cleaned['error']['message'] ?? ''));
        $cleanedCodes = array_column($cleaned['data']['warnings'], 'code');
        self::assertContains('html-img', $cleanedCodes);
        self::assertContains('html-dangerous-tag', $cleanedCodes);
        self::assertContains('html-href', $cleanedCodes);

        $cleanedWorldCode = $cleaned['data']['worldCode'];
        $cleanedArticle = $this->dispatch('lore.getArticle', [
            'worldCode' => $cleanedWorldCode,
            'articleCode' => 'fall',
        ]);
        self::assertTrue($cleanedArticle['success']);
        $body = $cleanedArticle['data']['bodyHtml'];
        self::assertStringNotContainsString('<script', $body);
        self::assertStringNotContainsString('<img', $body);
        self::assertStringNotContainsString('href=', $body);
        self::assertStringContainsString('data-lore-code="secret-for-bob"', $body);
    }

    /**
     * Мир для HTTP-теста.
     *
     * @param string $visibility Видимость.
     * @param string $code Код.
     *
     * @return array<string, mixed> Снимок.
     */
    private function worldPayload(string $visibility = 'private', string $code = 'austerland'): array
    {
        return [
            'code' => $code,
            'name' => 'Аустерленд',
            'description' => 'Мир севера',
            'visibility' => $visibility,
            'spaceCode' => '',
        ];
    }

    /**
     * Дерево разделов.
     *
     * @return array<int, array<string, mixed>> Узлы.
     */
    private function sectionsPayload(): array
    {
        return [
            ['code' => 'history', 'name' => 'История', 'sortOrder' => 0, 'icon' => 'mdi-history'],
            ['code' => 'wars', 'name' => 'Войны', 'parentCode' => 'history', 'sortOrder' => 1],
            ['code' => 'places', 'name' => 'Места', 'sortOrder' => 2, 'icon' => 'mdi-map-marker'],
        ];
    }

    /**
     * Статья для HTTP-теста.
     *
     * @param string $code Код.
     * @param string $title Заголовок.
     * @param string $section Раздел.
     * @param string $body HTML.
     * @param array<string, mixed> $extra Переопределения.
     *
     * @return array<string, mixed> Снимок.
     */
    private function articlePayload(
        string $code,
        string $title,
        string $section,
        string $body = '<p>См. <a data-lore-code="old-capital">столицу</a>.</p>',
        array $extra = [],
    ): array {
        return array_merge([
            'code' => $code,
            'title' => $title,
            'summary' => 'Кратко',
            'bodyHtml' => $body,
            'sectionCode' => $section,
            'sortOrder' => 0,
            'keywordIds' => [],
            'status' => 'published',
            'visibility' => 'public',
            'readers' => [],
        ], $extra);
    }

    /**
     * boot и порты.
     *
     * @return void
     *
     * @throws DatabaseException Если MySQL недоступен.
     */
    private function connectPorts(): void
    {
        $application = (new ApplicationFactory())->boot(dirname(__DIR__, 4));
        $this->application = $application;
        $routes = $application->getModuleManager()->getRoutes();
        foreach (
            [
                'lore.findWorldPage',
                'lore.getWorld',
                'lore.saveWorld',
                'lore.archiveWorld',
                'lore.saveTree',
                'lore.findArticlePage',
                'lore.getArticle',
                'lore.saveArticle',
                'lore.archiveArticle',
                'lore.findReaderCandidates',
            ] as $route
        ) {
            self::assertArrayHasKey($route, $routes);
        }

        $kernelContainer = $application->getLocator()->get(IKernelContainer::class);
        $requestContext = $kernelContainer->get(IRequestContext::class);
        self::assertInstanceOf(IRequestContext::class, $requestContext);
        $this->requestContext = $requestContext;

        $userContainer = $application->getLocator()->get(IUserContainer::class);
        $userAccounts = $userContainer->get(IUserAccounts::class);
        self::assertInstanceOf(IUserAccounts::class, $userAccounts);
        $this->userAccounts = $userAccounts;

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
     * User + Keyword + Lore.
     *
     * @return void
     */
    private function installSchemas(): void
    {
        $gateway = $this->smartTableGateway();
        (new UserSchema(
            $gateway->open(UserTable::class)->schema(),
            $gateway->open(UserGroupTable::class)->schema(),
            $gateway->open(UserGroupMemberTable::class)->schema(),
            $gateway->open(UserAvatarTable::class)->schema(),
        ))->install();
        $gateway->open(KeywordTable::class)->schema()->createTable();
        foreach (LoreSchema::getTableClasses() as $tableClass) {
            $gateway->open($tableClass)->schema()->createTable();
        }
    }

    /**
     * Снос.
     *
     * @return void
     */
    private function dropTables(): void
    {
        if (!$this->smartTableGateway instanceof ISmartTableGateway) {
            return;
        }

        $gateway = $this->smartTableGateway;
        $tables = array_merge(
            array_reverse(LoreSchema::getTableClasses()),
            [KeywordTable::class],
        );
        foreach ($tables as $tableClass) {
            $this->deleteIfExists($gateway->open($tableClass));
        }
    }

    /**
     * deleteTable если есть.
     *
     * @param IOpenedTable $openedTable Карта.
     *
     * @return void
     */
    private function deleteIfExists(IOpenedTable $openedTable): void
    {
        if ($openedTable->schema()->exists()) {
            $openedTable->schema()->deleteTable();
        }
    }

    /**
     * Учётка.
     *
     * @param string $login Логин.
     *
     * @return int Id.
     */
    private function addUser(string $login): int
    {
        return $this->userAccounts()->addFromInput([
            'login' => $login . '_' . bin2hex(random_bytes(4)),
            'name' => $login,
        ]);
    }

    /**
     * Актор HTTP.
     *
     * @param int $userId Учётка.
     * @param array<int, string> $permissionKeys Ключи.
     *
     * @return void
     */
    private function setActor(int $userId, array $permissionKeys = []): void
    {
        $this->requestContext()->setActor(new RequestActor($userId, $permissionKeys, false));
    }

    /**
     * Action.
     *
     * @param string $action Код.
     * @param mixed $payload Тело.
     *
     * @return array<string, mixed> Конверт.
     */
    private function dispatch(string $action, mixed $payload): array
    {
        return $this->application()->dispatch($action, $payload)->toArray();
    }

    /**
     * Приложение.
     *
     * @return IApplication Приложение.
     */
    private function application(): IApplication
    {
        self::assertInstanceOf(IApplication::class, $this->application);

        return $this->application;
    }

    /**
     * Контекст.
     *
     * @return IRequestContext Контекст.
     */
    private function requestContext(): IRequestContext
    {
        self::assertInstanceOf(IRequestContext::class, $this->requestContext);

        return $this->requestContext;
    }

    /**
     * Учётки.
     *
     * @return IUserAccounts Порт.
     */
    private function userAccounts(): IUserAccounts
    {
        self::assertInstanceOf(IUserAccounts::class, $this->userAccounts);

        return $this->userAccounts;
    }

    /**
     * Признаки.
     *
     * @return IKeywords Порт.
     */
    private function keywords(): IKeywords
    {
        self::assertInstanceOf(IKeywords::class, $this->keywords);

        return $this->keywords;
    }

    /**
     * Шлюз ST.
     *
     * @return ISmartTableGateway Шлюз.
     */
    private function smartTableGateway(): ISmartTableGateway
    {
        self::assertInstanceOf(ISmartTableGateway::class, $this->smartTableGateway);

        return $this->smartTableGateway;
    }
}
