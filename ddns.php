<?php
/*
 * DNSPOD
 */
class Ddns {
    // 用户token
    private $token ='';
    // 需要动态ip的域名
    private $domain = '';
    // 子域名，若为    顶级域名，则为 @
    private $sub_domain = '';
    // 域名记录id
    private $domain_id = '';
    // 错误信息
    public $error = '';
    // ip记录
    public $ip = '';

    /*
     * 初始化
     */
    public function __construct($token = '', $domain = '', $sub = '', $ip = '')
    {
        $this->token      = $token;
        $this->domain     = $domain;
        $this->sub_domain = $sub;
        $this->domain_id  = $this->getDomainId();
        
        if($ip == '') {
            $ip = self::getMyIP();
            while(!$ip) {
                $ip = self::getMyIP();
            }
            $this->ip = $ip;
        }else {
            $this->ip = $ip;
        }
    }

    /*
     * 设置当前域名记录id
     */
    private function getDomainId()
    {
        $getDomainInfo = $this->apiData('Domain.Info', array('domain' => $this->domain));
        if ($getDomainInfo['status']['code'] == 1) {
            $domainInfo      = $getDomainInfo['domain'];
            return $domainInfo['id'];
        } else {
            $this->log($getDomainInfo['status']['code'], $getDomainInfo['status']['message']);
        }
    }
    
    /*
     *  获取域名记录信息
     */
    public function getAllRecordData($key = '', $sub = '')
    {
        $getDomainRecordData = $this->apiData('Record.List', array(
            'domain'     => $this->domain,
            'sub_domain' => $sub
        ));

        if ($getDomainRecordData['status']['code'] == 1) {
            if (empty($key)) {
                return $getDomainRecordData;
            } else {
                return $getDomainRecordData[$key];
            }
        } else {
            //TODO 记录失败信息
        }
    }

    /**
     * 获取子域名记录id
     * @param string $sub_domain
     * @return mix
     */
    public function getSubDomainRecordId($sub_domain = '')
    {
        if (empty($sub_domain)) return false;

        $recordData = $this->getAllRecordData('', $sub_domain);
        if ($recordData['status']['code'] == 1) {
            $recordInfos = $recordData['records'];
            return  $recordInfos[0]['id'];
        } else {
            return false;
        }
    }

    /*
     * 新建域名记录
     */
    public function createRecord($ip = '')
    {
        if (empty($ip)) $ip = $this->ip;

        $data = array(
            'domain_id' => $this->domain_id,
            'sub_domain' => $this->sub_domain,
            'record_type' => 'A',
            'record_line' => '默认',
            'value' => $ip,
            'ttl' => '600'
        );

        $createNewRecord = $this->apiData('Record.Create', $data);

        if ($createNewRecord['status']['code'] == 1) {
            $str = '添加成功，当前ip地址为：' . $ip;
            $this->log('002', $str);
        } else {
            $this->log($createNewRecord['status']['code'], $createNewRecord['status']['message']);
        }
    }

    /*
     * 修改域名记录
     */
    public function modifyRecord($ip = '')
    {
        if (empty($ip)) $ip = $this->ip;

//        $subRecordInfo = $this->getSubRecordInfo($this->getSubDomainRecordId($this->sub_domain));
//
//        if ($subRecordInfo['value'] == $ip) {
//            $this->log('000', '当前ip与记录ip一致，无需修改');
//            echo '当前ip与记录ip一致，无需修改';
//            return false;
//        }

        $data = array(
            'domain_id'   => $this->domain_id,
            'record_id'   => $this->getSubDomainRecordId($this->sub_domain),
            'sub_domain'  => $this->sub_domain,
            'record_type' => 'A',
            'record_line' => '默认',
            'value' => $ip
        );

        $modifyRecord = $this->apiData('Record.Modify', $data);

        if ($modifyRecord['status']['code'] == 1) {
            $str = '修改成功，当前ip地址为：' . $ip;
            $this->log('001', $str);
        } else {
            $this->log($modifyRecord['status']['code'], $modifyRecord['status']['message']);
        }
    }

