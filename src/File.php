<?php

namespace OnPage;

class File
{
    public string $name;
    public string $token;
    public string $ext;
    public int $size;
    private AbstractApi $api;

    function __construct(AbstractApi $api, object $file)
    {
        $this->api = $api;
        $this->name = $file->name;
        $this->token = $file->token;
        $this->ext = $file->ext;
        $this->size = $file->size;
    }

    function isImage(): bool
    {
        return in_array(strtolower($this->ext), ['png', 'gif', 'jpg', 'webp', 'eps', 'dwg', 'svg', 'tiff']);
    }

    function link(array $opts = []): string
    {
        $suffix = '';
        if (isset($opts['x']) || isset($opts['y'])) {
            $suffix .= @".{$opts['x']}x{$opts['y']}";

            if (isset($opts['contain'])) {
                $suffix .= '-contain';
            }
        }

        if ($suffix || isset($opts['ext'])) {
            if (!isset($opts['ext'])) {
                $opts['ext'] = $this->api->thumbnail_format;
            }
            $suffix .= ".{$opts['ext']}";
        }

        $name = $opts['name'] ?? true;
        if ($name && !is_string($name)) $name = $this->name;
        else if ($name === false) $name = null;

        if ($name && isset($opts['ext'])) {
            $name = self::replaceFilenameExtension($name, $opts['ext']);
        }

        return $this->api->storageLink("{$this->token}{$suffix}", $name);
    }

    static function replaceFilenameExtension(string $filename, ?string $ext)
    {
        if (!$ext) return $filename;
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        return "$filename.$ext";
    }
}
