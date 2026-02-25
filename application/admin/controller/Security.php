<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海口快推科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace app\admin\controller;

use think\Db;
use think\Cache;
use app\admin\logic\DdosLogic;

class Security extends Base
{
    public $admin_info = array();
    public $admin_id = 0;
    public $ddosLogic;

    /**
     * 初始化操作
     */
    public function _initialize() {
        parent::_initialize();
        $this->admin_info = session('admin_info');
        $this->admin_id = empty($this->admin_info) ? 0 : $this->admin_info['admin_id'];
        $this->ddosLogic = new DdosLogic;
    }

    public function index()
    {
        // 重置扫描范围
        $setdata = [
            'ddos_scan_range_files' => 1,
            'ddos_scan_range_attachment' => 0,
            'ddos_scan_range_uploads' => 0,
        ];
        tpSetting('ddos', $setdata, 'cn');
        // 重置ddos_log表
        $this->ddosLogic->ddos_log_reset();
        $this->redirect(url('Security/ddos_kill', [], true, true));
        exit;

        // if (IS_POST) {
        //     $this->handleSave();
        // }

        $is_founder = 0;
        if (-1 == $this->admin_info['role_id'] && empty($this->admin_info['parent_id'])) {
            $is_founder = 1;
        }
        $this->admin_info['is_founder'] = $is_founder;
        $this->assign('admin_info', $this->admin_info);

        //自定义后台路径名
        $baseFile = explode('/', $this->request->baseFile());
        $web_adminbasefile = end($baseFile);
        $adminbasefile = preg_replace('/^(.*)\.([^\.]+)$/i', '$1', $web_adminbasefile);
        $this->assign('adminbasefile', $adminbasefile);

        // 安全验证配置
        $security = tpSetting('security');
        if (isset($security['security_verifyfunc'])) {
            $security['security_verifyfunc'] = json_decode($security['security_verifyfunc'], true);
        }
        $security_askanswer_content = '';
        if (!empty($security['security_askanswer_list'])) {
            $security_askanswer_list = json_decode($security['security_askanswer_list'], true);
            $security['security_askanswer_list'] = $security_askanswer_list;
        }
        if (empty($security_askanswer_list)) {
            $security_askanswer_list = config('global.security_askanswer_list');
        }
        $security_askanswer_content = implode(PHP_EOL, $security_askanswer_list);
        $this->assign('security', $security);
        $this->assign('security_askanswer_content', $security_askanswer_content);

        if (!empty($security['security_ask'])) {
            $security_ask = $security['security_ask'];
            if (!in_array($security_ask, $security_askanswer_list)) {
                $security_askanswer_list[] = $security_ask;
            }
        }
        $this->assign('security_askanswer_list', $security_askanswer_list);

        return $this->fetch();
    }

    /**
     * 保存 - 后台安全中心
     * @return [type] [description]
     */
    public function handleSave1()
    {
        if (IS_POST) {
            $post = input('post.');

            /*-------------------后台安全配置 start-------------------*/
            $param = [
                'web_login_expiretime' => $post['web_login_expiretime'],
                'login_expiretime_old' => $post['login_expiretime_old'],
                'web_login_lockopen'    => !empty($post['web_login_lockopen']) ? 1 : 0,
                // 'web_sqldatapath' => $post['web_sqldatapath'],
            ];
            // 开启锁定才修改相应的配置值
            if (!empty($param['web_login_lockopen'])) {
                $param['web_login_errtotal'] = $post['web_login_errtotal'];
                $param['web_login_errexpire'] = $post['web_login_errexpire'];
            }

            // 自定义后台路径名
            $adminbasefile = preg_replace('/([^\w\_\-])/i', '', trim($post['adminbasefile'])).'.php'; // 新的文件名
            $param['web_adminbasefile'] = $this->root_dir.'/'.$adminbasefile; // 支持子目录
            $baseFile = explode('/', $this->request->baseFile());
            $adminbasefile_old = end($baseFile); // 旧的文件名
            if ('index.php' == $adminbasefile) {
                $this->error("后台路径禁止使用index", null, '', 1);
            }

            // 数据库备份目录
            /*$web_sqldatapath_old = tpCache('global.web_sqldatapath');
            $param['web_sqldatapath'] = '/'.trim($param['web_sqldatapath'], '/');*/

            // 后台登录超时
            $web_login_expiretime = $param['web_login_expiretime'];
            $login_expiretime_old = $param['login_expiretime_old'];
            unset($param['login_expiretime_old']);
            if ($login_expiretime_old != $web_login_expiretime) {
                $web_login_expiretime = preg_replace('/^(\d{0,})(.*)$/i', '${1}', $web_login_expiretime);
                empty($web_login_expiretime) && $web_login_expiretime = config('login_expire');
                if ($web_login_expiretime > 2592000) {
                    $web_login_expiretime = 2592000; // 最多一个月
                }
                $param['web_login_expiretime'] = $web_login_expiretime;
                //前台登录超时时间
                $users_login_expiretime = getUsersConfigData('users.users_login_expiretime');
                //前台和后台谁设置的时间大就用谁的做session过期时间
                $max_login_expiretime = $web_login_expiretime;
                if ($web_login_expiretime < $users_login_expiretime){
                    $max_login_expiretime = $users_login_expiretime;
                }
            }
            // 编辑器防注入
            $param['web_xss_filter'] = intval($post['web_xss_filter']);
            $this->setWebXssFilter($param['web_xss_filter']);
            // 网站防止被刷
            $param['web_anti_brushing'] = intval($post['web_anti_brushing']);
            /*-------------------后台安全配置 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', $param, $val['mark']);
                }
            } else {
                tpCache('web', $param);
            }
            /*--end*/

            $refresh = false;

            /*-------------------后台安全配置 start-------------------*/
            // 更改session会员设置 - session有效期（后台登录超时）
            if ($login_expiretime_old != $web_login_expiretime) {
                $session_conf = [];
                $session_file = APP_PATH.'admin/conf/session_conf.php';
                if (file_exists($session_file)) {
                    require_once($session_file);
                    $session_conf_tmp = EY_SESSION_CONF;
                    if (!empty($session_conf_tmp)) {
                        $session_conf_tmp = json_decode($session_conf_tmp, true);
                        if (!empty($session_conf_tmp) && is_array($session_conf_tmp)) {
                            $session_conf = $session_conf_tmp;
                        }
                    }
                }
                $session_conf['expire'] = $max_login_expiretime;
                $str_session_conf = '<?php'.PHP_EOL.'$session_1600593464 = json_encode('.var_export($session_conf,true).');'.PHP_EOL.'define(\'EY_SESSION_CONF\', $session_1600593464);';
                @file_put_contents(APP_PATH . 'admin/conf/session_conf.php', $str_session_conf);
            }

            // 更改自定义后台路径名 - 刷新整个后台
            $gourl = request()->domain().$this->root_dir.'/'.$adminbasefile; // 支持子目录
            if ($adminbasefile_old != $adminbasefile && eyPreventShell($adminbasefile_old)) {
                if (file_exists($adminbasefile_old)) {
                    if(rename($adminbasefile_old, $adminbasefile)) {
                        $refresh = true;
                    }
                } else {
                    $this->error("根目录{$adminbasefile_old}文件不存在！", null, '', 2);
                }
            }
            /*if ($web_sqldatapath_old != $param['web_sqldatapath'] && preg_match('/^\/data\/sqldata([^\/]*)$/i', $param['web_sqldatapath'])) {
                @rename(ROOT_PATH.ltrim($web_sqldatapath_old, '/'), ROOT_PATH.ltrim($param['web_sqldatapath'], '/'));
            }*/
            /*-------------------后台安全配置 end-------------------*/

            if ($refresh) {
                $this->success('操作成功', $gourl, '', 1, [], '_parent');
            }

            $this->success('操作成功', url('Security/index'));
        }
        $this->error('操作失败');
    }

