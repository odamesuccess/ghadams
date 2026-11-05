<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Raised when AI asset research cannot produce a usable suggestion.
 * Messages are safe to show to users (they never contain the API key).
 */
class AssetResearchException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = false,
        public readonly ?int $retryAfter = null,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function notConfigured(): self
    {
        return new self('AI research is not configured. Set ANTHROPIC_API_KEY in the server environment.');
    }

    public static function unsupported(string $reason): self
    {
        return new self($reason);
    }

    public static function apiError(int $status, string $message, ?int $retryAfter = null): self
    {
        $retryable = $status === 408 || $status === 429 || $status >= 500;

        return new self("AI service error ({$status}): {$message}", $retryable, $retryAfter);
    }

    public static function connectionFailed(Throwable $previous): self
    {
        return new self('Could not reach the AI service (timeout or network error).', true, null, [], $previous);
    }

    public static function refused(?string $category): self
    {
        return new self('The AI declined this request'.($category ? " ({$category})." : '.'));
    }

    public static function truncated(): self
    {
        return new self('The AI response was cut off before it finished.', true);
    }

    public static function invalidJson(): self
    {
        return new self('The AI did not return valid JSON.', true);
    }

    public static function invalidSchema(array $errors): self
    {
        return new self('The AI response did not match the expected format.', true, null, $errors);
    }
}
