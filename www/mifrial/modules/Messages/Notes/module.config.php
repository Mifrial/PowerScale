<?php

declare(strict_types=1);

use Mifrial\Core\Kernel\Interface\Service\IServiceLocator;
use Mifrial\Messages\Notes\Action\CreateNoteAction;
use Mifrial\Messages\Notes\Action\DeleteNoteAction;
use Mifrial\Messages\Notes\Action\FindNoteHostsAction;
use Mifrial\Messages\Notes\Action\FindNotePageAction;
use Mifrial\Messages\Notes\Action\GetNoteAction;
use Mifrial\Messages\Notes\Action\PinNoteAction;
use Mifrial\Messages\Notes\Action\ReorderNotesAction;
use Mifrial\Messages\Notes\Action\UpdateNoteAction;
use Mifrial\Messages\Notes\Container\NotesContainer;
use Mifrial\Messages\Notes\Interface\Container\INotesContainer;
use Mifrial\Messages\Notes\Interface\Service\INotes;
use Mifrial\Messages\Notes\Service\NoteHttpService;
use Mifrial\Messages\Notes\Service\NotePortFactory;
use Mifrial\Messages\Notes\Setup\NotesModuleSetup;

return [
    'container' => NotesContainer::class,
    'locator' => INotesContainer::class,
    'setup' => NotesModuleSetup::class,
    'ports' => [
        INotes::class => static function (IServiceLocator $serviceLocator): INotes {
            return (new NotePortFactory())->create($serviceLocator);
        },
        NoteHttpService::class => static function (IServiceLocator $serviceLocator): NoteHttpService {
            return (new NotePortFactory())->createHttp($serviceLocator);
        },
        FindNotePageAction::class => static function (IServiceLocator $serviceLocator): FindNotePageAction {
            return new FindNotePageAction((new NotePortFactory())->fromContainer($serviceLocator));
        },
        FindNoteHostsAction::class => static function (IServiceLocator $serviceLocator): FindNoteHostsAction {
            return new FindNoteHostsAction((new NotePortFactory())->fromContainer($serviceLocator));
        },
        GetNoteAction::class => static function (IServiceLocator $serviceLocator): GetNoteAction {
            return new GetNoteAction((new NotePortFactory())->fromContainer($serviceLocator));
        },
        CreateNoteAction::class => static function (IServiceLocator $serviceLocator): CreateNoteAction {
            return new CreateNoteAction((new NotePortFactory())->fromContainer($serviceLocator));
        },
        UpdateNoteAction::class => static function (IServiceLocator $serviceLocator): UpdateNoteAction {
            return new UpdateNoteAction((new NotePortFactory())->fromContainer($serviceLocator));
        },
        DeleteNoteAction::class => static function (IServiceLocator $serviceLocator): DeleteNoteAction {
            return new DeleteNoteAction((new NotePortFactory())->fromContainer($serviceLocator));
        },
        ReorderNotesAction::class => static function (IServiceLocator $serviceLocator): ReorderNotesAction {
            return new ReorderNotesAction((new NotePortFactory())->fromContainer($serviceLocator));
        },
        PinNoteAction::class => static function (IServiceLocator $serviceLocator): PinNoteAction {
            return new PinNoteAction((new NotePortFactory())->fromContainer($serviceLocator));
        },
    ],
    'routes' => [
        'note.findPage' => [
            'handler' => FindNotePageAction::class,
            'csrf' => true,
        ],
        'note.findHosts' => [
            'handler' => FindNoteHostsAction::class,
            'csrf' => true,
        ],
        'note.get' => [
            'handler' => GetNoteAction::class,
            'csrf' => true,
        ],
        'note.create' => [
            'handler' => CreateNoteAction::class,
            'csrf' => true,
        ],
        'note.update' => [
            'handler' => UpdateNoteAction::class,
            'csrf' => true,
        ],
        'note.delete' => [
            'handler' => DeleteNoteAction::class,
            'csrf' => true,
        ],
        'note.reorder' => [
            'handler' => ReorderNotesAction::class,
            'csrf' => true,
        ],
        'note.pin' => [
            'handler' => PinNoteAction::class,
            'csrf' => true,
        ],
    ],
    'events' => [],
];
