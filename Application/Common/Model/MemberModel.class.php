<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace Common\Model;

use Think\Model;
use User\Api\UserApi;


/**
 * 文档基础模型
 */
class MemberModel extends Model
{
    /* 用户模型自动完成 */
    protected $_auto = array(
        array('login', 0, self::MODEL_INSERT),
        array('reg_ip', 'get_client_ip', self::MODEL_INSERT, 'function', 1),
        array('reg_time', NOW_TIME, self::MODEL_INSERT),
        array('last_login_ip', 0, self::MODEL_INSERT),
        array('last_login_time', 0, self::MODEL_INSERT),
        array('update_time', NOW_TIME),
        array('status', 1, self::MODEL_INSERT),
        array('tox_money', 0, self::MODEL_INSERT),
        array('score', 0, self::MODEL_INSERT),
        array('pos_province', 0, self::MODEL_INSERT),
        array('pos_city', 0, self::MODEL_INSERT),
        array('pos_district', 0, self::MODEL_INSERT),
        array('pos_community', 0, self::MODEL_INSERT),
    );

    protected $_validate = array(
        array('signature', '0,100', -1, self::EXISTS_VALIDATE, 'length'),


        /* 验证昵称 */
        array('nickname', '2,30', -33, self::EXISTS_VALIDATE, 'length'), //昵称长度不合法
        array('nickname', 'checkDenyNickname', -31, self::EXISTS_VALIDATE, 'callback'), //昵称禁止注册
        array('nickname', 'checkNickname', -32, self::EXISTS_VALIDATE, 'callback'),
        array('nickname', '', -30, self::EXISTS_VALIDATE, 'unique'), //昵称被占用

    );

    protected $insertField = 'nickname,sex,birthday,qq,signature'; //新增数据时允许操作的字段
    protected $updateField = 'nickname,sex,birthday,qq,signature,last_login_ip,login,last_login_ip,last_login_time,update_time,status,tox_money,score,pos_province,pos_city,pos_district,pos_community'; //编辑数据时允许操作的字段

    /**
     * 检测用户名是不是被禁止注册
     * @param  string $nickname 昵称
     * @return boolean          ture - 未禁用，false - 禁止注册
     */
    protected function checkDenyNickname($nickname)
    {
        return true; //TODO: 暂不限制，下一个版本完善
    }

    protected function checkNickname($nickname)
    {
        //如果用户名中有空格，不允许注册
        if (strpos($nickname, ' ') !== false) {
            return false;
        }
        preg_match('/^(?!_|\s\')[A-Za-z0-9_\x80-\xff\s\']+$/', $nickname, $result);

        if (!$result) {
            return false;
        }
        return true;
    }

    public function registerMember($nickname = '')
    {
        /* 在当前应用中注册用户 */
        if ($user = $this->create(array('nickname' => $nickname, 'status' => 1))) {
            $uid = $this->add($user);
            if (!$uid) {
                $this->error = '前台用户信息注册失败，请重试！';
                return false;
            }

            return $uid;
        } else {
            return $this->getError(); //错误详情见自动验证注释
        }

    }

    /**
     * 登录指定用户
     * @param  integer $uid 用户ID
     * @return boolean      ture-登录成功，false-登录失败
     */
    public function login($uid, $remember = false)
    {
        session('temp_login_uid', $uid);
        /* 检测是否在当前应用注册 */
        $user = $this->field(true)->find($uid);
        if ($user['status'] == 3 /*判断是否激活*/) {
            header('Content-Type:application/json; charset=utf-8');
            $data['status'] = 1;
            $data['url'] = U('Ucenter/Member/activate');
            exit(json_encode($data));
        }

        if (1 != $user['status']) {
            $this->error = '用户未激活或已禁用！'; //应用级别禁用
            return false;
        }

        $step = UCenterMember()->where(array('id' => $uid))->getField('step');
        if (!empty($step) && $step != 'finish') {
            header('Content-Type:application/json; charset=utf-8');
            $data['status'] = 1;
            //执行步骤在start的时候执行下一步，否则执行此步骤
            $go = $step=='start'?get_next_step($step):check_step($step);
            $data['url'] = U('Ucenter/Member/step',array('step'=>$go));

            exit(json_encode($data));
        }
        /* 登录用户 */
        $this->autoLogin($user, $remember);

        session('temp_login_uid',null);
        //记录行为
        action_log('user_login', 'member', $uid, $uid);
        return true;
    }

    /**
     * 注销当前用户
     * @return void
     */
    public function logout()
    {
        session('user_auth', null);
        session('user_auth_sign', null);
        cookie('OX_LOGGED_USER', NULL);
    }

