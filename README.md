# PHP Streams vs. Sockets

### Brief history
PHP in general offers two ways for network communication - integrated [Streams](http://php.net/manual/en/book.stream.php) and [Sockets](http://php.net/manual/en/book.sockets.php) extension.
Streams are used allover internal PHP structure, offering rather high-level API including bells & whistles like easy encryption integration or contexts support used to deal with e.g. HTTP(s) traffic.
Sockets extension is an official extension which ships along with [PHP source code](https://github.com/php/php-src). It offers pinpoint precise control and doesn't offer builtin features available for streams, but open possibilities to do realize more complicated scenarios.

### Purpose of this experiment
Magic and easy of use always comes with a price of performance. While streams are usually easier to use underlying `C` code is much more complicated, so in **theory** simpler socket extension should be faster.

### Results summary
*Coming soon.*
TL;DR: Sockets are marginally faster in ping test (many small packets), but provides ~35% increase in large volume data throughput.

### I want to replicate your results
Sure, you can - that's why all the source code was shared.

**Ping**
In order to execute this test you need to run appropriate server file first and than execute client file.
Example:
![example](http://i.imgur.com/koQb6dv.png)
You can of course mix stream server with socket client and vice versa.

**Data transfer**
Since it's worthless to reinvent the wheel for data transfer PHP is only a server. For client it's best to use [`wget`](https://www.gnu.org/software/wget/).
While server files aren't complaint with HTTP `wget` will accept them and treat as `HTTP/0.9` ;)
I don't recommend using `netcat` and `pv` combination - you'll get very low performance (tops around 240MB/s on my machine).

### So when to use which implementation?
If you're asking such question you should probably stick with streams. While switching to sockets may gain some performance in specific scenarios it may also decrease it dramatically if you one day decide to implement e.g. encryption.
I started being interested in sockets while looking for method to transfer opened network socket to another application ([some details about such scenario C](http://stackoverflow.com/questions/12425067/socket-handle-transfer-between-independent-processes)).

### It's **NOT** example how to use streams or sockets
While code in this repository contains implementation of server and client using both streams & sockets you **should not** treat it as an example *"how to built servers in PHP"*. It uses `$GLOBALS`, lacks proper error handling, breaks PSR rules etc. It was created solely for benchmark purposes and nothing else!