    /**
     * 编辑器防注入是否开启与关闭
     */
    private function setWebXssFilter($web_xss_filter = 0)
    {
        $tfile = DATA_PATH.'conf'.DS.'web_xss_filter.txt';
        $fp = @fopen($tfile,'w');
        if(!$fp) {
            @file_put_contents($tfile, $web_xss_filter);
        }
        else {
            fwrite($fp, $web_xss_filter);
            fclose($fp);
        }
    }

    /**
     * 保存 - 安全验证中心
     * @return [type] [description]
     */
    public function handleSave2()
    {
        if (IS_POST) {
            $settingData = [];
            $post = input('post.');

            if (empty($post['security_ask_open'])) {
                $securityOld = tpSetting('security');
                if (!empty($securityOld['security_ask'])) {
                    $answer = empty($post['security_answer_old']) ? '' : trim($post['security_answer_old']);
                    if (empty($answer)) {
                        $this->error('请录入密保答案！');
                    } else {
                        $security_answer = empty($securityOld['security_answer']) ? '' : trim($securityOld['security_answer']);
                        $encrypt_answer = func_encrypt($answer, true, pwd_encry_type('bcrypt'));
                        if ($security_answer != $encrypt_answer) {
                            $this->error('密保答案不正确！');
                        }
                    }
                    $this->submit_answer_verify();
                }
            }

            /*-------------------二次安全验证 start-------------------*/
            $this->handleAskData($settingData, $post);
            /*-------------------二次安全验证 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpSetting('security', $settingData, $val['mark']);
                }
            } else {
                tpSetting('security', $settingData);
            }
            /*--end*/

            // 设置问题答案后，自动验证通过
            $this->submit_answer_verify();

            $msg = "操作成功";
            $is_show_answer = 0;
            if (!empty($settingData['security_answer']) && !empty($settingData['security_ask_open'])) {
                $is_show_answer = 1;
                $securityData = tpSetting('security');
                $msg = "问题：{$securityData['security_ask']}<br/>答案：".mchStrCode($securityData['security_answer_bright'], 'DECODE'); 
            }
            $this->success($msg, url('Security/index'), ['is_show_answer'=>$is_show_answer,'security_ask_open'=>$settingData['security_ask_open']]);
        }
        $this->error('操作失败');
    }

    /**
     * 保存二次安全验证的数据处理
     * @param  array  &$settingData [description]
     * @param  array  &$post        [description]
     * @return [type]               [description]
     */
    private function handleAskData(&$settingData = [], &$post = [])
    {
        $securityOld = tpSetting('security');
        $security_ask = intval($post['security_ask']);
        $security_answer = trim($post['security_answer']);
        $is_ask_add_edit = empty($securityOld['security_ask']) ? 'add' : 'edit';
        if ('add' == $is_ask_add_edit) {
            if (empty($post['security_ask_open'])) {
                $this->success('操作成功', url('Security/index'), ['is_show_answer'=>0,'security_ask_open'=>0]);
            }
            if (0 > intval($security_ask)) {
                $this->error('请选择密保问题！');
            } else if ($security_answer === '') {
                $this->error('请设置密保答案！');
            }
            $encrypt_answer = func_encrypt($security_answer, true, pwd_encry_type('bcrypt'));
            $row = Db::name('admin')->where([
                    'admin_id'  => $this->admin_id,
                    'password'  => $encrypt_answer,
                ])->count();
            if (!empty($row)) {
                $this->error('密保答案不能与登录密码一致！');
            }
        } else {
            $security_answer_old = trim($post['security_answer_old']);
            if ($security_answer !== '' || 0 <= intval($security_ask)) {
                if ($security_answer_old === '') {
                    $this->error('密保答案不能为空！');
                } else {
                    if (0 <= intval($security_ask)) {
                        if ($security_answer === '') {
                            $this->error('请重置密保答案！');
                        } else if ($security_answer === $security_answer_old) {
                            $this->error('重置密保答案不能与原来的一致！');
                        }
                    }
                }
                $encrypt_answer_old = func_encrypt($security_answer_old, true, pwd_encry_type('bcrypt'));
                if ($encrypt_answer_old != $securityOld['security_answer']) {
                    $this->error('密保答案不正确！');
                }

                $encrypt_answer = func_encrypt($security_answer, true, pwd_encry_type('bcrypt'));
                $row = Db::name('admin')->where([
                        'admin_id'  => $this->admin_id,
                        'password'  => $encrypt_answer,
                    ])->count();
                if (!empty($row)) {
                    $this->error('重置密保答案不能与登录密码一致！');
                }
            } else {
                if ($security_answer_old !== '') {
                    $encrypt_answer_old = func_encrypt($security_answer_old, true, pwd_encry_type('bcrypt'));
                    if ($encrypt_answer_old != $securityOld['security_answer']) {
                        $this->error('密保答案不正确！');
                    }
                }
                unset($post['security_ask']);
                unset($post['security_answer']);
                unset($post['security_answer_old']);
            }
        }

        /**
         * 如果要关闭二次安全验证，必须要进行答案验证
         * 同IP不验证功能也会影响到这里的逻辑
         */
        // 问题列表
        $security_askanswer_list = empty($securityOld['security_askanswer_list']) ? config('global.security_askanswer_list') : json_decode($securityOld['security_askanswer_list'], true);
        // 当前管理员二次安全验证过的IP地址
        $security_answerverify_ip = !empty($securityOld['security_answerverify_ip']) ? $securityOld['security_answerverify_ip'] : '-1';
        // 1、问答要已设置；2、目前是开启；3、当前要关闭；
        if (!empty($securityOld['security_ask_open']) && empty($post['security_ask_open']) && !empty($securityOld['security_ask'])) {
            $admin_info = Db::name('admin')->field('*')->where(['admin_id'=>$this->admin_id])->find();
            // if (!empty($admin_info['parent_id']) || -1 != $admin_info['role_id']) {
            //     $this->error('创始人才能关闭安全验证功能！');
            // }
            if ($admin_info['last_ip'] != $security_answerverify_ip) {
                $this->error("<span style='display:none;'>__html__</span>出于安全考虑<br/>请勿非法越过密保答案验证", null, '', 3);
            }
        }
        $settingData['security_ask_open'] = intval($post['security_ask_open']);
        if (!empty($settingData['security_ask_open'])) {
            empty($post['security_verifyfunc']) && $post['security_verifyfunc'] = [];
            $ctl_act_arr = ['Filemanager@*','Arctype@ajax_newtpl','Archives@ajax_newtpl','Index@ajax_theme_tplfile_add','Index@ajax_theme_tplfile_edit'];
            $post['security_verifyfunc'] = array_merge($post['security_verifyfunc'], $ctl_act_arr);
            // $post['security_verifyfunc'][] = 'Security@*';
            $post['security_verifyfunc'] = array_unique($post['security_verifyfunc']);
            $settingData['security_verifyfunc'] = json_encode($post['security_verifyfunc']);
            $settingData['security_ask_ip_open'] = !empty($post['security_ask_ip_open']) ? intval($post['security_ask_ip_open']) : 0;
            if (isset($post['security_ask'])) {
                $settingData['security_ask'] = $security_askanswer_list[$post['security_ask']];
            }
            if (isset($post['security_answer'])) {
                $settingData['security_answer'] = func_encrypt($post['security_answer'], true, pwd_encry_type('bcrypt'));
                $settingData['security_answer_bright'] = mchStrCode($post['security_answer']);
            }
            if (empty($securityOld['security_askanswer_list'])) {
                $settingData['security_askanswer_list'] = json_encode($security_askanswer_list);
            }
        }
    }

