<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model\Http;

use Psr\Http\Message\StreamInterface;

/** In-memory body for the SDK's JSON requests and Magento Curl responses. */
class Stream implements StreamInterface
{
    private int $offset = 0;

    public function __construct(private string $contents = '')
    {
    }

    public function __toString(): string
    {
        return $this->contents;
    }

    public function close(): void
    {
        $this->contents = '';
    }

    public function detach()
    {
        $this->contents = '';
        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->contents);
    }

    public function tell(): int
    {
        return $this->offset;
    }

    public function eof(): bool
    {
        return $this->offset >= strlen($this->contents);
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $length = strlen($this->contents);
        $this->offset = match ($whence) {
            SEEK_END => $length + $offset,
            SEEK_CUR => $this->offset + $offset,
            default => $offset,
        };
    }

    public function rewind(): void
    {
        $this->offset = 0;
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function write(string $string): int
    {
        $this->contents .= $string;
        return strlen($string);
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $chunk = substr($this->contents, $this->offset, $length);
        $this->offset += strlen($chunk);
        return $chunk;
    }

    public function getContents(): string
    {
        $rest = substr($this->contents, $this->offset);
        $this->offset = strlen($this->contents);
        return $rest === false ? '' : $rest;
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }
}
