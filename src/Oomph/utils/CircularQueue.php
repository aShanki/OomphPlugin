<?php

declare(strict_types=1);

namespace Oomph\utils;

/**
 * Generic circular buffer/ring buffer implementation
 * @template T
 */
class CircularQueue {
    /** @var array<int, T> */
    private array $buffer;
    private int $capacity;
    private int $head = 0;
    private int $tail = 0;
    private int $size = 0;

    /**
     * @param int $capacity Maximum capacity of the circular queue
     */
    public function __construct(int $capacity) {
        if ($capacity <= 0) {
            throw new \InvalidArgumentException("Capacity must be positive");
        }
        $this->capacity = $capacity;
        $this->buffer = [];
    }

    /**
     * Add an element to the queue
     * @param T $element
     * @return void
     */
    public function push($element): void {
        $this->buffer[$this->tail] = $element;
        $this->tail = ($this->tail + 1) % $this->capacity;

        if ($this->size < $this->capacity) {
            $this->size++;
        } else {
            // Queue is full, move head forward
            $this->head = ($this->head + 1) % $this->capacity;
        }
    }

    /**
     * Remove and return the oldest element
     * @return T|null
     */
    public function pop() {
        if ($this->isEmpty()) {
            return null;
        }

        $element = $this->buffer[$this->head];
        unset($this->buffer[$this->head]);
        $this->head = ($this->head + 1) % $this->capacity;
        $this->size--;

        return $element;
    }

    /**
     * Get the oldest element without removing it
     * @return T|null
     */
    public function peek() {
        if ($this->isEmpty()) {
            return null;
        }
        return $this->buffer[$this->head];
    }

    /**
     * Get all elements as an array (oldest to newest)
     * @return array<T>
     */
    public function toArray(): array {
        if ($this->isEmpty()) {
            return [];
        }

        $result = [];
        $current = $this->head;
        for ($i = 0; $i < $this->size; $i++) {
            $result[] = $this->buffer[$current];
            $current = ($current + 1) % $this->capacity;
        }

        return $result;
    }

    /**
     * Check if the queue is full
     * @return bool
     */
    public function isFull(): bool {
        return $this->size === $this->capacity;
    }

    /**
     * Check if the queue is empty
     * @return bool
     */
    public function isEmpty(): bool {
        return $this->size === 0;
    }

    /**
     * Get the current number of elements
     * @return int
     */
    public function count(): int {
        return $this->size;
    }

    /**
     * Clear all elements from the queue
     * @return void
     */
    public function clear(): void {
        $this->buffer = [];
        $this->head = 0;
        $this->tail = 0;
        $this->size = 0;
    }

    /**
     * Get the maximum capacity
     * @return int
     */
    public function getCapacity(): int {
        return $this->capacity;
    }
}
