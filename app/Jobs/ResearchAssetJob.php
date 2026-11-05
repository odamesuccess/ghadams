<?php

namespace App\Jobs;

use App\Exceptions\AssetResearchException;
use App\Models\Asset;
use App\Models\AssetSuggestion;
use App\Services\AssetResearchService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs AI research for one asset and stores the result as a pending AssetSuggestion.
 * It never changes the asset itself.
 */
class ResearchAssetJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Up to two API calls of 120s each, plus overhead. Keep DB_QUEUE_RETRY_AFTER above this. */
    public int $timeout = 300;

    public bool $failOnTimeout = true;

    /** Only one research job per asset and mode can be queued or running at a time. */
    public int $uniqueFor = 900;

    public function __construct(
        public int $assetId,
        public string $mode = AssetSuggestion::MODE_FULL,
        public ?int $requestedBy = null,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->assetId}:{$this->mode}";
    }

    /**
     * Seconds to wait before retry 2 and retry 3.
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(AssetResearchService $service): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            return;
        }

        try {
            $result = $service->research($asset, $this->mode);
        } catch (AssetResearchException $e) {
            if ($e->retryable && $this->attempts() < $this->tries) {
                $this->release($e->retryAfter ?? $this->backoff()[$this->attempts() - 1] ?? 60);

                return;
            }

            Log::warning('Asset research failed', [
                'asset_id' => $this->assetId,
                'mode' => $this->mode,
                'error' => $e->getMessage(),
                'errors' => $e->errors,
            ]);

            $this->recordFailure($e->getMessage());

            return;
        }

        AssetSuggestion::create([
            'asset_id' => $asset->id,
            'mode' => $this->mode,
            'payload' => $result['payload'],
            'sources' => $result['sources'],
            'confidence' => $result['confidence'],
            'status' => AssetSuggestion::STATUS_PENDING,
            'retrieved_at' => $result['retrieved_at'],
            'requested_by' => $this->requestedBy,
        ]);
    }

    /**
     * Called when the job crashes or times out on its last attempt.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Asset research job crashed', [
            'asset_id' => $this->assetId,
            'mode' => $this->mode,
            'exception' => $exception?->getMessage(),
        ]);

        $this->recordFailure($exception instanceof AssetResearchException
            ? $exception->getMessage()
            : 'Research failed unexpectedly or timed out. Please try again later.');
    }

    protected function recordFailure(string $message): void
    {
        if (! Asset::whereKey($this->assetId)->exists()) {
            return;
        }

        AssetSuggestion::create([
            'asset_id' => $this->assetId,
            'mode' => $this->mode,
            'status' => AssetSuggestion::STATUS_PENDING,
            'error_message' => mb_substr($message, 0, 1000),
            'requested_by' => $this->requestedBy,
        ]);
    }
}
