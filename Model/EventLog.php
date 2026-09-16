<?php
declare(strict_types=1);

namespace BriizPay\PayByBank\Model;

use Magento\Framework\App\ResourceConnection;

/**
 * Which webhook events have already been applied.
 *
 * Delivery is at-least-once, so the same event arrives again after any failure.
 * The event id is the table's primary key: a second insert of the same id fails
 * on the key, which is the duplicate check and the record in one statement, with
 * no read-then-write window for two concurrent deliveries to slip through.
 */
class EventLog
{
    private const TABLE = 'briizpay_webhook_event';

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    /** @return bool true the first time an event id is seen, false for a repeat */
    public function claim(string $eventId): bool
    {
        $connection = $this->resource->getConnection();
        try {
            $connection->insert(
                $this->resource->getTableName(self::TABLE),
                ['event_id' => substr($eventId, 0, 64)]
            );
            return true;
        } catch (\Throwable $e) {
            if ($this->isDuplicateKey($e)) {
                return false;
            }
            throw $e;
        }
    }

    /** MySQL error 1062, SQLSTATE 23000, however the adapter wraps it. */
    private function isDuplicateKey(\Throwable $e): bool
    {
        for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof \Magento\Framework\DB\Adapter\DuplicateException) {
                return true;
            }
            $message = $cause->getMessage();
            if (str_contains($message, '1062') || str_contains($message, 'Duplicate entry') || (string) $cause->getCode() === '23000') {
                return true;
            }
        }
        return false;
    }

    /** Give an event back when applying it failed, so the retry is not skipped. */
    public function release(string $eventId): void
    {
        $connection = $this->resource->getConnection();
        $connection->delete(
            $this->resource->getTableName(self::TABLE),
            ['event_id = ?' => substr($eventId, 0, 64)]
        );
    }
}
