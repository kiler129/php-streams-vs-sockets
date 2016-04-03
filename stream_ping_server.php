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
        $this->stream = $stream;
        stream_set_blocking($stream, 0);
    }

    public function doRead()
    {
        $data = fread($this->stream, 8192);
        if (empty($data)) {
            $this->disconnect();

            return;
        }

        $this->readBuffer .= $data;
        $this->writeBuffer = 'PONG:' . $data;
    }

    public function disconnect()
    {
        unset($GLOBALS['clientsPool'][(int)$this->stream]);
        @fclose($this->stream);
    }

    public function doWrite()
    {
        $writtenBytes = fwrite($this->stream, $this->writeBuffer);
        $this->writeBuffer = substr($this->writeBuffer, $writtenBytes);
    }

    public function isWriteReady()
    {
        return ($this->writeBuffer !== '');
    }
}


$server = stream_socket_server('127.0.0.1:9999', $errNo, $errStr);
if ($server === false) {
    die("Failed to start stream server - $errNo, $errStr");
}

register_shutdown_function(
    function () use ($server) {
        fclose($server);
    }
);

/** @var serverClient[] $clientsPool */
$clientsPool = [];
while (1) {
    $read = [(int)$server => $server];
    $write = [];
    $expect = null;

    foreach ($clientsPool as $client) {
        $read[(int)$client->stream] = $client->stream;

        if ($client->isWriteReady()) {
            $write[(int)$client->stream] = $client->stream;
        }
    }

    stream_select($read, $write, $expect, null);

    foreach ($read as $stream) {
        if ($stream === $server) {
            $stream = stream_socket_accept($server, 0.5);
            $clientsPool[(int)$stream] = new serverClient($stream);

        } else {
            $clientsPool[(int)$stream]->doRead();
        }
    }

    foreach ($write as $stream) {
        $clientsPool[(int)$stream]->doWrite();
    }
}
