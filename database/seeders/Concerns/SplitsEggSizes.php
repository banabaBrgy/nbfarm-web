<?php

namespace Database\Seeders\Concerns;

trait SplitsEggSizes
{
    /** @return array{small: int, medium: int, large: int, extra_large: int, cracked: int, produced: int} */
    private function splitBySize(int $total): array
    {
        if ($total <= 0) {
            return [
                'small' => 0,
                'medium' => 0,
                'large' => 0,
                'extra_large' => 0,
                'cracked' => 0,
                'produced' => 0,
            ];
        }

        $cracked = (int) (round($total * 0.03 / 30) * 30);
        $cracked = max(30, min($cracked, $total - 120));
        $good = $total - $cracked;

        $weights = [
            'small' => 0.22,
            'medium' => 0.32,
            'large' => 0.28,
            'extra_large' => 0.18,
        ];

        $sizes = [];
        $allocated = 0;
        $keys = array_keys($weights);
        $last = end($keys);

        foreach ($weights as $size => $weight) {
            if ($size === $last) {
                $sizes[$size] = $good - $allocated;
                break;
            }
            $part = (int) (round($good * $weight / 30) * 30);
            $sizes[$size] = $part;
            $allocated += $part;
        }

        return [
            'small' => $sizes['small'],
            'medium' => $sizes['medium'],
            'large' => $sizes['large'],
            'extra_large' => $sizes['extra_large'],
            'cracked' => $cracked,
            'produced' => $total,
        ];
    }
}
