<?php

class ImageUploadDirectory
{
    public function GetDirectory()
    {
        $uploadDir = Configuration::Instance()->GetKey(ConfigKeys::IMAGE_UPLOAD_DIRECTORY);
        if (is_dir($uploadDir)) {
            return $uploadDir;
        }

        $dir = ROOT_DIR . $uploadDir;
        if (!is_dir($dir)) {
            @mkdir($dir);
        }

        return $dir;
    }

    public function MakeWriteable()
    {
        $chmodResult = chmod($this->GetDirectory(), 0770);
    }

    public function GetPath()
    {
        return Configuration::Instance()->GetScriptUrl() . '/' . Configuration::Instance()->GetKey(ConfigKeys::IMAGE_UPLOAD_URL);
    }
}
