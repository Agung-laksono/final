<?php

namespace App\Services;

class CodeGenerator
{
    /**
     * Generate next sequential code based on the highest existing code number.
     *
     * @param string|object $modelClass Eloquent Model Class or query instance
     * @param string $column Column name storing the document code (e.g. 'so_number')
     * @param string $prefix Code prefix (e.g. 'ODM-', 'PEM-', 'SQ-')
     * @param int $padLength Number of padded zero digits (default 4)
     * @return string
     */
    public static function generateNextCode($modelClass, string $column, string $prefix, int $padLength = 4): string
    {
        $escapedPrefix = str_replace(['%', '_'], ['\%', '\_'], $prefix);
        
        $query = is_string($modelClass) ? $modelClass::query() : $modelClass;

        $latestRecord = $query->where($column, 'like', $escapedPrefix . '%')
            ->orderByRaw('LENGTH(' . $column . ') DESC, ' . $column . ' DESC')
            ->first();

        $nextNumber = 1;

        if ($latestRecord && !empty($latestRecord->{$column})) {
            if (preg_match('/' . preg_quote($prefix, '/') . '(\d+)/', $latestRecord->{$column}, $matches)) {
                $nextNumber = intval($matches[1]) + 1;
            }
        }

        return $prefix . str_pad($nextNumber, $padLength, '0', STR_PAD_LEFT);
    }
}
