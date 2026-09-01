<?php
namespace Dashboard\Routes;

/**
 * Sticky note dashboard routes.
 */
class StickynoteRoutes extends AbstractRouteRegistrar {
    public function register(): void {
        $this->router->addRoutes([
            [['GET', 'POST', 'DELETE'], '/stickynotes/note/:action/:note_id', 'stickynote/partial/note.dialog', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST', 'DELETE'], '/stickynotes/category/dialog/', 'stickynote/partial/category.dialog', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/stickynotes/note-list', 'stickynote/partial/note.list', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/stickynotes/cat-list', 'stickynote/partial/category.list', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/stickynotes/audio-transcribe', 'stickynote/partial/audio.transcribe.dialog', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/stickynotes/audio-transcribe/save', 'stickynote/partial/audio.transcribe.save', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/stickynotes/audio-transcribe/process', 'stickynote/partial/audio.transcribe.process', 'type' => 'partial', 'middleware' => $this->auth()],
            [['GET', 'POST'], '/stickynotes/search', 'stickynote/partial/note.search', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);
    }
}
