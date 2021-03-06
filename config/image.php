<?php

return [

    'tiles' => [
        /*
        | Create tiles for local images where the longest edge is larger than this value
        | in pixels. For these images the tiles will be displayed in the annotation tool
        | instead of the original images because they are too large to be displayed in a
        | browser.
        |
        | Set to INF to disable tiling.
        */
        'threshold' => env('IMAGE_TILES_THRESHOLD', 1E+4),

        /*
        | URI where the image tiles are available from.
        | If you have 'tiles', the URL will look like 'example.com/tiles/...'.
        |
        | The URI must exist as directory in the public path.
        | For 'tiles' there must be a 'public/tiles' directory (or link).
        */
        'uri' => 'tiles',

        /*
         | Directory to temporarily store the tiles when they are generated.
         */
        'tmp_dir' => sys_get_temp_dir(),

        /*
         | Storage disk from config('filesystems.disks') to permanently store the tiles.
         | The default disk stores the tiles locally in storage/tiles. You can also use
         | a cloud storage disk for this.
         */
        'disk' => env('IMAGE_TILES_DISK', 'local-tiles'),

        /*
        | Settings for the image tile cache. The image tile cache extracts local or cloud
        | storage image tiles (which are packed in ZIP files) locally so they can be
        | served by the webserver. Image tiles are cached on demand when a user opens an
        | image.
        */
        'cache' => [
            /*
            | Maximum size (soft limit) of the image tile cache in bytes.
            */
            'max_size' => env('IMAGE_TILES_CACHE_MAX_SIZE', 1E+9), // 1 GB

            /*
            | Directory to use for the image tile cache.
            */
            'path' => storage_path('framework/cache/tiles'),
        ],
    ],

];
