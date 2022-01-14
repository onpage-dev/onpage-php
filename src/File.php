<?php

namespace OnPage;

class File
{
    public $name;
    public $token;
    private $api;

    function __construct(Api $api, object $file)
    {
        $this->api = $api;
        $this->name = $file->name;
        $this->token = $file->token;
    }

    function isImage(): bool
    {
        $ext = pathinfo($this->name, PATHINFO_EXTENSION);
        return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif']);
    }

    function link(array $opts = []): string
    {
        $suffix = '';
        if ($this->isImage() && (isset($opts['x']) || isset($opts['y']))) {
            $suffix .= @".{$opts['x']}x{$opts['y']}";

            if (isset($opts['contain'])) {
                $suffix .= '-contain';
            }
        }

        if ($suffix || isset($opts['ext'])) {
            if (!isset($opts['ext'])) {
                $opts['ext'] = 'jpg';
            }
            $suffix .= ".{$opts['ext']}";
        }
        $name = $opts['name'] ?? true;
        if ($name && !is_string($name)) $name = $this->name;
        else if ($name === false) $name = null;
        return $this->api->storageLink("{$this->token}{$suffix}", $name);
    }
}
