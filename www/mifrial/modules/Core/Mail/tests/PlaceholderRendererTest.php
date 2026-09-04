<?php

declare(strict_types=1);

namespace Mifrial\Core\Mail\Tests;

use Mifrial\Core\Mail\Exception\MailException;
use Mifrial\Core\Mail\Service\PlaceholderRenderer;
use PHPUnit\Framework\TestCase;

final class PlaceholderRendererTest extends TestCase
{
    /**
     * Подстановка, default, битый токен.
     *
     * @return void
     */
    public function testRenderTokens(): void
    {
        $renderer = new PlaceholderRenderer();
        self::assertSame(
            'Hi Bob',
            $renderer->render('Hi {{name}}', ['name' => 'Bob']),
        );
        self::assertSame(
            'Hi Guest',
            $renderer->render("Hi {{name@default:'Guest'}}", ['name' => '']),
        );
        self::assertSame(
            'Hi ',
            $renderer->render('Hi {{name}}', []),
        );
        try {
            $renderer->render('Hi {{ name }}', ['name' => 'Bob']);
            self::fail('spaces');
        } catch (MailException $exception) {
            self::assertSame('MAIL_INVALID', $exception->getErrorCode());
        }
    }
}