    /*--------------------------------安全验证中心 start--------------------------*/

    /**
     * 设置二次安全验证的问题、答案
     */
    public function second_verify_add()
    {
        $security_askanswer_list = tpSetting('security.security_askanswer_list');
        $security_askanswer_list = json_decode($security_askanswer_list, true);
        if (empty($security_askanswer_list)) {
            $security_askanswer_list = config('global.security_askanswer_list');
        }

        if (IS_POST) {
            // 修补越权的漏洞，在重设答案时，通过抓包改成新设答案
            if (!empty($this->globalConfig['security_ask'])) {
                $this->error('已设置过密保，请重新设置');
            }

            $ask = input('post.ask/d');
            $answer = input('post.answer/s');
            $answer = trim($answer);

            if (0 > $ask) {
                $this->error('请选择密保问题！');
            } else if (empty($answer)) {
                $this->error('密保答案不能为空！');
            }

            $encrypt_answer = func_encrypt($answer, true, pwd_encry_type('bcrypt'));
            $row = Db::name('admin')->where([
                    'admin_id'  => $this->admin_id,
                    'password'  => $encrypt_answer,
                ])->count();
            if (!empty($row)) {
                $this->error('密保答案不能与登录密码一致！');
            }

            $data = [
                'security_ask_open'   => 1,
                'security_ask'   => $security_askanswer_list[$ask],
                'security_answer'   => $encrypt_answer,
                'security_answer_bright'   => mchStrCode($answer),
                'security_askanswer_list' => json_encode($security_askanswer_list),
            ];
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpSetting('security', $data, $val['mark']);
                }
            } else {
                tpSetting('security', $data);
            }
            /*--end*/

