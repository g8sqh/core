<?php

namespace Biigle\Listeners;

use Storage;
use Biigle\Events\TiledImagesDeleted;
use Illuminate\Contracts\Queue\ShouldQueue;

class CleanupImageTiles implements ShouldQueue
{
    /**
     * Handle the event.
     *
     * @param  TiledImagesDeleted  $event
     * @return void
     */
    public function handle(TiledImagesDeleted $event)
    {
        $disk = Storage::disk(config('image.tiles.disk'));

        foreach ($event->uuids as $uuid) {
            $disk->delete(fragment_uuid_path($uuid));
        }
    }
}
