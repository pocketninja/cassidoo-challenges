<?php

readonly class Challenge
{
    public function __construct(
        public ?string $title,
        public ?string $content,
    ) {
    }

    public function valid(): bool
    {
        return $this->title !== null && $this->content !== null;
    }
}