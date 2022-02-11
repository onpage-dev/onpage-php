<?php

namespace OnPage;

class FileUpload
{
    public function __construct(public string $path)
    {
        $this->path = $path;
    }
}
