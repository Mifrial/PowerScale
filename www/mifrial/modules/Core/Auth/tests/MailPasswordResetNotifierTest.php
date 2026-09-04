<?php

declare(strict_types=1);

namespace Mifrial\Core\Auth\Tests;

use Mifrial\Core\Auth\Dto\AuthSettings;
use Mifrial\Core\Auth\Service\MailPasswordResetNotifier;
use PHPUnit\Framework\TestCase;

final class MailPasswordResetNotifierTest extends TestCase
{
    /**
     * trigger с login/token/email; expose из настроек.
     *
     * @return void
     */
    public function testNotifyTriggersMailEvent(): void
    {
        $mail = new RecordingMail();
        $notifier = new MailPasswordResetNotifier(
            $mail,
            AuthSettings::fromSection(['expose_reset_token' => true]),
        );
        $notifier->notify('alice', 'raw-token', 'alice@x.test');
        self::assertSame('auth.password_reset', $mail->eventCode);
        self::assertSame(
            ['login' => 'alice', 'token' => 'raw-token', 'email' => 'alice@x.test'],
            $mail->payload,
        );
        self::assertTrue($notifier->shouldExposeRawToken());
    }
}
