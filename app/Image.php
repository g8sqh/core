<?php

namespace Biigle;

use Response;
use Exception;
use TileCache;
use FileCache;
use Biigle\Traits\HasJsonAttributes;
use Illuminate\Database\Eloquent\Model;
use Biigle\FileCache\Contracts\File as FileContract;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;

/**
 * This model stores information on an image file in the file system.
 */
class Image extends Model implements FileContract
{
    use HasJsonAttributes;

    /**
     * Don't maintain timestamps for this model.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes hidden in the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'labels',
    ];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'attrs' => 'array',
        'lat' => 'float',
        'lng' => 'float',
        'tiled' => 'bool',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'taken_at',
    ];

    /**
     * {@inheritdoc}
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * The volume, this image belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function volume()
    {
        return $this->belongsTo(Volume::class);
    }

    /**
     * The annotations on this image.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function annotations()
    {
        return $this->hasMany(Annotation::class);
    }

    /**
     * The labels, this image got attached by the users.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function labels()
    {
        return $this->hasMany(ImageLabel::class)->with('label', 'user');
    }

    /**
     * Adds the `url` attribute to the image model. The url is the absolute path
     * to the original image file.
     *
     * @return string
     */
    public function getUrlAttribute()
    {
        return "{$this->volume->url}/{$this->filename}";
    }

    /**
     * Set the image metadata attribute.
     *
     * @param array $value
     */
    public function setMetadataAttribute(array $value)
    {
        return $this->setJsonAttr('metadata', $value);
    }

    /**
     * Get the image metadata attribute.
     *
     * @return array
     */
    public function getMetadataAttribute()
    {
        return $this->getJsonAttr('metadata', []);
    }

    /**
     * Get the original image as download response.
     *
     * @return Response
     */
    public function getFile()
    {
        if ($this->tiled === true) {
            $response = [
                'id' => $this->id,
                'uuid' => $this->uuid,
                'width' => $this->width,
                'height' => $this->height,
                'tiled' => true,
            ];

            // Instruct the image tile cache to load and extract the tiles. This is done
            // syncronously so the tiles are ready when this request returns.
            TileCache::get($this);

            return $response;
        }

        if ($this->volume->isRemote()) {
            return Response::redirectTo($this->url);
        }

        try {
            $stream = FileCache::getStream($this);
            if (!is_resource($stream)) {
                abort(404);
            }

            return response()->stream(function () use ($stream) {
                fpassthru($stream);
            }, 200, [
                'Content-Type' => $this->mimetype,
                'Content-Length' => $this->size,
                'Content-Disposition' => 'inline',
            ]);
        } catch (Exception $e) {
            abort(404, $e->getMessage());
        }
    }

    /**
     * Set the width attribute.
     *
     * @param int $value
     */
    public function setWidthAttribute($value)
    {
        $this->setJsonAttr('width', $value);
    }

    /**
     * Get the width attribute.
     *
     * @return int|null
     */
    public function getWidthAttribute()
    {
        return $this->getJsonAttr('width');
    }

    /**
     * Set the height attribute.
     *
     * @param int $value
     */
    public function setHeightAttribute($value)
    {
        $this->setJsonAttr('height', $value);
    }

    /**
     * Get the height attribute.
     *
     * @return int|null
     */
    public function getHeightAttribute()
    {
        return $this->getJsonAttr('height');
    }

    /**
     * Set the size attribute.
     *
     * @param int $value
     */
    public function setSizeAttribute($value)
    {
        $this->setJsonAttr('size', $value);
    }

    /**
     * Get the size attribute.
     *
     * @return int|null
     */
    public function getSizeAttribute()
    {
        return $this->getJsonAttr('size');
    }

    /**
     * Set the mimetype attribute.
     *
     * @param string $value
     */
    public function setMimetypeAttribute($value)
    {
        $this->setJsonAttr('mimetype', $value);
    }

    /**
     * Get the mimetype attribute.
     *
     * @return string|null
     */
    public function getMimetypeAttribute()
    {
        return $this->getJsonAttr('mimetype');
    }
}
