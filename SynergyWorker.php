<?php

require_once 'AbstractWorker.php';
require_once 'Http.php';

class SynergyWorker extends AbstractWorker
{
    /**
     * @var Http
     */
    protected $http;
    protected $response;
    protected $lastStart;


    protected function init()
    {
        $this->http = new Http('https://syn.su/testwork.php');
        if ($answer = $this->http->post(['method' => 'get'])) {
            $json = json_decode($answer, true);
            $this->response = $json['response'];
            $this->log('message = ' . $answer);
        } else {
            $this->log('Не получил ответ от апи');
            $this->_shutdown();
        }
    }
    protected function job()
    {
        if (date('i') == '06' && $this->lastStart != date('H')) {
            $this->lastStart = date('H');
            $hash = $this->encrypt_xor($this->response['message'], $this->response['key']);

            $this->log('xor = ' . $hash);
            $out = $this->http->post(['method' => 'update', 'message' => $hash]);
            $this->log('Answer = ' . $out);
        }
        return true;
    }

    protected function encrypt_xor($text, $key)
    {
        $this->log($text . ' = ' . $key);
        $out = '';
        for ($i = 0; $i < strlen($text);) {
            for ($j=0;$j<strlen($key);$j++, $i++) {
                $out .= $text{$i} ^ $key{$j};
            }
        }
        return base64_encode($out);
    }
}

(new SynergyWorker())->run();