    /*
     * 获取子域名相关信息
     */
    public function getSubRecordInfo($record_id = '')
    {
        $getRecordInfo = $this->apiData('Record.Info', array(
            'domain'    => $this->domain,
            'record_id' => $record_id
        ));

        if ($getRecordInfo['status']['code'] == 1) {
            return $getRecordInfo['record'];
        } else {
            $this->log($getRecordInfo['status']['code'], $getRecordInfo['status']['message']);
            return false;
        }
    }
    /*
     * 获取用户当前ip
     */
    public function getMyIP() {
        // 先尝试通过命令行获取 
        /*
        if(function_exists("shell_exec")) {
            $ifconfig = shell_exec('/sbin/ifconfig eth0');
            preg_match('/addr:([\d\.]+)/', $ifconfig, $match);
            if(isset($match[1])  && $match[1]) {
                return $match[1];
            }
        }
        */

        // 命令行获取失败就通过外网 IP
        $ch = curl_init("http://ip.taobao.com/service/getIpInfo.php?ip=myip");
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result === FALSE) {
            $this->error = '获取当前ip失败';
            $this->log('003', $this->error);
            return false;
        } else {
            $data = json_decode($result, true);
            if($data['code'] != 0 || !isset($data['data']['ip'])) {
                $this->error = '调用第三方接口获取当前ip失败';
                $this->log('004', $this->error);
                return false;
            }else {
                return trim($data['data']['ip']);
            }
        }
    }

    /*
     * 日志记录
     */
    public function log($code = '', $msg = '')
    {
        $str = '【'. date('Y年m月d日 H:i:s') . '】' . 'CODE:' . $code . ', MSG:' . $msg . PHP_EOL;

        echo $str;

        $filename = LOG_PATH . 'error.log';
        $result = file_put_contents($filename, $str, FILE_APPEND);

        return $result ? true : false;
    }

    /*
     * 多次请求缓存当前ip
     * @param int $n 缓存次数
     */
    public function cacheIPs($n = 1)
    {
        $arr = array();
        $startTime = time();

        for ($i = 0; $i < $n; $i++) {
            $arr[] = $this->getMyIP();
        }

        $data = array(
            'create_time' => $startTime,
            'data' => $arr
        );

        $result = file_put_contents(CACHE_IPS_FILE, json_encode($data));

        if ($result == false) {
            $this->log('002', '缓存ip失败');
        } else {
            $useTime = (time() - $startTime) / 60; //使用多少秒
            $str = '缓存成功，共耗时' . $useTime . '秒';
            echo $str;
            $this->log(1, $str);
        }
    }

    /**
     * 检查当前ip是否已经发生变化
     * @return boolean
     */
    public function checkIP()
    {
        $cache = json_decode(file_get_contents(CACHE_IPS_FILE), true);

        //缓存3600秒
        if (time() - $cache['create_time'] > 3600) {
            $this->cacheIPs(8);
        }

        return (in_array($this->ip, $cache['data'])) ? true : false;
    }

    /*
     * curl api 操作
     */
    public function apiData($api = '', $param = array(), $method = 'post', $exit = false)
    {
        if (empty($api)) {
            $this->error = '参数错误';
            return false;
        }

        $method = strtolower($method);
        $url    = API_URL . $api; //接口地址

        $userinfo =  array( //数组处理
            'login_token' => $this->token,
            'format' => 'json',
            'lang'   => 'cn',
            'error_on_empty' => 'no'
        );
        $param = array_merge($param, $userinfo);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($param));

        $data = curl_exec($ch);

        if($method == 'post'){
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        }
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
        $data = curl_exec($ch);
        curl_close($ch);

        if($exit){
            echo $url;
            var_dump($data);
        }

        return ($data === false) ? array('exception'=>1) : json_decode($data, true);
    }

}
