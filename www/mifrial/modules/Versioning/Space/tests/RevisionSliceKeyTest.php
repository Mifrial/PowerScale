<?php

declare(strict_types=1);

namespace Mifrial\Versioning\Space\Tests;

use Mifrial\Versioning\Space\Dto\RevisionSliceKey;
use PHPUnit\Framework\TestCase;

final class RevisionSliceKeyTest extends TestCase
{
    /**
     * Ключ vs:{table}:{space}:{revision}.
     *
     * @return void
     */
    public function testMakeJoinsParts(): void
    {
        self::assertSame('vs:vt_note:3:2', RevisionSliceKey::make('vt_note', 3, 2));
    }
}
