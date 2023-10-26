<?php

class CallMediaTest
{

    const RABBIT_EXCHANGE = 'urls';
    const RABBIT_QUEUE = 'urls';

    /**
     *
     * @var array
     */
    private $config;
    private $mysql;

    /**
     *
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    private $rabbit;

    /**
     *
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    private $rabbitChannel;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function initRabbit()
    {
        $this->rabbit = new \PhpAmqpLib\Connection\AMQPStreamConnection('rabbitmq', 5672, $this->config['rabbit']['user'], $this->config['rabbit']['psw']);
        $this->rabbitChannel = $this->rabbit->channel();
        $this->rabbitChannel->exchange_declare(self::RABBIT_EXCHANGE, 'direct');
        $this->rabbitChannel->queue_declare(self::RABBIT_QUEUE, false, true, false);
        $this->rabbitChannel->queue_bind(self::RABBIT_QUEUE, self::RABBIT_EXCHANGE);
    }

    public function pushToRabbit($text)
    {
        $msgArray = [
            'text' => $text,
            'time' => [
                'h' => date('H'),
                'm' => date('i'),
                's' => date('s'),
            ],
        ];
        $message = new \PhpAmqpLib\Message\AMQPMessage(json_encode($msgArray), [
            'content-type' => 'application/json',
            'delivery_mode' => \PhpAmqpLib\Message\AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ]);
        $this->rabbitChannel->basic_publish($message, self::RABBIT_EXCHANGE);
    }

    public function listener()
    {
        $this->rabbitChannel->basic_consume(
                self::RABBIT_QUEUE,
                '',
                false,
                false,
                false,
                false,
                [$this, 'processMessage']
        );
        while ($this->rabbitChannel->is_consuming()) {
            $this->rabbitChannel->wait();
        }
    }

    /**
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function processMessage(\PhpAmqpLib\Message\AMQPMessage $message)
    {
        if (json_decode($message->getBody())->text == 'stop') {
            $message->getChannel()->basic_cancel($message->getConsumerTag());
            $this->stat();
        } else {
            $this->pushToMySql($message);
//            $this->pushToClickHouse($message);
        }
        $message->ack();
    }

    public function initMySql()
    {
        $this->mysql = mysqli_connect($this->config['mariadb']['host'], $this->config['mariadb']['user'], $this->config['mariadb']['psw'], $this->config['mariadb']['schema']);
        $query = 'CREATE DATABASE IF NOT EXISTS ' . $this->config['mariadb']['schema'];
        mysqli_query($this->mysql, $query);
        $query = 'DROP TABLE IF EXISTS ' . $this->config['mariadb']['schema'] . '.`url`';
        mysqli_query($this->mysql, $query);
        $query = 'CREATE TABLE IF NOT EXISTS ' . $this->config['mariadb']['schema'] . '.`url` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `url` varchar(512) NOT NULL,
  `length` int(11) NOT NULL,
  `hour` int(11) NOT NULL,
  `minute` int(11) NOT NULL,
  `second` int(11) NOT NULL
) ENGINE=InnoDB';
        mysqli_query($this->mysql, $query);
    }

    public function close()
    {
        if ($this->rabbitChannel) {
            $this->rabbitChannel->close();
            if ($this->rabbit) {
                $this->rabbit->close();
            }
        }
    }

    public function pushToMySql(\PhpAmqpLib\Message\AMQPMessage $message)
    {
        $body = $message->getBody();
        $msgArray = json_decode($body);
        $size = strlen($msgArray->text);
        $query = 'INSERT INTO ' . $this->config['mariadb']['schema'] . '.url (`url`, `length`, `hour`, `minute`, `second`) VALUES '
                . '("' . $msgArray->text . '", ' . $size . ', ' . $msgArray->time->h . ', ' . $msgArray->time->m . ', ' . $msgArray->time->s . ')';
        mysqli_query($this->mysql, $query);
    }

//    public function initClickHouse()
//    {
//
//    }
//
//    public function pushToClickHouse(\PhpAmqpLib\Message\AMQPMessage $message)
//    {
//
//    }

    protected function stat()
    {
        $this->statMysql();
//        $this->statClickHouse();
    }

    protected function statMysql()
    {
        $query = 'SELECT
    COUNT(*) AS `number`,
    CONCAT(`hour`, ":", `minute`) AS `minute`,
    AVG(`length`) AS `size`,
    MIN(`second`) AS `first`,
    MAX(`second`) AS `last`
FROM `url`
GROUP BY `hour`, `minute`
ORDER BY `hour`, `minute`';
        $r = mysqli_query($this->mysql, $query);
        echo "==================================================================\n";
        echo "number\tminute\tsize\tfirst\tlast\n";
        echo "==================================================================\n";
        while ((($row = mysqli_fetch_array($r)) !== false) && !is_null($row)) {
            echo $row['number'] . "\t" . $row['minute'] . "\t" . $row['size'] . "\t" . $row['first'] . "\t" . $row['last'] . "\n";
        }
    }

//    protected function statClickHouse()
//    {
//
//    }
}
