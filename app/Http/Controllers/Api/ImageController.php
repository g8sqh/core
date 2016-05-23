<?php

namespace Dias\Http\Controllers\Api;

use Dias\Image;

class ImageController extends Controller
{
    /**
     * Shows the specified image.
     *
     * @api {get} images/:id Get image information
     * @apiDescription Image information includes a subset of the image EXIF
     * data as well as the transect, the image belongs to.
     * @apiGroup Images
     * @apiName ShowImages
     * @apiPermission projectMember
     *
     * @apiParam {Number} id The image ID.
     * @apiSuccessExample {json} Success response:
     * {
     *    "id":1,
     *    "width":1000,
     *    "height":750,
     *    "exif":{
     *       "FileName":"IMG_3275.JPG"
     *       "FileDateTime":1411554694,
     *       "FileSize":3014886,
     *       "FileType":2,
     *       "MimeType":"image\/jpeg",
     *       "Make":"Canon",
     *       "Model":"Canon PowerShot G9",
     *       "Orientation":1,
     *       "DateTime":"2014:05:09 00:53:45",
     *       "ExposureTime":"1\/100",
     *       "FNumber":"28\/10",
     *       "ShutterSpeedValue":"213\/32",
     *       "ApertureValue":"95\/32",
     *       "ExposureBiasValue":"0\/3",
     *       "MaxApertureValue":"95\/32",
     *       "MeteringMode":5,
     *       "Flash":9,
     *       "FocalLength":"7400\/1000",
     *       "ExifImageWidth":3264,
     *       "ExifImageLength":2448,
     *       "ImageType":"IMG:PowerShot G9 JPEG"
     *    },
     *    "transect":{
     *       "id":1,
     *       "name":"Test transect",
     *       "media_type_id":2,
     *       "creator_id":1,
     *       "created_at":"2015-05-04 07:34:04",
     *       "updated_at":"2015-05-04 07:34:04",
     *       "url":"\/path\/to\/transect\/1"
     *    }
     * }
     *
     * @param int $id image id
     * @return Image
     */
    public function show($id)
    {
        $image = Image::with('transect')->findOrFail($id);
        $this->requireCanSee($image);
        $image->setAttribute('exif', $image->getExif());
        $size = $image->getSize();
        $image->setAttribute('width', $size[0]);
        $image->setAttribute('height', $size[1]);

        return $image;
    }

    /**
     * Shows the specified image thumbnail file.
     *
     * @api {get} images/:id/thumb Get a thumbnail image
     * @apiGroup Images
     * @apiName ShowImageThumbs
     * @apiPermission projectMember
     * @apiDescription Responds with a JPG image thumbnail.
     *
     * @apiParam {Number} id The image ID.
     *
     * @param int $id image id
     * @return \Illuminate\Http\Response
     */
    public function showThumb($id)
    {
        $image = Image::findOrFail($id);
        $this->requireCanSee($image);

        return $image->getThumb();
    }

    /**
     * Shows the specified image file.
     *
     * @api {get} images/:id/file Get the original image
     * @apiGroup Images
     * @apiName ShowImageFiles
     * @apiPermission projectMember
     * @apiDescription Responds with the original file.
     *
     * @apiParam {Number} id The image ID.
     *
     * @param int $id image id
     * @return \Illuminate\Http\Response
     */
    public function showFile($id)
    {
        $image = Image::findOrFail($id);
        $this->requireCanSee($image);

        return $image->getFile();
    }

    /**
     * Deletes the image
     *
     * @api {delete} images/:id Delete an image
     * @apiGroup Images
     * @apiName DestroyImage
     * @apiPermission projectAdmin
     *
     * @apiParam {Number} id The image ID.
     *
     * @param int $id image id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $image = Image::findOrFail($id);
        $this->requireCanAdmin($image);

        $image->transect_id = null;
        $image->save();
    }
}
