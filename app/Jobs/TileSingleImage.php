<?php

namespace Biigle\Jobs;

use File;
use Storage;
use VipsImage;
use FileCache;
use SplFileInfo;
use Biigle\Image;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class TileSingleImage extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    /**
     * The image to generate tiles for.
     *
     * @var Image
     */
    public $image;

    /**
     * Path to the temporary storage file for the tiles.
     *
     * @var string
     */
    public $tempPath;

    /**
     * Ignore this job if the image does not exist any more.
     *
     * @var bool
     */
    protected $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param Image $image The image to generate tiles for.
     *
     * @return void
     */
    public function __construct(Image $image)
    {
        $this->image = $image;
        $this->tempPath = config('image.tiles.tmp_dir')."/{$image->uuid}.zip";
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            FileCache::getOnce($this->image, [$this, 'generateTiles']);
            $this->uploadToStorage();
            $this->image->tiled = true;
            $this->image->save();
        } finally {
            File::delete($this->tempPath);
        }
    }

    /**
     * Generate tiles for the image and put them to temporary storage.
     *
     * @param Image $image
     * @param string $path Path to the cached image file.
     */
    public function generateTiles(Image $image, $path)
    {
        $this->getVipsImage($path)->dzsave($this->tempPath, [
            'layout' => 'zoomify',
            'container' => 'zip',
            'strip' => true,
        ]);
    }

    /**
     * Upload the tiles from temporary local storage to the tiles storage disk.
     */
    public function uploadToStorage()
    {
        $directory = $this->image->uuid[0].$this->image->uuid[1].'/'.$this->image->uuid[2].$this->image->uuid[3];
        $file = new SplFileInfo($this->tempPath);
        Storage::disk(config('image.tiles.disk'))->putFileAs($directory, $file, $this->image->uuid);
    }

    /**
     * Get the vips image instance.
     *
     * @param string $path
     *
     * @return \Jcupitt\Vips\Image
     */
    protected function getVipsImage($path)
    {
        return VipsImage::newFromFile($path, ['access' => 'sequential']);
    }
}
