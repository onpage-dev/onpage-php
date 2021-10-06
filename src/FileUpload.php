<?php

namespace OnPage;

class FileUpload
{
    public $path = null;

    public function __construct(string $path)
    {
        $this->path = $path;
    }
}
