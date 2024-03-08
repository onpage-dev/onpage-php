<?php

namespace OnPage;

class File
{
    public string $name;
    public string $token;
    public string $ext;
    public int $size;
    private Api $api;

    function __construct(Api $api, object $file)
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

    function thumbnail(int $width = null, int $height = null, ?string $mode = 'contain', ?string $ext = null)
    {
        return $this->link([
            'x' => $width,
            'y' => $height,
            'mode' => $mode,
            'ext' => $ext,
        ]);
    }

    function link(array $opts = []): string
    {
        $suffix = '';
        if (isset($opts['x']) || isset($opts['y'])) {
            $suffix .= @".{$opts['x']}x{$opts['y']}";

            if (isset($opts['contain'])) {
                $suffix .= '-contain';
            } elseif (isset($opts['mode'])) {
                $suffix .= '-' . $opts['mode'];
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

        return $this->api->storageLink("{$this->token}{$suffix}", $name, (bool) (isset($opts['download']) && $opts['download']));
    }

    static function replaceFilenameExtension(string $filename, ?string $ext)
    {
        if (!$ext) return $filename;
        $filename = pathinfo($filename, PATHINFO_FILENAME);
        return "$filename.$ext";
    }
}
