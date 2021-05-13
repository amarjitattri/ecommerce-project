<?php

namespace App\Helpers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class FileUpload
{
    /**
     * upload file in storage.
     *
     * @return intger
     */
    public static function uploadFile($file, $path, $k = 0)
    {
        $file_name = $k . app('CommonHelper')->generateRandomNumber() . '_' . $file->getClientOriginalName();
        return Storage::disk('admin')->put($path . $file_name, File::get($file))
        ? $file_name
        : redirect()->back()->with('error', __('messages.something_went_wrong_file_upload'));
    }

}
