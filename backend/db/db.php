<?php
define("db_host", "localhost");
define("db_user", "devuser");
define("db_pass", "DevPass123!");
define("db_name", "felamo");


// define("db_host", "localhost");
// define("db_user", "u240756803_felamov3");
// define("db_pass", "hehcE6-fotcab-viskaj");
// define("db_name", "u240756803_felamov3");

class db_connect
{
    public $host = db_host;
    public $user = db_user;
    public $pass = db_pass;
    public $name = db_name;
    public $conn;
    public $error;
    public $mysqli;

    public function __construct()
    {
        $this->connect();
    }

    public function connect()
    {
        try {
            $this->conn = new mysqli($this->host, $this->user, $this->pass, $this->name);
    
            // Check connection
            if ($this->conn->connect_error) {
                $this->error = "Connection failed: " . $this->conn->connect_error;
                return false;
            } else {
                return $this->conn;
            }
        } catch (\Throwable $th) {
            $this->error = "Connection error: " . $th->getMessage();
            return false;
        }
    }
    
}
