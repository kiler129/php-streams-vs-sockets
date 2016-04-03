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

class serverClient
{
    public  $socket;
    private $readBuffer  = '';
    private $writeBuffer = '';

    public function __construct($stream)
    {
        $this->socket = $stream;
        socket_set_nonblock($stream);
    }

    public function doRead()
    {
        $data = socket_read($this->socket, 8192);
        if (empty($data)) {
            $this->disconnect();

            return;
        }

        $this->readBuffer .= $data;
        $this->writeBuffer = 'PONG:' . $data;
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


$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if ($server === false) {
    die("Failed to create socket - " . socket_strerror(socket_last_error()));
}

if (!socket_bind($server, '127.0.0.1', 9999)) {
    die("Failed to bind socket - " . socket_strerror(socket_last_error()));
}


if (!socket_listen($server)) {
    die("Failed to listen on socket - " . socket_strerror(socket_last_error()));
}

register_shutdown_function(
    function () use ($server) {
        socket_close($server);
    }
);

/** @var serverClient[] $clientsPool */
$clientsPool = [];
while (1) {
    $read = [(int)$server => $server];
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
        if ($socket === $server) {
            $socket = socket_accept($server);
            $clientsPool[(int)$socket] = new serverClient($socket);

        } else {
            $clientsPool[(int)$socket]->doRead();
        }
    }

    foreach ($write as $socket) {
        $clientsPool[(int)$socket]->doWrite();
    }
}
