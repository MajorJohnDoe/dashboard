<?php
namespace Dashboard\Stickynote;

use Dashboard\Core\Interfaces\DatabaseInterface;
use Dashboard\Core\User;

class StickyNoteControllerFactory
{
    public static function create(DatabaseInterface $db, User $user): StickyNoteController
    {
        $stickyNote = new StickyNote($db);
        $stickyCategory = new StickyCategory($db);
        return new StickyNoteController($user, $stickyNote, $stickyCategory, $db);
    }
}

?>