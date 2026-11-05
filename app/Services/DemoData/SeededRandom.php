<?php

namespace App\Services\DemoData;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Deterministic random source for the synthetic demo dataset.
 *
 * Uses its own Mt19937 engine, so other code calling mt_rand()/random_int()
 * can never shift the generated dataset.
 */
class SeededRandom
{
    private Randomizer $randomizer;

    public function __construct(int $seed)
    {
        $this->randomizer = new Randomizer(new Mt19937($seed));
    }

    public function int(int $min, int $max): int
    {
        if ($max <= $min) {
            return $min;
        }

        return $this->randomizer->getInt($min, $max);
    }

    /** Uniform float in [0, 1). */
    public function float(): float
    {
        return $this->randomizer->nextFloat();
    }

    public function between(float $min, float $max): float
    {
        return $min + ($max - $min) * $this->float();
    }

    /** Triangular-ish value in [0, 1) that favours the middle of a band. */
    public function centred(): float
    {
        return ($this->float() + $this->float()) / 2;
    }

    public function chance(float $probability): bool
    {
        return $this->float() < $probability;
    }

    /**
     * @template T
     *
     * @param  array<int|string, T>  $items
     * @return T
     */
    public function pick(array $items): mixed
    {
        $values = array_values($items);

        return $values[$this->int(0, count($values) - 1)];
    }

    /**
     * Pick a key from [key => weight]. Zero or negative weights are never picked.
     */
    public function weighted(array $weights): int|string
    {
        $total = 0.0;

        foreach ($weights as $weight) {
            $total += max(0.0, (float) $weight);
        }

        if ($total <= 0) {
            return array_key_first($weights);
        }

        $target = $this->float() * $total;
        $running = 0.0;

        foreach ($weights as $key => $weight) {
            $running += max(0.0, (float) $weight);

            if ($target < $running) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    public function shuffle(array $items): array
    {
        return $this->randomizer->shuffleArray(array_values($items));
    }

    /**
     * Weighted sample of $count distinct keys from [key => weight].
     *
     * @return array<int, int|string>
     */
    public function sample(array $weights, int $count): array
    {
        $chosen = [];

        while (count($chosen) < $count && $weights !== []) {
            $key = $this->weighted($weights);
            $chosen[] = $key;
            unset($weights[$key]);
        }

        return $chosen;
    }

    public function code(int $length): string
    {
        // No 0/O/1/I to keep printed codes readable.
        return $this->randomizer->getBytesFromString('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', $length);
    }
}
