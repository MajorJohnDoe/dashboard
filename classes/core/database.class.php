<?php
namespace Dashboard\Core;

use mysqli;
use Exception;
use Dashboard\Core\Interfaces\DatabaseInterface;

class Database implements DatabaseInterface {
    protected $_mysqli;
    protected $_debug;
    protected $_error_string;
    protected $_error_backtrace;
    protected $sql_thread_id;
 
    public function __construct(string $host, string $username, string $password, string $database, bool $debug = false)
    {
        $this->_debug = $debug;
        
        try {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            $this->_mysqli = new mysqli($host, $username, $password, $database);
            
            // Set charset to utf8mb4
            $this->_mysqli->set_charset("utf8mb4");
            
            error_log("Database connection successful");
        } catch (Exception $e) {
            error_log("Database connection failed: " . $e->getMessage());
            $this->handleException($e);
        }
    }
 
    public function q($query, $types = "", ...$params)
    {
    	$incoming_query = $query;
        if ($query = $this->_mysqli->prepare($query))
        {
            if (func_num_args() > 1)
            {
                $x = func_get_args();
                $args = array_merge(array(func_get_arg(1)),
                    array_slice($x, 2));
                $args_ref = array();
                foreach($args as $k => &$arg) {
                    $args_ref[$k] = &$arg; 
                }
                call_user_func_array(array($query, 'bind_param'), $args_ref);
            }

			// Execute Query
            $query->execute();
            	
            if ($query->errno)
            {
              if ($this->_debug)
              {
                echo mysqli_error($this->_mysqli);
                debug_print_backtrace();
              }
              
              $this->_error_string .= mysqli_error($this->_mysqli);
              //$this->_error_backtrace = $this->debug_string_backtrace();
              
              return false;
            }
 
 			// if ($query->sqlstate == "00000") {
 			if ($query->affected_rows > -1)
 			{
                return $query->affected_rows;
            }
            $params = array();
            $meta = $query->result_metadata();
            if ($meta) { // Always check if there's metadata to prevent errors
                $row = array(); // Initialize $row here
                while ($field = $meta->fetch_field()) {
                    $row[$field->name] = null; // Initialize each field with null
                    $params[] = &$row[$field->name]; // Now each $row element is correctly referenced
                }

                call_user_func_array(array($query, 'bind_result'), $params);
            
                $result = array();
                while ($query->fetch()) {
                    $r = array();
                    foreach ($row as $key => $val) {
                        $r[$key] = $val; // Copy each field in $row to $r
                    }
                    $result[] = $r;
                }
                $query->close();
                return $result;
            } else {
                $query->close(); // Close the query if no metadata, might indicate a non-select query
                return array();
            }
        }
        else
        {
            if ($this->_debug)
            {
                echo $this->_mysqli->error;
                debug_print_backtrace();
            }
            
            $this->_error_string .= $this->_mysqli->error;
            //$this->_error_backtrace = $this->debug_string_backtrace();
            
            return false;
        }
    }
 
    // Start a transaction
    public function beginTransaction() {
        $this->_mysqli->autocommit(FALSE);  // Turn off auto-committing database modifications
    }

    // Commit a transaction
    public function commit() {
        $this->_mysqli->commit();
        $this->_mysqli->autocommit(TRUE);   // Turn back on auto-committing
    }

    // Roll back a transaction
    public function rollback() {
        $this->_mysqli->rollback();
        $this->_mysqli->autocommit(TRUE);   // Turn back on auto-committing
    }

    public function handle() {
        return $this->_mysqli;
    }
    
	public function lastInsertId()
	{
		return $this->_mysqli->insert_id;
	}
	
    public function getError(): string
    {
        return $this->_error_string;
    }

    private function handleException(Exception $e): void
    {
        $this->_error_string = $e->getMessage();
        if ($this->_debug) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
            throw $e; // Re-throw the exception in debug mode
        }
    }
	
	public function getErrorBacktrace()
	{
		return $this->_error_backtrace;
		//return var_dump($e->getTraceAsString());
	}
	
	function debug_string_backtrace()
	{
		ob_start();
		debug_print_backtrace();
		$trace = ob_get_contents();
		ob_end_clean();
	
		// Remove first item from backtrace as it's this function which
		// is redundant.
		$trace = preg_replace ('/^#0\s+' . __FUNCTION__ . "[^\n]*\n/", '', $trace, 1);
	
		// Renumber backtrace items.
		$trace = preg_replace ('/^#(\d+)/me', '\'#\' . ($1 - 1)', $trace);
	
		return $trace;
	}
	
	public function killthread()
	{
		$this->_mysqli->kill($this->sql_thread_id);
	}
}
?>
