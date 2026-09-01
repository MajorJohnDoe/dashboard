<?php
namespace Dashboard\Core;

use mysqli;
use Exception;
use RuntimeException;
use Dashboard\Core\Interfaces\DatabaseInterface;

/**
 * Thrown when a query fails to prepare or execute.
 */
class DatabaseException extends RuntimeException
{
}

/**
 * Database access wrapper around mysqli.
 *
 * All queries go through q() which uses prepared statements exclusively.
 * Errors are thrown as DatabaseException instead of being silently swallowed,
 * so failures surface immediately with a clear message instead of cascading
 * into confusing downstream behaviour.
 */
class Database implements DatabaseInterface {
    protected mysqli $_mysqli;
    protected bool $debug;
    protected string $errorString = '';

    public function __construct(string $host, string $username, string $password, string $database, bool $debug = false)
    {
        $this->debug = $debug;

        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $this->_mysqli = new mysqli($host, $username, $password, $database);
            $this->_mysqli->set_charset('utf8mb4');
        } catch (Exception $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Execute a prepared statement.
     *
     * Return values by query kind:
     *  - SELECT (result set exists): array of associative rows (empty array if none)
     *  - INSERT/UPDATE/DELETE/etc.: int affected row count
     *
     * @param string $query  SQL with ? placeholders
     * @param string $types  mysqli bind types string ('i', 's', 'd', 'b'), '' for no params
     * @param mixed  ...$params bound parameter values
     * @return array<int,array<string,mixed>>|int
     * @throws DatabaseException on prepare/execute failure
     */
    public function q($query, $types = "", ...$params)
    {
        try {
            $stmt = $this->_mysqli->prepare($query);
        } catch (Exception $e) {
            $this->logError('Prepare failed: ' . $e->getMessage(), $query);
            throw new DatabaseException('Query prepare failed: ' . $e->getMessage(), 0, $e);
        }

        // Bind parameters when provided. mysqli_stmt::execute() accepts the
        // params array directly (PHP 7.1+), no bind_param reference hack needed.
        try {
            if ($params !== []) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
        } catch (Exception $e) {
            $stmt->close();
            $this->logError('Execute failed: ' . $e->getMessage(), $query);
            throw new DatabaseException('Query execute failed: ' . $e->getMessage(), 0, $e);
        }

        // Determine whether this statement produces a result set.
        $meta = $stmt->result_metadata();
        if ($meta === false) {
            // No result set: INSERT/UPDATE/DELETE/DDL — return affected rows.
            $affected = $stmt->affected_rows;
            $stmt->close();
            return $affected;
        }
        $meta->close();

        // Result set: fetch all rows as associative arrays.
        $result = $stmt->get_result();
        $rows = $result !== false ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();

        return $rows;
    }

    // Start a transaction
    public function beginTransaction(): void {
        $this->_mysqli->begin_transaction();
    }

    // Commit a transaction
    public function commit(): void {
        $this->_mysqli->commit();
    }

    // Roll back a transaction
    public function rollback(): void {
        $this->_mysqli->rollback();
    }

    public function handle(): mysqli {
        return $this->_mysqli;
    }

    public function lastInsertId(): int
    {
        return $this->_mysqli->insert_id;
    }

    public function getError(): string
    {
        return $this->errorString;
    }

    private function logError(string $message, string $query = ''): void
    {
        $this->errorString = $message;
        error_log('Database error: ' . $message . ' | Query: ' . $query);
    }
}