    /**
     * 自动登录用户
     * @param  integer $user 用户信息数组
     */
    private function autoLogin($user, $remember = false)
    {
        /* 更新登录信息 */
        $data = array(
            'uid' => $user['uid'],
            'login' => array('exp', '`login`+1'),
            'last_login_time' => NOW_TIME,
            'last_login_ip' => get_client_ip(1),
        );
        $this->save($data);

        /* 记录登录SESSION和COOKIES */
        $auth = array(
            'uid' => $user['uid'],
            'username' => get_username($user['uid']),
            'last_login_time' => $user['last_login_time'],
        );

        session('user_auth', $auth);
        session('user_auth_sign', data_auth_sign($auth));
        if ($remember) {
            $user1 = D('user_token')->where('uid=' . $user['uid'])->find();
            $token = $user1['token'];
            if ($user1 == null) {
                $token = build_auth_key();
                $data['token'] = $token;
                $data['time'] = time();
                $data['uid'] = $user['uid'];
                D('user_token')->add($data);
            }
        }

        if (!$this->getCookieUid() && $remember) {
            $expire = 3600 * 24 * 7;
            cookie('OX_LOGGED_USER', $this->jiami($this->change() . ".{$user['uid']}.{$token}"), $expire);
        }
    }

    public function need_login()
    {

        if ($uid = $this->getCookieUid()) {
            $this->login($uid);
            return true;
        }
    }

    public function getCookieUid()
    {

        static $cookie_uid = null;
        if (isset($cookie_uid) && $cookie_uid !== null) {
            return $cookie_uid;
        }
        $cookie = cookie('OX_LOGGED_USER');
        $cookie = explode(".", $this->jiemi($cookie));
        $map['uid'] = $cookie[1];
        $user = D('user_token')->where($map)->find();
        $cookie_uid = ($cookie[0] != $this->change()) || ($cookie[2] != $user['token']) ? false : $cookie[1];
        $cookie_uid = $user['time'] - time() >= 3600 * 24 * 7 ? false : $cookie_uid;
        return $cookie_uid;
    }


    /**
     * 加密函数
     * @param string $txt 需加密的字符串
     * @param string $key 加密密钥，默认读取SECURE_CODE配置
     * @return string 加密后的字符串
     */
    private function jiami($txt, $key = null)
    {
        empty($key) && $key = $this->change();

        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-=_";
        $nh = rand(0, 64);
        $ch = $chars[$nh];
        $mdKey = md5($key . $ch);
        $mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
        $txt = base64_encode($txt);
        $tmp = '';
        $i = 0;
        $j = 0;
        $k = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $k = $k == strlen($mdKey) ? 0 : $k;
            $j = ($nh + strpos($chars, $txt [$i]) + ord($mdKey[$k++])) % 64;
            $tmp .= $chars[$j];
        }
        return $ch . $tmp;
    }

    /**
     * 解密函数
     * @param string $txt 待解密的字符串
     * @param string $key 解密密钥，默认读取SECURE_CODE配置
     * @return string 解密后的字符串
     */
    private function jiemi($txt, $key = null)
    {
        empty($key) && $key = $this->change();

        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-=_";
        $ch = $txt[0];
        $nh = strpos($chars, $ch);
        $mdKey = md5($key . $ch);
        $mdKey = substr($mdKey, $nh % 8, $nh % 8 + 7);
        $txt = substr($txt, 1);
        $tmp = '';
        $i = 0;
        $j = 0;
        $k = 0;
        for ($i = 0; $i < strlen($txt); $i++) {
            $k = $k == strlen($mdKey) ? 0 : $k;
            $j = strpos($chars, $txt[$i]) - $nh - ord($mdKey[$k++]);
            while ($j < 0) {
                $j += 64;
            }
            $tmp .= $chars[$j];
        }

        return base64_decode($tmp);
    }

    private function change()
    {
        preg_match_all('/\w/', C('DATA_AUTH_KEY'), $sss);
        $str1 = '';
        foreach ($sss[0] as $v) {
            $str1 .= $v;
        }
        return $str1;
    }

    /**
     * 同步登陆时添加用户信息
     * @param $uid
     * @param $info
     * @return mixed
     * autor:xjw129xjt
     */
    public function addSyncData($uid, $info)
    {

        $data1['nickname'] = mb_substr($info['nick'], 0, 11, 'utf-8');
        //去除特殊字符。
        $data1['nickname'] = preg_replace('/[^A-Za-z0-9_\x80-\xff\s\']/', '', $data1['nickname']);
        empty($data1['nickname']) && $data1['nickname'] = $this->rand_nickname();
        $data1['nickname'] .= '_' . $this->rand_nickname();
        $data1['sex'] = $info['sex'];
        $data = $this->create($data1);
        $data['uid'] = $uid;
        $res = $this->add($data);
        return $res;
    }

    public function rand_nickname()
    {
        $nickname = $this->create_rand(4);
        if ($this->where(array('nickname' => $nickname))->select()) {
            $this->rand_nickname();
        } else {
            return $nickname;
        }
    }

    function create_rand($length = 8)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $password;
    }


}
