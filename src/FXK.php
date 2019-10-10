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
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\SeekException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Cache;


/**
 * Class FXK
 * @package Goodcatch\FXK
 */
class FXK
{

    const CACHE_KEY_CORPACCESSTOKEN_EXPIRESIN = 'fxiaoke_corpAccessToken_expiresIn';

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
     * @var array search criteria
     */
    private $criteria;

    /**
     * @var array corpAccessToken from connection with appId appSecret permanentCode
     */
    private $corpAccessToken = null;


    /**
     * Guanyi constructor.
     * @param array $config guanyi config
     */
    public function __construct(array $config)
    {
        $this->appId = $config ['appId'];
        $this->appSecret = $config ['appSecret'];
        $this->permanentCode = $config ['permanentCode'];
        $this->url = $config ['url'];

        $this->client = new Client([
            'timeout' => $config ['timeout'],
        ]);
    }

    /**
     * @param Client $client
     * @return FXK
     */
    public function setHttpClient (Client $client)
    {
        $this->client = $client;
        return $this;
    }


    public function query ()
    {
        $this->criteria = [];
        return $this;
    }

    /**
     * add criteria
     * @param string $search
     * @param $value
     * @return $this
     */
    public function criteria (string $search, $value)
    {
        if (isset ($this->criteria))
        {
            $this->criteria [$search] = $value;
        }
        return $this;
    }

    /**
     * @param Request $request
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function exec(Request $request): Model
    {
        $result = null;
        try {
            $response = $this->client->send($request);
            if (!is_null($response) && !empty ($response) && $response->getStatusCode() === 200) {
                $body = $response->getBody();

                $result = $this->handleResp(\GuzzleHttp\json_decode($body->getContents(), true));
            }
        } catch (RequestException $e) {
            $result = new Model;
            $result->exception = [urldecode(Psr7\str($e->getRequest()))];
            if ($e->hasResponse()) {
                $result->exception [] = urldecode(Psr7\str($e->getResponse()));
            }
        } catch (SeekException $e) {
            $result = new Model;
            $result->exception = [$e->getMessage()];
        }
        if (isset ($this->criteria))
        {
            unset ($this->criteria);
        }

        return $result;
    }

    /**
     * 模型转换
     *
     * @param array $result
     * @return Model
     */
    private function handleResp(array $result): Model
    {
        return new Model($result);
    }

    private function transform (Model $model, $collection):Model
    {
        $transform = new Model;

        $transform->data = $collection ?? \collect ([]);
        $transform->errorCode = $model->errorCode;
        $transform->errorMessage = $model->errorMessage;
        $transform->errorDescription = $model->errorDescription;

        // got error
        if (isset ($model->exception))
        {
            $transform->exception = $model->exception;
        }
        return $transform;
    }

    /**
     * make request
     *
     * @param string $method
     * @param array|null $req
     * @return Request
     */
    private function request(string $method, array $req = null): Request
    {
        if (is_null($req)) {
            $req = [];
        }
        if (isset ($this->criteria) && count ($this->criteria) > 0)
        {
            $req = array_merge ($this->criteria, $req);
        }

        return new Request('POST', $this->url . $method, [
            'Content-Type' => 'application/json;charset=utf-8',
        ], \GuzzleHttp\json_encode ($req));
    }

    /**
     * Get model by parameters
     *
     * @param string $method
     * @param array $params
     * @param int $page_no
     * @param int $page_size
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getModel (string $method, array $params = []): Model
    {

        return $this->exec (
            $this->request ($method, $params)
        );
    }

    /**
     * Get model by parameter key-value
     *
     * @param string $method
     * @param string|null $param_key
     * @param string|null $param_val
     * @param array $params
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getModelByParameter (string $method, string $param_key = null, string $param_val = null, array $params = []): Model
    {
        if (! is_null($param_val) && ! is_null ($param_key))
        {
            $params [$param_key] = $param_val;
        }
        return $this->getModel ($method, $params);
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function freshCorpAccessToken ()
    {
        if (is_null ($this->corpAccessToken))
        {
            $model = $this->getModel('corpAccessToken/get/V2', [
                'appId' => $this->appId,
                'appSecret' => $this->appSecret,
                'permanentCode' => $this->permanentCode
            ]);
            if ($model->errorCode == 0)
            {
                $this->corpAccessToken = $model->toArray ();
                Cache::put (self::CACHE_KEY_CORPACCESSTOKEN_EXPIRESIN, $this->corpAccessToken, $this->corpAccessToken ['expiresIn']);
            }
        } else if (! Cache::has (self::CACHE_KEY_CORPACCESSTOKEN_EXPIRESIN)) {
            $this->corpAccessToken = null;
            $this->getCorpAccessToken ();
        }
    }

    /**
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getCorpAccessToken (): array
    {
        $this->freshCorpAccessToken ();

        if (! is_null ($this->corpAccessToken)) {
            return [
                'corpId' => $this->corpAccessToken ['corpId'],
                'corpAccessToken' => $this->corpAccessToken ['corpAccessToken']
            ];
        }
        return [];
    }

    /**
     * 通讯录管理-获取部门列表
     * department/list
     *
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getDepartments (): Model
    {

        $model = $this->getModel('department/list', $this->getCorpAccessToken ());

        return $this->transform ($model, $model->departments);

    }

    /**
     * @param int $departmentId
     * @param boolean $fetchChild
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUsers (int $departmentId = 0, $fetchChild = true): Model
    {

        $accessToken = $this->getCorpAccessToken ();
        $this->query()
            ->criteria('fetchChild', $fetchChild)
            ->criteria('departmentId', $departmentId)
        ;

        $model = $this->getModel ('user/list', $accessToken);

        return $this->transform ($model, $model->userList);

    }

    /**
     * @param string $openUserId
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUser (string $openUserId = ''): Model
    {

        return $this->getModelByParameter ('user/get', 'openUserId', $openUserId, $this->getCorpAccessToken ());
    }

    /**
     * @param int $departmentId
     * @param string $mobile
     * @return Model
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getDepUserByMobile (int $departmentId = 0, string $mobile): Model
    {
        $model = $this->getUsers($departmentId);
        if ($model->data->count() > 0)
        {
            return $model->data
                ->filter (function ($item) use ($mobile) {
                    return $item->mobile === $mobile;
                })
                ->first ();
        }
        return null;
    }


}