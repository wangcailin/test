<?php
/**
 * Created by PhpStorm.
 * User: wangcailin
 * Date: 2018/4/17
 * Time: 下午3:22
 */


namespace app\index\controller;

use think\Controller;
use think\Db;

class Wechat extends Controller
{
    public $appid;
    public $secret;
    public $accessToken;
    public $jsTicket;
    public $jssdk;

    public function _initialize()
    {
        parent::_initialize();

        $this->appid        = config('site.wechat_appid');
        $this->secret       = config('site.wechat_appsecret');
        $this->accessToken  = $this->getAccessToken();
        $this->jsTicket     = $this->getJsApiTicket();

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
        $this->jssdk = $this->GetSignPackage($this->jsTicket);
        $this->assign('jssdk', $this->jssdk);
        $this->assign('openid', session('openid'));

    }


    public function index()
    {
        var_dump(config('site'));
    }
    use \app\common\library\traits\Wechat;

    private function getAccessToken()
    {
        $token = null;
        $siteConfig = config('site');
        switch ($siteConfig['wechat_type']){
            case '0':
                $sqlObj = [
                    'type'  => 'mysql',
                    'hostname'  => $siteConfig['wechat_sql_hostname'],
                    'database'  => $siteConfig['wechat_sql_dbname'],
                    'username'  => $siteConfig['wechat_sql_username'],
                    'password'  => $siteConfig['wechat_sql_password']
                ];
                $token  = $this->getSqlAccessToken(
                    $sqlObj, $siteConfig['wechat_sql_token_table'],
                    $siteConfig['wechat_sql_token_where'], $siteConfig['wechat_sql_token_field'],
                    $siteConfig['wechat_sql_token_overtime']
                );
                break;
            case '1':
                $token = $this->getFileAccessToken(
                    $siteConfig['wechat_file_token_dir'],
                    $siteConfig['wechat_file_token_field'],
                    $siteConfig['wechat_file_token_overtime']
                );
                break;
            case '2':
                break;
        }
        return $token;
    }

    private function getJsApiTicket()
    {
        $ticket = null;
        $siteConfig = config('site');
        switch ($siteConfig['wechat_type']){
            case '0':
                $sqlObj = [
                    'type'  => 'mysql',
                    'hostname'  => $siteConfig['wechat_sql_hostname'],
                    'database'  => $siteConfig['wechat_sql_dbname'],
                    'username'  => $siteConfig['wechat_sql_username'],
                    'password'  => $siteConfig['wechat_sql_password']
                ];
                $ticket  = $this->getSqlJsApiTicket(
                    $sqlObj, $siteConfig['wechat_sql_ticket_table'],
                    $siteConfig['wechat_sql_ticket_where'], $siteConfig['wechat_sql_ticket_field'],
                    $siteConfig['wechat_sql_ticket_overtime']
                );
                break;
            case '1':
                $ticket = $this->getFileJsApiTicket(
                    $siteConfig['wechat_file_ticket_dir'],
                    $siteConfig['wechat_file_ticket_field'],
                    $siteConfig['wechat_file_ticket_overtime']
                );
                break;
            case '2':
                break;
        }
        return $ticket;
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



    /**
     * 获取SQL数据库accessToken
     * @param $mysqlObj
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
            $token  = $this->getSqlAccessToken();
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
        $data = json_decode(file_get_contents($tokenDir), true);
        if($data[$tokenOvertime] < time()){
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
     * @param $ticketDir        ticket文件路径
     * @param $ticketField      ticket字段名
     * @param $ticketOvertime   ticket过期字段名
     * @param $tokenDir
     * @return mixed
     */
    protected function getFileJsApiTicket($ticketDir, $ticketField, $ticketOvertime) {
        $data = json_decode(file_get_contents($ticketDir), true);
        if ($data[$ticketOvertime] < time()) {
            $accessToken = $this->getAccessToken();
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