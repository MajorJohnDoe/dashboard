<?php
namespace Dashboard\Routes;

/**
 * Attachment routes: upload, list, download and delete of document
 * attachments (PDF, txt, docx, xlsx) on tasks, sticky notes and job
 * applications. Backed by Core\AttachmentController.
 */
class AttachmentRoutes extends AbstractRouteRegistrar {
    public function register(): void {
        $this->router->addRoutes([
            ['POST', '/attachment/upload/:item_type/:item_id', 'Core/AttachmentController@handleUpload', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/attachment/list/:item_type/:item_id', 'Core/AttachmentController@handleList', 'type' => 'partial', 'middleware' => $this->auth()],
            ['GET', '/attachment/download/:attachment_id', 'Core/AttachmentController@handleDownload', 'type' => 'partial', 'middleware' => $this->auth()],
            ['DELETE', '/attachment/delete/:attachment_id', 'Core/AttachmentController@handleDelete', 'type' => 'partial', 'middleware' => $this->auth()],
            // Staged-but-not-yet-claimed files (items that don't exist yet)
            ['DELETE', '/attachment/pending/:token', 'Core/AttachmentController@handleDeletePending', 'type' => 'partial', 'middleware' => $this->auth()],
        ]);
    }
}
