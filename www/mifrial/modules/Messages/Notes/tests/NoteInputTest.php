<?php

declare(strict_types=1);

namespace Mifrial\Messages\Notes\Tests;

use Mifrial\Messages\Notes\Dto\NewNote;
use Mifrial\Messages\Notes\Dto\NoteFilter;
use Mifrial\Messages\Notes\Dto\NotePatch;
use Mifrial\Messages\Notes\Exception\NoteInvalidException;
use PHPUnit\Framework\TestCase;

final class NoteInputTest extends TestCase
{
    /**
     * Новый заголовок тримится, пустой — отказ.
     *
     * @return void
     */
    public function testNewNoteTitle(): void
    {
        $note = NewNote::fromNormalized([
            'hostType' => 'character',
            'hostId' => 3,
            'title' => '  Гаррик  ',
        ]);

        self::assertSame('Гаррик', $note->getTitle());
        self::assertSame('', $note->getBodyHtml());
    }

    /**
     * Пустой заголовок и плохой тип сущности отвергаются.
     *
     * @return void
     */
    public function testNewNoteRejects(): void
    {
        $this->expectException(NoteInvalidException::class);
        NewNote::fromNormalized(['hostType' => 'Character', 'hostId' => 3, 'title' => 'x']);
    }

    /**
     * Patch с null не меняет поле.
     *
     * @return void
     */
    public function testPatchNullMeansNoChange(): void
    {
        $patch = NotePatch::fromNormalized(['title' => null, 'bodyHtml' => '<p>x</p>']);

        self::assertNull($patch->getTitle());
        self::assertSame('<p>x</p>', $patch->getBodyHtml());
        self::assertFalse($patch->isEmpty());
    }

    /**
     * state по умолчанию active.
     *
     * @return void
     */
    public function testFilterDefaults(): void
    {
        $filter = NoteFilter::fromNormalized([]);

        self::assertSame('active', $filter->getState());
        self::assertSame(50, $filter->getLimit());
        self::assertSame(0, $filter->getOffset());
    }

    /**
     * Неизвестный state отвергается.
     *
     * @return void
     */
    public function testFilterRejectsState(): void
    {
        $this->expectException(NoteInvalidException::class);
        NoteFilter::fromNormalized(['state' => 'deleted']);
    }
}
