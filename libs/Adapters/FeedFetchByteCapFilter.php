<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

/**
 * Stream filter that stops passing data once a byte ceiling is exceeded.
 *
 * Used by BudgetedSocket so a hostile feed origin cannot force an unbounded
 * response into memory: past the cap no further data reaches the reader, the
 * truncated body fails XML parsing, and the socket read times out shortly
 * after (a catchable adapter exception instead of an uncatchable memory fatal).
 *
 * Receives the limit via php_user_filter::$params as array{limit: int}.
 */
final class FeedFetchByteCapFilter extends \php_user_filter
{
    private int $bytes = 0;

    private bool $capped = false;

    #[\Override]
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $limit = \is_array($this->params) ? (int) ($this->params['limit'] ?? 0) : 0;

        if ($this->capped) {
            // ponytail: swallow silently instead of PSFS_ERR_FATAL (which raises
            // warnings); the read times out and the truncated body fails parsing.
            return \PSFS_FEED_ME;
        }

        while ($bucket = \stream_bucket_make_writeable($in)) {
            $total = $this->bytes + $bucket->datalen;
            if ($limit > 0 && $total > $limit) {
                $allowed = \max(0, $limit - $this->bytes);
                if ($allowed > 0) {
                    // Truncate the writable bucket in place: read filters cannot
                    // fabricate buckets with stream_bucket_new().
                    $bucket->data = \substr($bucket->data, 0, $allowed);
                    $bucket->datalen = $allowed;
                    $this->bytes += $allowed;
                    $consumed += $allowed;
                    \stream_bucket_append($out, $bucket);
                }
                $this->capped = true;

                return \PSFS_PASS_ON;
            }
            $this->bytes += $bucket->datalen;
            $consumed += $bucket->datalen;
            \stream_bucket_append($out, $bucket);
        }

        return \PSFS_PASS_ON;
    }
}
