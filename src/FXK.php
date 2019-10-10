<?php

/*
 *
 *
 * (c) Allen, Li <morningbuses@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Goodcatch\FXK;

use GuzzleHttp\Client;


/**
 * Class U9WS
 * @package Goodcatch\U9WS
 */
class FXK
{

    /**
     * https://open.fxiaoke.com/cgi/
     *
     * @var mixed API URL
     */
    private $url;

    /**
     * @var mixed app key
     */
    private $appId;

    /**
     * @var mixed app secret
     */
    private $appSecret;

    /**
     * @var mixed permanent code
     */
    private $permanentCode;

    /**
     * @var Client http client
     */
    private $client;


    /**
     * Guanyi constructor.
     * @param array $config guanyi config
     */
    public function __construct(array $config)
    {
        $this->key = $config ['appkey'];
        $this->secret = $config ['appsecret'];
        $this->session = $config ['sessionkey'];
        $this->url = $config ['url'];

        $this->client = new Client([
            'timeout' => $config ['timeout'],
        ]);
    }

    /**
     * @param Client $client
     * @return Guanyi
     */
    public function setHttpClient (Client $client)
    {
        $this->client = $client;
        return $this;
    }
}