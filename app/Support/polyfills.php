<?php

if (! function_exists('mb_split')) {
    function mb_split(string $pattern, string $string, int $limit = -1): array|false
    {
        return preg_split('/'.$pattern.'/u', $string, $limit);
    }
}

if (! function_exists('format_minutes')) {
    function format_minutes(?int $minutes): string
    {
        return \App\Services\WorkDuration::formatMinutes($minutes);
    }
}
