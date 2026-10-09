<?php

declare(strict_types=1);

namespace SimpleNewsletter\Adapters;

/**
 * Stream filter that stops passing data once a byte ceiling is exceeded or a
 * total-duration deadline passes.
 *
 * Used by BudgetedSocket so a hostile feed origin can neither force an
 * unbounded response into memory nor trickle bytes forever: past either
 * budget no further data reaches the reader, the blocked read trips the
 * socket idle timeout, and the truncated body fails XML parsing (a
 * catchable adapter exception instead of an uncatchable memory fatal or a
 * worker held open indefinitely).
 *
 * Receives its budget via php_user_filter::$params as
 * array{limit: int, deadline: float} (deadline 0 disables the time budget).
 */
final class FeedFetchByteCapFilter extends \php_user_filter
{
    private int $bytes = 0;

    private bool $capped = false;

    #[\Override]
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        $params = \is_array($this->params) ? $this->params : [];
        $limit = (int) ($params['limit'] ?? 0);
        $deadline = (float) ($params['deadline'] ?? 0.0);

        if ($this->capped || ($deadline > 0.0 && \microtime(true) > $deadline)) {
            // Total-duration budget, evaluated on every bucket so an origin
            // trickling bytes (each read inside the socket idle timeout)
            // cannot outlive the connect-time budget.
            $this->capped = true;
            // ponytail: swallow silently instead of PSFS_ERR_FATAL (which raises
            // warnings) and drain the input brigade — leftover buckets would
            // otherwise raise a stream-layer warning on the next read.
            while ($bucket = \stream_bucket_make_writeable($in)) {
                $consumed += $bucket->datalen;
            }

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
