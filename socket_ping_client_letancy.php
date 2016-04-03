<?php
/**
 * This file is part of Streams vs. Socket experiment.
 * You are using it at your own risk and you are fully responsible for everything that code will do.
 *  
 * Copyright (c) 2016 Grzegorz Zdanowski <grzegorz@noflash.pl>
 *
 * For the full copyright and license information, please view the LICENSE file distributed with this source code.
 *
 */

define('TEST_CLIENTS', 20);
define('TEST_IP', '127.0.0.1');
define('TEST_PORT', 9999);
define('PACKETS_PER_CLIENT', 10000);

$globalLatencyTable = [];

class client
{
    public  $socket;
    private $lastSent       = null;
    private $writeBuffer    = "ping?";
    private $repliesCounter = 0;

    public function __construct($ip = '127.0.0.1', $port = 9999)
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->socket === false) {
            die("Failed to create socket - " . socket_strerror(socket_last_error()));
        }

        if (!socket_connect($this->socket, $ip, $port)) {
            die("Failed to connect - " . socket_strerror(socket_last_error()));
        }

        socket_set_nonblock($this->socket);
    }

    public function doRead()
    {
        $data = socket_read($this->socket, 8192);
        if (empty($data)) {
            $this->disconnect();

            return;
        }

        if ($this->lastSent !== null) { //Discard first packet since it contains connection time
            $GLOBALS['globalLatencyTable'][] = ((microtime(true) - $this->lastSent) * 1000);
            $this->repliesCounter++;
        }

        if ($this->repliesCounter === PACKETS_PER_CLIENT) {
            $this->disconnect();

            return;
        }

        $this->writeBuffer = 'ping?';
        $this->lastSent = microtime(true);
    }

    public function disconnect()
    {
        unset($GLOBALS['clientsPool'][(int)$this->socket]);
        @socket_close($this->socket);
    }

    public function doWrite()
    {
        $writtenBytes = socket_write($this->socket, $this->writeBuffer);
        $this->writeBuffer = substr($this->writeBuffer, $writtenBytes);
    }

    public function isWriteReady()
    {
        return ($this->writeBuffer !== '');
    }
}

echo "Spawning " . TEST_CLIENTS . " connections...\n";

/** @var client[] $clientsPool */
$clientsPool = [];
for ($i = 0; $i < TEST_CLIENTS; $i++) {
    $client = new client(TEST_IP, TEST_PORT);
    $clientsPool[(int)$client->socket] = $client;
}

echo "Starting test NOW\n";
while (!empty($clientsPool)) {
    $read = [];
    $write = [];
    $expect = null;

    foreach ($clientsPool as $client) {
        $read[(int)$client->socket] = $client->socket;

        if ($client->isWriteReady()) {
            $write[(int)$client->socket] = $client->socket;
        }
    }

    socket_select($read, $write, $expect, null);

    foreach ($read as $socket) {
        $clientsPool[(int)$socket]->doRead();
    }

    foreach ($write as $socket) {
        $clientsPool[(int)$socket]->doWrite();
    }
}

echo "Test finished.\n" . "\tUsed connections: " . TEST_CLIENTS . "\n" . "\tPackets per client: " . PACKETS_PER_CLIENT .
     "\n" . "\tPackets total: " . (PACKETS_PER_CLIENT * TEST_CLIENTS) . "\n" . "\tMin RTT: " .
     round(min($globalLatencyTable), 2) . "ms\n" . "\tMax RTT: " . round(max($globalLatencyTable), 2) . "ms\n" .
     "\tAvg RTT: " . round((array_sum($globalLatencyTable) / count($globalLatencyTable)), 2) . "ms\n";
