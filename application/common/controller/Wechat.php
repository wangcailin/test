<?php
/**
 * Created by PhpStorm.
 * User: wangcailin
 * Date: 2018/4/17
 * Time: 下午3:22
 */


namespace app\common\controller;

use think\Controller;
use think\Db;

class Wechat extends Controller
{
    public $appid;
    public $secret;
    public $jsTicket;
    public $accessToken;
    public $jssdk;

    public function _initialize()
    {
        parent::_initialize();

        $this->appid        = config('site.wechat_appid');
        $this->secret       = config('site.wechat_appsecret');
        $this->jsTicket     = $this->getJsApiTicket();
        $this->accessToken  = $this->getAccessToken();



        if (!session('url')){
            $request = request();
            $url = 'http://'.$_SERVER['SERVER_NAME'].$request->url();
            session('url', $url);
        }

        $code = empty($_REQUEST['code']) ? '' : $_REQUEST['code'];

        if(!empty($_GET['mei_openid'])){
            session('openid', $_GET['mei_openid']);
        }
        if(!$code && !session('openid')){
            header("Location:https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$this->appid."&redirect_uri=".session('url')."&response_type=code&scope=snsapi_base&state=1#wechat_redirect");
            exit;
        }
        if($code){
            $access_token_url   = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$this->appid."&secret=".$this->secret."&code=".$code."&grant_type=authorization_code";
            $data               = get_url_json($access_token_url);
            session('openid', $data['openid']);
        }
        $this->jssdk                = $this->GetSignPackage($this->jsTicket);
        $modelName                  = ltrim(strstr(request()->controller(), '.'), '.');
        $this->jssdk['jsapilist']   = model('ProjectModule')->where('name', $modelName)->value('jsapilist');

        $this->assign('jssdk',$this->jssdk);
        $this->assign('openid',session('openid'));

    }

    /**
     * 创建jssdk
     * @param $jsApiTicket
     * @return array
     */
    private function getSignPackage($jsApiTicket) {
        $url            = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $timestamp      = time();
        $nonceStr       = $this->createNonceStr();
        $string         = "jsapi_ticket=".$jsApiTicket."&noncestr=".$nonceStr."&timestamp=".$timestamp."&url=".$url;
        $signature      = sha1($string);
        $signPackage    = [
            "appId"     => $this->appid,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        ];
        return $signPackage;
    }

    private function createNonceStr($length = 16) {
        $chars  = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str    = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    private function getAccessToken()
    {
        $token = null;
        $config = config('site.wechat_type');
        switch ($config['wechat_type']){
            case '0':
                $sqlObg = [
                    'type'      => 'mysql',
                    'hostname'  => $config['wechat_sql_hostname'],
                    'database'  => $config['wechat_sql_dbname'],
                    'username'  => $config['wechat_sql_username'],
                    'password'  => $config['wechat_sql_password'],
                ];
                $token = $this->getSqlAccessToken($sqlObg, $config['wechat_sql_token_table'], $config['wechat_sql_token_where'], $config['wechat_sql_token_field'], $config['wechat_sql_token_overtime']);
                break;
            case '1':
                break;
            case '2':
                break;
        }
        return $token;
    }

    private function getJsApiTicket()
    {
        $ticket = null;
        $config = config('site');
        switch ($config['wechat_type']){
            case '0':
                $sqlObg = [
                    'type'      => 'mysql',
                    'hostname'  => $config['wechat_sql_hostname'],
                    'database'  => $config['wechat_sql_dbname'],
                    'username'  => $config['wechat_sql_username'],
                    'password'  => $config['wechat_sql_password'],
                ];
                $ticket = $this->getSqlJsApiTicket($sqlObg, $config['wechat_sql_ticket_table'], $config['wechat_sql_ticket_where'], $config['wechat_sql_ticket_field'], $config['wechat_sql_ticket_overtime']);
                break;
            case '1':
                break;
            case '2':
                break;
        }
        return $ticket;
    }

    /**
     * 获取SQL数据库accessToken
     * @param $mysqlObj[
    'type'      => 'mysql',
    'hostname'  => '10.66.244.169',
    'database'  => 'evonik',
    'username'  => 'root',
    'password'  => 'BlueD0t@2o1&zy',
    ]
     * @param $table            表名
     * @param $where            条件
     * @param $tokenField       token字段
     * @param $tokenOvertime    token过期时间
     * @return mixed            accessToken
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    protected function getSqlAccessToken($mysqlObj,$table,$where, $tokenField, $tokenOvertime)
    {
        $tokenTable = Db::connect($mysqlObj);
        $tokenInfo  = $tokenTable->table($table)
            ->where($where)
            ->find();

        if(time()>intval($tokenInfo[$tokenOvertime])-3600 || !$tokenInfo[$tokenField]){
            $curl = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->secret;
            $tokenData = get_url_json($curl);;

            $data[$tokenField]      = $tokenData['access_token'];
            $data[$tokenOvertime]    = time()+6000;
            $data['type']       = 1;
            if($tokenInfo){
                if($tokenData['access_token']){
                    $tokenTable->table($table)
                        ->where($where)
                        ->update($data);
                }
            }else{
                $tokenTable->table($table)
                    ->where($where)
                    ->insert($data);
            }
            $token = $tokenData['access_token'];
        }else{
            $token = $tokenInfo['token'];
        }
        $tokenTable->close();
        return $token;
    }

    /**
     * 获取SQLjsTicket
     * @param $mysqlObj
     * @param $table
     * @param $where
     * @param $ticketField
     * @param $ticketOvertime
     * @return mixed
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    private function getSqlJsApiTicket($mysqlObj, $table, $where, $ticketField, $ticketOvertime) {
        $tokenTable = Db::connect($mysqlObj);
        $tokenInfo  = $tokenTable->table($table)
            ->where($where)
            ->find();
        if (time()>intval($tokenInfo[$ticketOvertime]) || !$tokenInfo[$ticketField]) {
            $token  = $this->getAccessToken();
            $url    = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$token;
            $res    = get_url_json($url);
            $ticket = $res['ticket'];
            if ($ticket) {
                $data['token']      = $ticket;
                $data['outtime']    = time()+7000;
                $data['type']       = 2;
                if($tokenInfo){
                    $tokenTable->table($table)
                        ->where($where)
                        ->update($data);
                }else{
                    $tokenTable->table($table)
                        ->where($where)
                        ->insert($data);
                }
            }
        } else {
            $ticket = $tokenInfo['token'];
        }
        $tokenTable->close();
        return $ticket;
    }

    /**
     * 获取File accessToken
     */
    protected function getFileAccessToken($tokenDir, $tokenField, $tokenOvertime)
    {
        if(time()-filemtime($tokenDir)>1200||file_get_contents($tokenDir)==''){
            $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->secret;
            $res = get_url_json($url);
            $token = $res['access_token'];
            if ($token) {
                $data[$tokenOvertime]   = time() + 7000;
                $data[$tokenField]      = $token;
                $fp = fopen($tokenDir, "w");
                fwrite($fp, json_encode($data));
                fclose($fp);
            }
        }else{
            $token = file_get_contents($tokenDir);
        }
        return $token;
    }

    /**
     * 获取File jsTicket
     */
    protected function getFileJsApiTicket($ticketDir, $ticketField, $ticketOvertime, $tokenDir) {
        $data = json_decode(file_get_contents($ticketDir), true);
        if ($data[$ticketOvertime] < time()) {
            $accessToken = $this->getAccessToken($tokenDir);
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$accessToken;
            $res = get_url_json($url);
            $ticket = $res['ticket'];
            if ($ticket) {
                $data[$ticketOvertime]  = time() + 7000;
                $data[$ticketField] = $ticket;
                $fp = fopen($ticketDir, "w");
                fwrite($fp, json_encode($data));
                fclose($fp);
            }
        } else {
            $ticket = $data[$ticketField];
        }
        return $ticket;
    }
}