<?php

namespace App\Notifications\Messages;

class SmsMessage
{
    public function __construct(public string $text = '') {}

    public static function make(string $text): self
    {
        return new self($text);
    }

    public function text(string $text): self
    {
        $this->text = $text;
        return $this;
    }
}
