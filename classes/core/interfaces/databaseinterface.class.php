<?php
namespace Dashboard\Core\Interfaces;

interface DatabaseInterface
{
    public function q($query, $types = "", ...$params);
    public function beginTransaction();
    public function commit();
    public function rollback();
    public function lastInsertId();
    public function getError(): string;
}
?>