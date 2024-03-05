<?php

enum SolutionType: string
{
    case PHP = 'php';
    case JS = 'js';
    case RUST = 'rs';
    case KOTLIN = 'kt';

    public function label(): string
    {
        return match ($this) {
            self::PHP => 'PHP',
            self::JS => 'JavaScript',
            self::RUST => 'Rust',
            self::KOTLIN => 'Kotlin',
        };
    }

    public function filename(): string
    {
        return sprintf('solution.%s', $this->value);
    }
}