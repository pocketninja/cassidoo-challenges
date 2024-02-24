<?php

enum SolutionType: string
{
    case PHP = 'php';
    case JS = 'js';
    case RUST = 'rs';

    public function label(): string
    {
        return match ($this) {
            self::PHP => 'PHP',
            self::JS => 'JavaScript',
            self::RUST => 'Rust',
        };
    }

    public function filename(): string
    {
        return sprintf('solution.%s', $this->value);
    }
}