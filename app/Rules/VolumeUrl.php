<?php

namespace Biigle\Rules;

use App;
use Storage;
use GuzzleHttp\Client;
use Illuminate\Contracts\Validation\Rule;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

class VolumeUrl implements Rule
{
    /**
     * The validation message to display.
     *
     * @var string
     */
    protected $message;

    /**
     * Create a new instance.
     */
    public function __construct()
    {
        $this->message = 'The volume URL is invalid.';
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (strpos($value, 'http') === 0 && !config('biigle.offline_mode')) {
            return $this->passesRemoteUrl($value);
        } else {
            return $this->passesDiskUrl($value);
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message;
    }

    /**
     * Validate a remote volume URL.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function passesRemoteUrl($value)
    {
        $client = App::make(Client::class);

        try {
            $response = $client->head($value);
        } catch (ServerException $e) {
            $this->message = 'The remote volume URL returned an error response. '.$e->getMessage();

            return false;
        } catch (ClientException $e) {
            // A 400 level error means that something is responding.
            // It may well be that the Volume URL results in a 400 response but a
            // single image works fine so we define this as success.
            return true;
        } catch (RequestException $e) {
            $this->message = 'The remote volume URL does not seem to exist. '.$e->getMessage();

            return false;
        }

        return true;
    }

    /**
     * Validate a storage disk volume URL.
     *
     * @param string $value
     *
     * @return bool
     */
    protected function passesDiskUrl($value)
    {
        $url = explode('://', $value);
        if (count($url) !== 2) {
            $this->message = "Unable to identify storage disk. Please set the URL as '[disk]://[path]'.";

            return false;
        }

        if (!config("filesystems.disks.{$url[0]}")) {
            $this->message = "Storage disk '{$url[0]}' does not exist.";

            return false;
        }

        $disk = Storage::disk($url[0]);
        if (empty($disk->files($url[1])) && empty($disk->directories($url[1]))) {
            $this->message = "Unable to access '{$url[1]}'. Does it exist and you have access permissions?";

            return false;
        }

        return true;
    }
}
