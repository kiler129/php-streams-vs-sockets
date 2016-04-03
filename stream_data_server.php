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

error_reporting(E_ERROR | E_WARNING | E_PARSE); //fwrite will emit E_NOTICE if some data were left on buffer

define('PACKET_LEN', 65536);

class serverClient
{
    public  $socket;
    private $testPacket = '';

    public function __construct($stream)
    {
        $this->stream = $stream;
        stream_set_blocking($stream, 0);

        $this->testPacket = str_repeat('a', PACKET_LEN);
    }

    public function doRead()
    {
        $data = fread($this->stream, 8192);

        if (empty($data)) { //Normal disconnect
            $this->disconnect();

            return;
        }
    }

    public function disconnect()
    {
        unset($GLOBALS['clientsPool'][(int)$this->stream]);
        @fclose($this->stream);
    }

    public function doWrite()
    {
        fwrite($this->stream, $this->testPacket);
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
        $write[(int)$client->stream] = $client->stream;
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
        if (isset($clientsPool[(int)$stream])) {
            $clientsPool[(int)$stream]->doWrite();
        }
    }
}