            $this->success('操作成功', url('Security/index'));
        }

        $this->assign('security_askanswer_list', $security_askanswer_list);

        return $this->fetch();
    }

    /**
     * 修改二次安全验证的问题、答案
     */
    public function second_verify_edit()
    {
        $security_askanswer_list = tpSetting('security.security_askanswer_list');
        $security_askanswer_list = json_decode($security_askanswer_list, true);
        if (empty($security_askanswer_list)) {
            $security_askanswer_list = config('global.security_askanswer_list');
        }

        if (IS_POST) {
            $post = input('post.');
            $answer_old = trim($post['answer_old']);
            $ask = intval($post['ask']);
            $answer = trim($post['answer']);

            if (empty($answer_old)) {
                $this->error('密保答案不能为空！');
            } else {
                if (0 <= $ask) {
                    if (empty($answer)) {
                        $this->error('重置密保答案不能为空！');
                    } else if ($answer == $answer_old) {
                        $this->error('重置密保答案不能与原来的一致！');
                    }
                } 
            }

            $security = tpSetting('security');
            $encrypt_answer_old = func_encrypt($answer_old, true, pwd_encry_type('bcrypt'));
            if ($encrypt_answer_old != $security['security_answer']) {
                $this->error('密保答案不正确！');
            }

            $data = [];
            if (0 <= $ask) {
                $encrypt_answer = func_encrypt($answer, true, pwd_encry_type('bcrypt'));
                $row = Db::name('admin')->where([
                        'admin_id'  => $this->admin_id,
                        'password'  => $encrypt_answer,
                    ])->count();
                if (!empty($row)) {
                    $this->error('重置密保答案不能与登录密码一致！');
                }
                $data['security_ask'] = $security_askanswer_list[$ask];
                $data['security_answer'] = $encrypt_answer;
                $data['security_answer_bright'] = mchStrCode($answer);
                $data['security_askanswer_list'] = json_encode($security_askanswer_list);
            }

            if (!empty($data)) {
                /*多语言*/
                if (is_language()) {
                    $langRow = \think\Db::name('language')->order('id asc')
                        ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                        ->select();
                    foreach ($langRow as $key => $val) {
                        tpSetting('security', $data, $val['mark']);
                    }
                } else {
                    tpSetting('security', $data);
                }
                /*--end*/
            }

            $this->success('操作成功', url('Security/index'));
        }

        $security = tpSetting('security');
        if (!empty($security)) {
            $security_ask = $security['security_ask'];
            if (!in_array($security_ask, $security_askanswer_list)) {
                $security_askanswer_list[] = $security_ask;
            }
        }
        $this->assign('security', $security);
        $this->assign('security_askanswer_list', $security_askanswer_list);

        return $this->fetch();
    }

    /**
     * 二次安全验证答案
     * @return [type] [description]
     */
    public function ajax_answer_verify()
    {
        if (IS_POST) {
            $answer = input('param.answer/s');
            $answer = trim($answer);
            if (empty($answer)) {
                $this->error('请录入密保答案！');
            } else {
                $security_answer = tpSetting('security.security_answer');
                $encrypt_answer = func_encrypt($answer, true, pwd_encry_type('bcrypt'));
                if ($security_answer != $encrypt_answer) {
                    $this->error('密保答案不正确！');
                }
            }
            $this->submit_answer_verify();
            $this->success('密保验证成功');
        }
    }

    /**
     * 二次安全验证答案-提交
     * @return [type] [description]
     */
    private function submit_answer_verify()
    {
        /*多语言*/
        $ip = clientIP();
        if (is_language()) {
            $langRow = \think\Db::name('language')->order('id asc')
                ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                ->select();
            foreach ($langRow as $key => $val) {
                tpSetting('security', ['security_answerverify_ip'=>$ip], $val['mark']);
            }
        } else {
            tpSetting('security', ['security_answerverify_ip'=>$ip]);
        }
        /*--end*/

        // 解决个别用户安装后，登录后台没记录最后登录IP地址，导致一直弹出验证答案
        $admin_info = Db::name('admin')->field('admin_id,last_ip')->where(['admin_id'=>$this->admin_id])->find();
        Db::name('admin')->where(['admin_id'=>$admin_info['admin_id']])->save(['last_ip'=>$ip, 'update_time'=>getTime()]);
    }

    /**
     * 是否已验证了答案
     * @return [type] [description]
     */
    public function ajax_isverify_answer()
    {
        if (IS_POST) {
            $security = tpSetting('security');
            $security_answerverify_ip = !empty($security['security_answerverify_ip']) ? $security['security_answerverify_ip'] : '-1';
            $admin_info = Db::name('admin')->field('admin_id,last_ip')->where(['admin_id'=>$this->admin_id])->find();
            if ($admin_info['last_ip'] == $security_answerverify_ip) {
                $this->success('已验证');
            }
        }
        $this->error('未验证');
    }

    /**
     * 修改问题列表
     * @return [type] [description]
     */
    public function save_ask_list()
    {
        if (IS_POST) {
            $value = input('post.value/s');
            $value = str_replace(["\r\n", "\n\r", "\r", "\n"], PHP_EOL, $value);
            $arr = explode(PHP_EOL, $value);
            foreach ($arr as $key => $val) {
                $val = trim($val);
                if (empty($val)) {
                    unset($arr[$key]);
                } else {
                    $arr[$key] = $val;
                }
            }
            if (empty($arr)) {
                $this->error('问题列表不能为空！');
            }

            // 将已设置的问题加入列表中
            $security_ask = tpSetting('security.security_ask');
            $security_ask = trim($security_ask);
            if (!empty($security_ask) && !in_array($security_ask, $arr)) {
                $arr[] = $security_ask;
            }

            if (is_language()) {
                $langRow = Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpSetting('security', ['security_askanswer_list'=>json_encode($arr)], $val['mark']);
                }
            } else { // 单语言
                tpSetting('security', ['security_askanswer_list'=>json_encode($arr)]);
            }
            $value = implode(PHP_EOL, $arr);

            $this->success('操作成功', null, ['value'=>$value, 'security_askanswer_list'=>$arr]);
        }
    }

    /**
     * 独立弹窗的安全验证中心（用于点击入口模板管理）
     * @return [type] [description]
     */
    public function second_ask_init()
    {
        if (IS_POST) {
            $settingData = [];
            $post = input('post.');
            
            /*-------------------二次安全验证 start-------------------*/
            $this->handleAskData($settingData, $post);
            /*-------------------二次安全验证 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpSetting('security', $settingData, $val['mark']);
                }
            } else {
                tpSetting('security', $settingData);
            }
            /*--end*/

            // 设置问题答案后，自动验证通过
            $this->submit_answer_verify();

            $is_show_answer = 0;
            if (empty($settingData['security_ask_open'])) {
                $gourl = "";
                $msg = "操作成功";
            } else {
                $gourl = input('param.gourl/s', '', null);
                if (empty($settingData['security_answer'])) {
                    $msg = "操作成功";
                } else {
                    $is_show_answer = 1;
                    $securityData = tpSetting('security');
                    $msg = "问题：{$securityData['security_ask']}<br/>答案：".mchStrCode($securityData['security_answer_bright'], 'DECODE'); 
                }
            }
            $this->success($msg, null, ['gourl'=>$gourl,'is_show_answer'=>$is_show_answer]);
        }

        $is_founder = 0;
        if (-1 == $this->admin_info['role_id'] && empty($this->admin_info['parent_id'])) {
            $is_founder = 1;
        }
        $this->admin_info['is_founder'] = $is_founder;
        $this->assign('admin_info', $this->admin_info);

        // 安全验证配置
        $security = tpSetting('security');
        if (!isset($security['security_ask_open'])) {
            $security['security_ask_open'] = 1;
        }
        if (isset($security['security_verifyfunc'])) {
            $security['security_verifyfunc'] = json_decode($security['security_verifyfunc'], true);
        }
        $security_askanswer_content = '';
        if (!empty($security['security_askanswer_list'])) {
            $security_askanswer_list = json_decode($security['security_askanswer_list'], true);
            $security['security_askanswer_list'] = $security_askanswer_list;
        }
        if (empty($security_askanswer_list)) {
            $security_askanswer_list = config('global.security_askanswer_list');
        }
        $security_askanswer_content = implode(PHP_EOL, $security_askanswer_list);
        $this->assign('security', $security);
        $this->assign('security_askanswer_content', $security_askanswer_content);

        if (!empty($security['security_ask'])) {
            $security_ask = $security['security_ask'];
            if (!in_array($security_ask, $security_askanswer_list)) {
                $security_askanswer_list[] = $security_ask;
            }
        }
        $this->assign('security_askanswer_list', $security_askanswer_list);

        $gourl = input('param.gourl/s');
        $this->assign('gourl', urldecode($gourl));
        // 点击来源
        $source = input('param.source/s');
        $this->assign('source', $source);

        return $this->fetch();
    }

    public function ajax_security_ask_open()
    {
        $data = tpSetting('security');
        $data['security_ask_open'] = empty($data['security_ask_open']) ? 0 : intval($data['security_ask_open']);
        $this->success('请求成功', null, $data);
    }

    /*-----------------------ddos攻击脚本查杀 start-----------------------*/

    /**
     * DDOS攻击脚本查杀
     * @return [type] [description]
     */
    public function ddos_kill()
    {
        $Prefix = config('database.prefix');
        $syn_admin_logic_1726216198 = tpSetting('syn.syn_admin_logic_1726216198', [], 'cn');
        if (empty($syn_admin_logic_1726216198)) {
            try {
                @Db::execute("DROP TABLE IF EXISTS `{$Prefix}ddos_log`");
                tpSetting('syn', ['syn_admin_logic_1726216198' => 1], 'cn');
            } catch (\Exception $e) {}
        }

        $tableSql = <<<EOF
CREATE TABLE IF NOT EXISTS `{$Prefix}ddos_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `md5key` varchar(50) DEFAULT '' COMMENT 'md5值',
  `file_name` text COMMENT '文件名',
  `file_num` int(10) DEFAULT '0' COMMENT '已扫描数',
  `file_total` int(10) DEFAULT '0' COMMENT '总文件数',
  `file_doubt_total` int(10) DEFAULT '0' COMMENT '可疑恶意文件数',
  `file_excess` int(5) DEFAULT '0' COMMENT '是否多余',
  `file_grade` int(10) DEFAULT '0' COMMENT '文件级别，0=正常，100=异常文件，200=疑似木马，970=低危，980=中危，990=高危',
  `html` longtext,
  `admin_id` int(11) DEFAULT '0',
  `add_time` int(11) DEFAULT '0' COMMENT '新增时间',
  `update_time` int(11) DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COMMENT='ddos查杀进度记录表';
EOF;
        $r = @Db::execute($tableSql);
        if ($r !== false) {
            schemaTable('ddos_log');
        }

        $tableSql = <<<EOF
CREATE TABLE IF NOT EXISTS `{$Prefix}ddos_setting` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) DEFAULT '' COMMENT '配置的key键名',
  `value` longtext,
  `inc_type` varchar(50) DEFAULT 'ddos',
  `admin_id` int(11) DEFAULT '0',
  `add_time` int(11) DEFAULT '0' COMMENT '新增时间',
  `update_time` int(11) DEFAULT '0' COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='ddos业务存储表';
EOF;
        $r = @Db::execute($tableSql);
        if ($r !== false) {
            schemaTable('ddos_setting');
        }

        // 病毒特征库
        $url = 'ht'.'tp'.':/'.'/'.'up'.'da'.'te'.'.e'.'yo'.'u.5'.'f'.'a.'.'c'.'n/other/ddos_feature_library.txt';
        $response = @httpRequest2($url, 'GET', [], [], 3);
        if (empty($response)) {
            $context = stream_context_set_default(array('http' => array('timeout' => 3,'method'=>'GET')));
            $response = @file_get_contents($url, false, $context);
        }
        if (!empty($response)) {
            $path = DATA_PATH.'conf/ddos_feature_library.txt';
            if (!file_exists($path) || is_writeable($path)) {
                try {
                    $fp = fopen($path, "w+");
                    if (!empty($fp) && fwrite($fp, $response)) {
                        fclose($fp);
                    }
                } catch (\Exception $e) {}
            }
            $this->ddosLogic->ddos_setting('sys.feature_library', $response);
        } else {
            $response = getVersion('ddos_feature_library', '');
            if (empty($response)) {
                $response = $this->ddosLogic->ddos_setting('sys.feature_library');
            }
        }

        if (empty($response)) {
            $this->error('文件 data/conf/ddos_feature_library.txt 没有读写权限', null, '', 20);
        }
        $response = preg_replace("#[\r\n]{1,}#", "\n", $response);
        $feature_librarys = explode("\n", $response);
        $feature_librarys = array_filter($feature_librarys);

        $feature_pattern = [];
        // $feature_pattern_grade = [];
        $feature_imgpattern = [];
        $feature_msg = [];
        $feature_msg_grade = [];
        $feature_other = [];
        foreach ($feature_librarys as $key => $val) {
            if (!preg_match('/^#/i', $val)) {
                if (preg_match('/^pattern\|/i', $val)) {
                    $_k = preg_replace('/^pattern\|(\d+)\|(.*)$/i', '${1}', $val);
                    $feature_pattern[$_k]['value'] = preg_replace('/^(.*)\|value\|(.*)$/i', '${2}', $val);
                    // $_k2 = preg_replace('/^(\d{3,3})(.*)$/i', '${1}', $_k);
                    // $feature_pattern_grade[$_k2]['grade'] = $_k2;
                    // $feature_pattern_grade[$_k2]['value'] = preg_replace('/^pattern\|(\d+)\|([^\|]+)\|(.*)$/i', '${2}', $val);
                }
                else if (preg_match('/^imgpattern\|/i', $val)) {
                    $_k = preg_replace('/^imgpattern\|(\d+)\|(.*)$/i', '${1}', $val);
                    $feature_imgpattern[$_k]['value'] = preg_replace('/^(.*)\|value\|(.*)$/i', '${2}', $val);
                }
                else if (preg_match('/^msg\|/i', $val)) {
                    $_k = preg_replace('/^msg\|(\d+)\|(.*)$/i', '${1}', $val);
                    $feature_msg[$_k]['value'] = preg_replace('/^(.*)\|value\|([^\|]+)\|(.*)$/i', '${2}', $val);
                    $_k2 = preg_replace('/^(\d{3,3})(.*)$/i', '${1}', $_k);
                    $feature_msg_grade[$_k2]['grade'] = $_k2;
                    $feature_msg_grade[$_k2]['value'] = preg_replace('/^msg\|(\d+)\|([^\|]+)\|(.*)$/i', '${2}', $val);
                    $opt = preg_replace('/^(.*)\|opt_([\w\-]*)\|(.*)$/i', '${2}', $val);
                    $feature_msg[$_k]['opt']['event'] = $opt;
                    $feature_msg[$_k]['opt']['value'] = preg_replace('/^(.*)\|opt_([\w\-]*)\|([^\|]+)\|(.*)$/i', '${3}', $val);
                }
                else if (preg_match('/^other\|/i', $val)) {
                    $_k = preg_replace('/^other\|(\d+)\|(.*)$/i', '${1}', $val);
                    $feature_other[$_k]['value'] = preg_replace('/^(.*)\|value\|(.*)$/i', '${2}', $val);
                }
            }
        }
        // var_dump($feature_msg);exit;
        $setData = [
            'ddos_feature_pattern' => base64_encode(json_encode($feature_pattern)),
            // 'ddos_feature_pattern_grade' => base64_encode(json_encode($feature_pattern_grade)),
            'ddos_feature_imgpattern' => base64_encode(json_encode($feature_imgpattern)),
            'ddos_feature_msg' => base64_encode(json_encode($feature_msg)),
            'ddos_feature_msg_grade' => base64_encode(json_encode($feature_msg_grade)),
            'ddos_feature_other' => base64_encode(json_encode($feature_other)),
        ];
        tpSetting('ddos', $setData, 'cn');

        $assign_data['ddosSetting'] = tpSetting('ddos', [], 'cn');
        $assign_data['root_path'] = ROOT_PATH;
        $assign_data['doubtdata'] = $this->ddosLogic->ddos_doubtdata();
        // 后台入口文件
        $assign_data['adminbasefile'] = $this->ddosLogic->getAdminbasefile(false);
        // 图片木马检测的开关
        $assign_data['check_illegal_open'] = tpCache('weapp.weapp_check_illegal_open');

        $this->assign($assign_data);

        return $this->fetch();
    }

    /**
     * 整理文件列表
     * @return [type] [description]
     */
    public function ddos_arrange_files()
    {
        //防止超时/内存溢出
        function_exists('set_time_limit') && set_time_limit(0);
        @ini_set('memory_limit','-1');
        if (IS_POST) {
            // 清理缓存
            Cache::clear();
            delFile(RUNTIME_PATH, false, ['.htaccess']);
            delFile(DATA_PATH.'schema/', false, ['.htaccess']);
            delFile(DATA_PATH.'backup/', false, ['.htaccess']);
            // 重新生成数据表结构
            if (function_exists('schemaAllTable')) schemaAllTable();
            // 清除session过期文件
            if (function_exists('clear_session_file')) clear_session_file();
            // 生成语言包文件
            if (file_exists('application/common/model/ForeignPack.php')) model('ForeignPack')->updateLangFile();
            // 第一个先执行的范围，重置一些数据
            $init_runtype = input('param.init_runtype/s');
            if ('files' == $init_runtype) {
                // 重置ddos_log表
                $this->ddosLogic->ddos_log_reset();
            }

            // Win 环境
            if (IS_WIN) {
                $dir = APP_PATH.'../';
            }
            // 非 Win 环境
            else {
                $dir = ROOT_PATH;
            }
            if (!is_readable($dir)) {
                $dir = str_replace('\\', '/', $dir);
                $dir = rtrim($dir, '/').'/';
            }
            
            $list = [];
            // 安装目录
            $install_dir = glob('install*');
            if (!empty($install_dir)) {
                foreach ($install_dir as $key => $val) {
                    $list[] = $val;
                }
            }
            // 递归读取文件夹文件
            $this->ddosLogic->ddos_getDirFile($dir, '', $list, ['uploads','public/upload', 'upload']);
            // 存储读取后的文件列表
            $this->ddosLogic->ddos_setting('web.filelist', json_encode($list));
            /*// 递归读取文件夹
            $this->ddosLogic->ddos_getDir($dir, '', $list, ['uploads','public/upload', 'upload']);
            // 存储读取后的文件夹列表
            $this->ddosLogic->ddos_setting('web.source_dirlist', json_encode($list));*/
            // 获取官方对应版本的文件列表
            $this->ddosLogic->ddos_eyou_source_files();

            $this->success("读取文件完成");
        }
    }

    /**
     * 整理附件列表
     * @return [type] [description]
     */
    public function ddos_arrange_attachment()
    {
        //防止超时/内存溢出
        function_exists('set_time_limit') && set_time_limit(0);
        @ini_set('memory_limit','-1');
        if (IS_POST) {
            // 第一个先执行的范围，重置一些数据
            $init_runtype = input('param.init_runtype/s');
            if ('attachment' == $init_runtype) {
                // 重置ddos_log表
                $this->ddosLogic->ddos_log_reset();
            }
            
            $list = [];
            // 递归读取包括子站点的上传图片文件夹
            $dirs = [];
            foreach (['uploads','public/upload', 'upload'] as $key => $val) {
                $xing_str = '';
                for ($i=0; $i < 5; $i++) { 
                    $dir_arr = glob("{$xing_str}{$val}", GLOB_ONLYDIR);
                    if (!empty($dir_arr)) {
                        $dirs = array_merge($dirs, $dir_arr);
                    }
                    $xing_str .= '*/';
                }
                $dirs = array_unique($dirs);
            }
            // 递归读取文件夹文件
            $this->ddosLogic->get_dir_list($dirs, $list);
            // 存储读取后的文件列表
            $this->ddosLogic->ddos_setting('web.uploads_dirlist', json_encode($list));

            $this->success("读取目录完成");
        }
    }

    public function ddos_scan_file()
    {
        @ini_set('memory_limit', '-1');
        function_exists('set_time_limit') && set_time_limit(0);

        if (IS_POST) {
            $achievepage = input("param.achieve/d", 0); // 已扫描文件/目录数
            $doubtotal = input("param.doubtotal/d", 0); // 已扫描出的异常文件数
            $achievefile = input("param.achievefile/d", 0); // 已扫描文件数
            $allscantotal = input("param.allscantotal/d", 0); // 已扫描所有范围的文件数
            $scan_range = input("param.scan_range/s", 'files');
            if ('files' == $scan_range) {
                $data = $this->ddosLogic->ddosHandelScanFile($doubtotal, $achievepage, true, 100);
                // $data = $this->ddosLogic->ddosHandelScanFiles($doubtotal, $achievepage, $achievefile, $allscantotal, true, 50);
            } else if ('attachment' == $scan_range) {
                $data = $this->ddosLogic->ddosHandelScanAttachment($doubtotal, $achievepage, $achievefile, $allscantotal, true, 50);
            }
            $this->success($data[0], null, $data[1]);
        }

        $range_files      = input("param.range_files/d", 0);
        $range_attachment      = input("param.range_attachment/d", 0);
        $range_uploads      = input("param.range_uploads/d", 0);
        $setdata = [
            'ddos_scan_range_files' => $range_files,
            'ddos_scan_range_attachment' => $range_attachment,
            'ddos_scan_range_uploads' => $range_uploads,
            'ddos_scan_last_time' => getTime(),
        ];
        tpSetting('ddos', $setdata, 'cn');

        $this->assign($setdata);
        return $this->fetch();
    }

    /**
     * 删除可疑恶意文件
     * @return [type] [description]
     */
    public function ddos_delfile()
    {
        if (IS_AJAX) {
            $md5key = input('param.md5key/s');
            $md5key = preg_replace('/([^\w]+)/i', '', $md5key);
            $result = Db::name('ddos_log')->where(['md5key'=>$md5key, 'file_grade'=>['gt', 0]])->find();
            if (empty($result)) {
                $this->success('操作成功', null, ['file_doubt_total'=>0]);
            }

            $filename = !empty($result['file_name']) ? trim($result['file_name'], '/') : '';
            if (!empty($filename)) {
                $r = true;
                if (is_dir($filename)) {
                    $filename = str_replace('\\', '/', $filename);
                    $filename = trim($filename, '/');
                    if (!preg_match('/^([\/\\\]*)$/i', $filename)) {
                        try {
                            $r = delFile(ROOT_PATH.$filename, true);
                        } catch (\Exception $e) {
                            $this->error($e->getMessage());
                        }
                    }
                } else if (is_file($filename)) {
                    $feature_other = tpSetting('ddos.ddos_feature_other', [], 'cn');
                    $feature_other_arr = json_decode(base64_decode($feature_other), true);
                    $filetype = preg_replace("/^(.*)\.([a-z]+)$/i", '${2}', $filename);
                    $phpfile = strtolower(stristr($filename,'.php'));
                    if ($phpfile || '*' == $feature_other_arr[10060]['value'] || in_array($filetype, explode(',', $feature_other_arr[10060]['value']))) {
                        try {
                            $r = unlink('./'.$filename);
                        } catch (\Exception $e) {
                            $r = false;
                            $this->error($e->getMessage());
                        }
                    }
                }

                if ($r !== false) {
                    $redata = $this->ddos_update_doubt_total($result);
                    $this->success('操作成功', null, ['file_doubt_total'=>$redata['file_doubt_total']]);
                }
            }
        }
        $this->error('操作失败');
    }

    private function ddos_update_doubt_total($result = [])
    {
        $max_file_num = Db::name('ddos_log')->where(['admin_id'=>$this->admin_id])->max('file_num');
        Db::name('ddos_log')->where(['id'=>$result['id']])->delete();
        // 重新统计异常等文件数
        $file_doubt_total = (int)Db::name('ddos_log')->where(['admin_id'=>$this->admin_id, 'file_grade'=>['gt',0]])->count();
        $update = [
            'file_doubt_total'=>$file_doubt_total,
            'update_time'=>getTime(),
        ];
        $info = Db::name('ddos_log')->where(['admin_id'=>$this->admin_id])->order('file_num desc, id desc')->find();
        if ($max_file_num == $info['file_num']) {
            $update['file_num'] = $max_file_num;
        }
        Db::name('ddos_log')->where(['id'=>$info['id']])->update($update);

        return [
            'file_doubt_total' => $file_doubt_total,
        ];
    }

    public function ddos_download_file()
    {
        $md5key = input('param.md5key/s');
        $md5key = preg_replace('/([^\w]+)/i', '', $md5key);
        $result = Db::name('ddos_log')->where(['md5key'=>$md5key, 'file_grade'=>['gt', 0], 'admin_id'=>$this->admin_id])->find();
        if (!empty($result)) {
            $file_all_name = !empty($result['file_name']) ? trim($result['file_name'], '/') : '';
            if (!empty($file_all_name)) {
                if (is_dir($file_all_name)) {
                    $this->error('不支持下载目录');
                }
                else if (is_file($file_all_name)) {
                    $filetype = preg_replace("/^(.*)\.([a-z]+)$/i", '${2}', $file_all_name); // pathinfo($file_all_name, PATHINFO_EXTENSION);
                    $file_alias_name = preg_replace('/^(.*)(\/|\\\)([^\/\\\]+)$/i', '${3}', $file_all_name);
                    if (in_array($filetype, ['zip','rar','gz'])) {
                        $url = request()->domain().ROOT_DIR.'/'.$file_all_name;
                        $this->redirect($url);
                    } else {
                        download_file('/'.$file_all_name, '', $file_alias_name);
                    }
                    exit; 
                }
            }
        }
        $this->error('下载失败');
    }

    /**
     * 替换文件
     * @return [type] [description]
     */
    public function ddos_replace_file()
    {
        $version = getVersion();
        if (version_compare($version,'v1.6.9','<')) {
            $this->error("系统版本过低，请升级到v1.6.9或更高版本");
        }
        $md5key = input('param.md5key/s');
        $md5key = preg_replace('/([^\w]+)/i', '', $md5key);
        $result = Db::name('ddos_log')->where(['md5key'=>$md5key, 'file_grade'=>['gt', 0], 'admin_id'=>$this->admin_id])->find();
        if (!empty($result)) {
            $file_all_name = !empty($result['file_name']) ? trim($result['file_name'], '/') : '';
            if (is_file($file_all_name)) {
                $local_file = ROOT_PATH."{$file_all_name}"; // 本地路径 不存在可以自动创建
                tp_mkdir(dirname($local_file));
                clearstatcache(); // 清除文件夹权限缓存
                if (!is_writeable($local_file)) {
                    $this->error("文件或所在目录没有写入权限，无法替换");
                }
                $url = 'ht'.'tp'.':/'.'/'.'up'.'da'.'te'.'.e'.'yo'.'u.5'.'f'.'a.'.'c'.'n/other/repair/'.$version.'/'.$file_all_name;
                // 替换是否成功
                $is_replace = true;
                // 打开远程文件
                $remote_file = @fopen($url, 'r');
                if (empty($remote_file)) {
                    $is_replace = false;
                } else {
                    // 打开本地文件
                    $fp = @fopen($local_file, 'w');
                    // 使用流下载文件内容
                    while (!feof($remote_file)) {
                        $content = @fread($remote_file, 1024);
                        if (empty($content)) {
                            $is_replace = false;
                            break;
                        }
                        fwrite($fp, $content);
                    }
                    // 关闭文件
                    fclose($remote_file);
                }

                if (false === $is_replace) {
                    $fp = @fopen($url, 'r');
                    if (!empty($fp)) {
                        if (@file_put_contents($local_file, $fp)) {
                            $is_replace = true;
                        }
                    }
                }

                if (true === $is_replace) {
                    $redata = $this->ddos_update_doubt_total($result);
                    $this->success('操作成功', null, ['file_doubt_total'=>$redata['file_doubt_total']]);
                }
            }
        }
        $this->error('替换失败');
    }

    /*-----------------------ddos攻击脚本查杀 end-----------------------*/

    /**
     * 后台登录路径
     * @return [type] [description]
     */
    public function popup_adminbasefile()
    {
        if (IS_POST) {
            $post = input('post.');
            /*-------------------后台安全配置 start-------------------*/
            $param = [];
            // 自定义后台路径名
            $adminbasefile = preg_replace('/([^\w\_\-])/i', '', trim($post['adminbasefile'])).'.php'; // 新的文件名
            $param['web_adminbasefile'] = $this->root_dir.'/'.$adminbasefile; // 支持子目录
            $baseFile = explode('/', $this->request->baseFile());
            $adminbasefile_old = end($baseFile); // 旧的文件名
            if ('index.php' == $adminbasefile) {
                $this->error("后台路径禁止使用index", null, '', 1);
            }
            /*-------------------后台安全配置 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', $param, $val['mark']);
                }
            } else {
                tpCache('web', $param);
            }
            /*--end*/

            $refresh = false;

            /*-------------------后台安全配置 start-------------------*/
            // 更改自定义后台路径名 - 刷新整个后台
            $gourl = request()->domain().$this->root_dir.'/'.$adminbasefile; // 支持子目录
            if ($adminbasefile_old != $adminbasefile && eyPreventShell($adminbasefile_old)) {
                if (file_exists($adminbasefile_old)) {
                    if(rename($adminbasefile_old, $adminbasefile)) {
                        $refresh = true;
                    }
                } else {
                    $this->error("根目录{$adminbasefile_old}文件不存在！", null, '', 2);
                }
            }
            /*-------------------后台安全配置 end-------------------*/

            if ($refresh) {
                $this->success('操作成功', $gourl, '', 1, [], '_parent');
            }

            $this->success('操作成功', url('Security/ddos_kill'));
        }

        // 后台入口文件
        $adminbasefile = $this->ddosLogic->getAdminbasefile(false);
        $adminbasefile = preg_replace('/^(.*)\.([^\.]+)$/i', '$1', $adminbasefile);
        $this->assign('adminbasefile', $adminbasefile);

        return $this->fetch();
    }

    /**
     * 登录超时
     * @return [type] [description]
     */
    public function popup_login_expiretime()
    {
        if (IS_POST) {
            $post = input('post.');

            /*-------------------后台安全配置 start-------------------*/
            $param = [
                'web_login_expiretime' => $post['web_login_expiretime'],
                'login_expiretime_old' => $post['login_expiretime_old'],
            ];
            // 后台登录超时
            $web_login_expiretime = $param['web_login_expiretime'];
            $login_expiretime_old = $param['login_expiretime_old'];
            unset($param['login_expiretime_old']);
            if ($login_expiretime_old != $web_login_expiretime) {
                $web_login_expiretime = preg_replace('/^(\d{0,})(.*)$/i', '${1}', $web_login_expiretime);
                empty($web_login_expiretime) && $web_login_expiretime = config('login_expire');
                if ($web_login_expiretime > 2592000) {
                    $web_login_expiretime = 2592000; // 最多一个月
                }
                $param['web_login_expiretime'] = $web_login_expiretime;
                //前台登录超时时间
                $users_login_expiretime = getUsersConfigData('users.users_login_expiretime');
                //前台和后台谁设置的时间大就用谁的做session过期时间
                $max_login_expiretime = $web_login_expiretime;
                if ($web_login_expiretime < $users_login_expiretime){
                    $max_login_expiretime = $users_login_expiretime;
                }
            }
            /*-------------------后台安全配置 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', $param, $val['mark']);
                }
            } else {
                tpCache('web', $param);
            }
            /*--end*/

            /*-------------------后台安全配置 start-------------------*/
            // 更改session会员设置 - session有效期（后台登录超时）
            if ($login_expiretime_old != $web_login_expiretime) {
                $session_conf = [];
                $session_file = APP_PATH.'admin/conf/session_conf.php';
                if (file_exists($session_file)) {
                    require_once($session_file);
                    $session_conf_tmp = EY_SESSION_CONF;
                    if (!empty($session_conf_tmp)) {
                        $session_conf_tmp = json_decode($session_conf_tmp, true);
                        if (!empty($session_conf_tmp) && is_array($session_conf_tmp)) {
                            $session_conf = $session_conf_tmp;
                        }
                    }
                }
                $session_conf['expire'] = $max_login_expiretime;
                $str_session_conf = '<?php'.PHP_EOL.'$session_1600593464 = json_encode('.var_export($session_conf,true).');'.PHP_EOL.'define(\'EY_SESSION_CONF\', $session_1600593464);';
                @file_put_contents(APP_PATH . 'admin/conf/session_conf.php', $str_session_conf);
            }
            /*-------------------后台安全配置 end-------------------*/

            $this->success('操作成功', url('Security/ddos_kill'));
        }

        return $this->fetch();
    }

    /**
     * 登录防爆设置
     * @return [type] [description]
     */
    public function popup_flameproof()
    {
        if (IS_POST) {
            $post = input('post.');

            /*-------------------后台安全配置 start-------------------*/
            $param = [
                'web_login_lockopen'    => !empty($post['web_login_lockopen']) ? 1 : 0,
            ];
            // 开启锁定才修改相应的配置值
            if (!empty($param['web_login_lockopen'])) {
                $param['web_login_errtotal'] = $post['web_login_errtotal'];
                $param['web_login_errexpire'] = $post['web_login_errexpire'];
            }
            /*-------------------后台安全配置 end-------------------*/

            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')
                    ->cache(true, EYOUCMS_CACHE_TIME, 'language')
                    ->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', $param, $val['mark']);
                }
            } else {
                tpCache('web', $param);
            }
            /*--end*/

            $this->success('操作成功', url('Security/ddos_kill'));
        }

        return $this->fetch();
    }

    /**
     * 密保问题设置
     * @return [type] [description]
     */
    public function popup_second()
    {
        $is_founder = 0;
        if (-1 == $this->admin_info['role_id'] && empty($this->admin_info['parent_id'])) {
            $is_founder = 1;
        }
        $this->admin_info['is_founder'] = $is_founder;
        $this->assign('admin_info', $this->admin_info);

        // 安全验证配置
        $security = tpSetting('security');
        if (isset($security['security_verifyfunc'])) {
            $security['security_verifyfunc'] = json_decode($security['security_verifyfunc'], true);
        }
        $security_askanswer_content = '';
        if (!empty($security['security_askanswer_list'])) {
            $security_askanswer_list = json_decode($security['security_askanswer_list'], true);
            $security['security_askanswer_list'] = $security_askanswer_list;
        }
        if (empty($security_askanswer_list)) {
            $security_askanswer_list = config('global.security_askanswer_list');
        }
        $security_askanswer_content = implode(PHP_EOL, $security_askanswer_list);
        $this->assign('security', $security);
        $this->assign('security_askanswer_content', $security_askanswer_content);

        if (!empty($security['security_ask'])) {
            $security_ask = $security['security_ask'];
            if (!in_array($security_ask, $security_askanswer_list)) {
                $security_askanswer_list[] = $security_ask;
            }
        }
        $this->assign('security_askanswer_list', $security_askanswer_list);

        return $this->fetch();
    }

    // 图片木马的开关设置
    public function ddos_check_illegal_open()
    {
        $msg = "";
        $value = input('post.value/d');
        if (empty($value)) {
            $msg = "开启成功";
        } else {
            $msg = "关闭成功";
        }
        /*多语言*/
        if (is_language()) {
            $langRow = \think\Db::name('language')->order('id asc')->select();
            foreach ($langRow as $key => $val) {
                tpCache('weapp', ['weapp_check_illegal_open' => $value], $val['mark']);
            }
        } else { // 单语言
            tpCache('weapp', ['weapp_check_illegal_open' => $value]);
        }
        /*--end*/
        $this->success($msg);
    }
}