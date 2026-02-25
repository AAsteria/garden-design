<?php
/**
 * 易优CMS
 * ============================================================================
 * 版权所有 2016-2028 海南赞赞网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.eyoucms.com
 * ----------------------------------------------------------------------------
 * 如果商业用途务必到官方购买正版授权, 以免引起不必要的法律纠纷.
 * ============================================================================
 * Author: 小虎哥 <1105415366@qq.com>
 * Date: 2018-4-3
 */

namespace app\admin\logic;

use think\Config;
use think\Model;
use think\Db;

/**
 * 逻辑定义
 * Class CatsLogic
 * @package admin\Logic
 */
class AjaxLogic extends Model
{
    private $request = null;
    private $admin_lang = 'cn';
    private $main_lang = 'cn';

    /**
     * 析构函数
     */
    function  __construct() {
        $this->request = request();
        $this->admin_lang = get_admin_lang();
        $this->main_lang = get_main_lang();
    }

    /**
     * 进入登录页面需要异步处理的业务
     */
    public function login_handle()
    {
        // $this->repairAdmin(); // 修复管理员ID为0的问题
        $this->saveBaseFile(); // 存储后台入口文件路径，比如：/login.php
        clear_session_file(); // 清理过期的data/session文件
    }

    /**
     * 修复管理员
     * @return [type] [description]
     */
    private function repairAdmin()
    {
        $row = [];
        $result = Db::name('admin')->field('admin_id,user_name')->order('add_time asc')->select();
        $total = count($result);
        foreach ($result as $key => $val) {
            $pre_admin_id = $next_admin_id = 0;
            if (empty($val['admin_id'])) {
                if (1 == $total) {
                    Db::name('admin')->where(['user_name'=>$val['user_name']])->update(['admin_id'=>1, 'update_time'=>getTime()]);
                } else {
                    $pre_admin_id = empty($key) ? 0 : $result[$key - 1]['admin_id'];
                    if ($key < ($total - 1)) {
                        $next_admin_id = $result[$key + 1]['admin_id'];
                    } else {
                        $next_admin_id = $pre_admin_id + 2;
                    }

                    if (($next_admin_id - $pre_admin_id) >= 2) {
                        $admin_id = $pre_admin_id + 1;
                        Db::name('admin')->where(['user_name'=>$val['user_name']])->update(['admin_id'=>$admin_id, 'update_time'=>getTime()]);
                    }
                }
            }
        }
    }

    /**
     * 清理未存在的左侧菜单
     * @return [type] [description]
     */
    public function admin_menu_clear()
    {
        $del_ids = [];
        $codeArr = Db::name('weapp')->column('code');
        $list = Db::name('admin_menu')->where(['controller_name'=>'Weapp','action_name'=>'execute'])->select();
        foreach ($list as $key => $val) {
            $code = preg_replace('/^(.*)\|sm\|([^\|]+)\|sc\|(.*)$/i', '${2}', $val['param']);
            if (!in_array($code, $codeArr)) {
                $del_ids[] = $val['id'];
            }
        }
        if (!empty($del_ids)) {
            Db::name('admin_menu')->where(['id'=>['IN', $del_ids]])->delete();
        }
    }

    /**
     * 进入欢迎页面需要异步处理的业务
     */
    public function welcome_handle()
    {
        getVersion('version_themeusers', 'v1.0.1', true);
        getVersion('version_themeshop', 'v1.0.1', true);
        $this->addChannelFile(); // 自动补充自定义模型的文件
        $this->saveBaseFile(); // 存储后台入口文件路径，比如：/login.php
        $this->renameInstall(); // 重命名安装目录，提高网站安全性
        $this->renameSqldatapath(); // 重命名数据库备份目录，提高网站安全性
        $this->del_adminlog(); // 只保留最近一个月的操作日志
        model('Member')->batch_update_userslevel(); // 批量更新会员过期等级
        // tpversion(); // 统计装载量，请勿删除，谢谢支持！
    }
    
    /**
     * 自动补充自定义模型的文件
     */
    public function addChannelFile()
    {
        try {
            $list = Db::name('channeltype')->where([
                'ifsystem'  => 0,
                ])->select();
            if (!empty($list)) {
                $cmodSrc = "data/model/application/common/model/CustomModel.php";
                $cmodContent = @file_get_contents($cmodSrc);
                $hctlSrc = "data/model/application/home/controller/CustomModel.php";
                $hctlContent = @file_get_contents($hctlSrc);
                $hmodSrc = "data/model/application/home/model/CustomModel.php";
                $hmodContent = @file_get_contents($hmodSrc);
                foreach ($list as $key => $val) {
                    $file = "application/common/model/{$val['ctl_name']}.php";
                    if (!file_exists($file)) {
                        $cmodContent = str_replace('CustomModel', $val['ctl_name'], $cmodContent);
                        $cmodContent = str_replace('custommodel', strtolower($val['nid']), $cmodContent);
                        $cmodContent = str_replace('CUSTOMMODEL', strtoupper($val['nid']), $cmodContent);
                        @file_put_contents($file, $cmodContent);
                    }
                    $file = "application/home/controller/{$val['ctl_name']}.php";
                    if (!file_exists($file)) {
                        $hctlContent = str_replace('CustomModel', $val['ctl_name'], $hctlContent);
                        $hctlContent = str_replace('custommodel', strtolower($val['nid']), $hctlContent);
                        $hctlContent = str_replace('CUSTOMMODEL', strtoupper($val['nid']), $hctlContent);
                        @file_put_contents($file, $hctlContent);
                    }
                    $file = "application/home/model/{$val['ctl_name']}.php";
                    if (!file_exists($file)) {
                        $hmodContent = str_replace('CustomModel', $val['ctl_name'], $hmodContent);
                        $hmodContent = str_replace('custommodel', strtolower($val['nid']), $hmodContent);
                        $hmodContent = str_replace('CUSTOMMODEL', strtoupper($val['nid']), $hmodContent);
                        @file_put_contents($file, $hmodContent);
                    }
                }
            }
        } catch (\Exception $e) {}
    }
    
    /**
     * 只保留最近一个月的操作日志
     */
    public function del_adminlog()
    {
        try {
            $is_system = true;
            if (file_exists(ROOT_PATH.'weapp/Equal/logic/EqualLogic.php')) {
                $equalLogic = new \weapp\Equal\logic\EqualLogic;
                if (method_exists($equalLogic, 'del_adminlog')) {
                    $is_system = false;
                    $equalLogic->del_adminlog();
                }
            }
            else if (file_exists(ROOT_PATH.'weapp/Systemdoctor/logic/SystemdoctorLogic.php')) {
                $systemdoctorLogic = new \weapp\Systemdoctor\logic\SystemdoctorLogic;
                if (method_exists($systemdoctorLogic, 'del_adminlog')) {
                    $is_system = false;
                    $systemdoctorLogic->del_adminlog();
                }
            }
            if ($is_system) {
                $mtime = strtotime("-1 month");
                Db::name('admin_log')->where([
                    'log_time'  => ['lt', $mtime],
                    ])->delete();
            }
        } catch (\Exception $e) {}
    }

    /*
     * 修改备份数据库目录
     */
    private function renameSqldatapath() {
        $default_sqldatapath = config('DATA_BACKUP_PATH');
        if (is_dir('.'.$default_sqldatapath)) { // 还是符合初始默认的规则的链接方式
            $dirname = get_rand_str(20, 0, 1);
            $new_path = '/data/sqldata_'.$dirname;
            if (@rename(ROOT_PATH.ltrim($default_sqldatapath, '/'), ROOT_PATH.ltrim($new_path, '/'))) {
                /*多语言*/
                if (is_language()) {
                    $langRow = \think\Db::name('language')->order('id asc')->select();
                    foreach ($langRow as $key => $val) {
                        tpCache('web', ['web_sqldatapath'=>$new_path], $val['mark']);
                    }
                } else { // 单语言
                    tpCache('web', ['web_sqldatapath'=>$new_path]);
                }
                /*--end*/
            }
        }
    }

    /**
     * 重命名安装目录，提高网站安全性
     * 在 Admin@login 和 Index@index 操作下
     */
    private function renameInstall()
    {
        if (stristr($this->request->host(), 'eycms.hk')) {
            return true;
        }
        $install_path = ROOT_PATH.'install';
        if (is_dir($install_path) && file_exists($install_path)) {
            $install_time = get_rand_str(20, 0, 1);
            $new_path = ROOT_PATH.'install_'.$install_time;
            @rename($install_path, $new_path);
        }
        else {
            $dirlist = glob('install_*');
            $install_dirname = current($dirlist);
            if (!empty($install_dirname)) {
                /*---修补v1.1.6版本删除的安装文件 install.lock start----*/
                if (!empty($_SESSION['isset_install_lock'])) {
                    return true;
                }
                $_SESSION['isset_install_lock'] = 1;
                /*---修补v1.1.6版本删除的安装文件 install.lock end----*/

                $install_path = ROOT_PATH.$install_dirname;
                if (preg_match('/^install_[0-9]{10}$/i', $install_dirname)) {
                    $install_time = get_rand_str(20, 0, 1);
                    $install_dirname = 'install_'.$install_time;
                    $new_path = ROOT_PATH.$install_dirname;
                    if (@rename($install_path, $new_path)) {
                        $install_path = $new_path;
                        /*多语言*/
                        if (is_language()) {
                            $langRow = \think\Db::name('language')->order('id asc')->select();
                            foreach ($langRow as $key => $val) {
                                tpSetting('install', ['install_dirname'=>$install_time], $val['mark']);
                            }
                        } else { // 单语言
                            tpSetting('install', ['install_dirname'=>$install_time]);
                        }
                        /*--end*/
                    }
                }

                $filename = $install_path.DS.'install.lock';
                if (!file_exists($filename)) {
                    @file_put_contents($filename, '');
                }
            }
        }
    }

    /**
     * 存储后台入口文件路径，比如：/login.php
     * 在 Admin@login 和 Index@index 操作下
     */
    private function saveBaseFile()
    {
        $data = [];
        $data['web_adminbasefile'] = $this->request->baseFile();
        $data['web_cmspath'] = ROOT_DIR; // EyouCMS安装目录
        /*多语言*/
        if (is_language()) {
            $langRow = \think\Db::name('language')->field('mark')->order('id asc')->select();
            foreach ($langRow as $key => $val) {
                tpCache('web', $data, $val['mark']);
            }
        } else { // 单语言
            tpCache('web', $data);
        }
        /*--end*/
    }

    /**
     * 升级前台会员中心的模板文件
     */
    public function update_template($type = '')
    {
        if (!empty($type)) {
            if ('users' == $type) {
                if (file_exists(ROOT_PATH.'template/'.TPL_THEME.'pc/users') || file_exists(ROOT_PATH.'template/'.TPL_THEME.'mobile/users')) {
                    $upgrade = getDirFile(DATA_PATH.'backup'.DS.'tpl');
                    if (!empty($upgrade) && is_array($upgrade)) {
                        delFile(DATA_PATH.'backup'.DS.'template_www');
                        // 升级之前，备份涉及的源文件
                        foreach ($upgrade as $key => $val) {
                            $val_tmp = str_replace("template/", "template/".TPL_THEME, $val);
                            $source_file = ROOT_PATH.$val_tmp;
                            if (file_exists($source_file)) {
                                $destination_file = DATA_PATH.'backup'.DS.'template_www'.DS.$val_tmp;
                                tp_mkdir(dirname($destination_file));
                                @copy($source_file, $destination_file);
                            }
                        }

                        // 递归复制文件夹
                        $this->recurse_copy(DATA_PATH.'backup'.DS.'tpl', rtrim(ROOT_PATH, DS));
                    }
                    /*--end*/
                }
            }
        }
    }

    /**
     * 自定义函数递归的复制带有多级子目录的目录
     * 递归复制文件夹
     *
     * @param string $src 原目录
     * @param string $dst 复制到的目录
     * @return string
     */                        
    //参数说明：            
    //自定义函数递归的复制带有多级子目录的目录
    private function recurse_copy($src, $dst)
    {
        $planPath_pc = "template/".TPL_THEME."pc/";
        $planPath_m = "template/".TPL_THEME."mobile/";
        $dir = opendir($src);

        /*pc和mobile目录存在的情况下，才拷贝会员模板到相应的pc或mobile里*/
        $dst_tmp = str_replace('\\', '/', $dst);
        $dst_tmp = rtrim($dst_tmp, '/').'/';
        if (stristr($dst_tmp, $planPath_pc) && file_exists($planPath_pc)) {
            tp_mkdir($dst);
        } else if (stristr($dst_tmp, $planPath_m) && file_exists($planPath_m)) {
            tp_mkdir($dst);
        }
        /*--end*/

        while (false !== $file = readdir($dir)) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $needle = '/template/'.TPL_THEME;
                    $needle = rtrim($needle, '/');
                    $dstfile = $dst . '/' . $file;
                    if (!stristr($dstfile, $needle)) {
                        $dstfile = str_replace('/template', $needle, $dstfile);
                    }
                    $this->recurse_copy($src . '/' . $file, $dstfile);
                }
                else {
                    if (file_exists($src . DIRECTORY_SEPARATOR . $file)) {
                        /*pc和mobile目录存在的情况下，才拷贝会员模板到相应的pc或mobile里*/
                        $rs = true;
                        $src_tmp = str_replace('\\', '/', $src . DIRECTORY_SEPARATOR . $file);
                        if (stristr($src_tmp, $planPath_pc) && !file_exists($planPath_pc)) {
                            continue;
                        } else if (stristr($src_tmp, $planPath_m) && !file_exists($planPath_m)) {
                            continue;
                        }
                        /*--end*/
                        $rs = @copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                        if($rs) {
                            @unlink($src . DIRECTORY_SEPARATOR . $file);
                        }
                    }
                }
            }
        }
        closedir($dir);
    }
    
    // 记录当前是多语言还是单语言到文件里
    public function system_langnum_file()
    {
        model('Language')->setLangNum();
    }
    
    // 记录当前是否多站点到文件里
    public function system_citysite_file()
    {
        $key = base64_decode('cGhwLnBocF9zZXJ2aWNlbWVhbA==');
        $value = tpCache($key);
        if (2 > $value) {
            /*多语言*/
            if (is_language()) {
                $langRow = Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache('web', ['web_citysite_open'=>0], $val['mark']);
                }
            } else { // 单语言
                tpCache('web', ['web_citysite_open'=>0]);
            }
            /*--end*/
            model('Citysite')->setCitysiteOpen();
        }
    }

    public function admin_logic_1609900642()
    {
        // 更新自定义的样式表文件
        $version = getVersion();
        $syn_admin_logic_1697156935 = tpSetting('syn.admin_logic_1697156935', [], 'cn');
        if ($version != $syn_admin_logic_1697156935) {
            $r = $this->admin_update_theme_css();
            if ($r !== false) {
                tpSetting('syn', ['admin_logic_1697156935'=>$version], 'cn');
            }
        }

        $vars1 = 'cGhwLnBo'.'cF9zZXJ2aW'.'NlaW5mbw==';
        $vars1 = base64_decode($vars1);
        $data = tpCache($vars1);
        $data = mchStrCode($data, 'DECODE');
        $data = json_decode($data, true);
        if (empty($data['pid']) || 2 > $data['pid']) return true;
        $file = "./data/conf/{$data['code']}.txt";
        $vars2 = 'cGhwX3Nl'.'cnZpY2V'.'tZWFs';
        $vars2 = base64_decode($vars2);
        if (!file_exists($file)) {
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache('php', [$vars2=>1], $val['mark']);
                }
            } else { // 单语言
                tpCache('php', [$vars2=>1]);
            }
            /*--end*/
        } else {
            /*多语言*/
            if (is_language()) {
                $langRow = \think\Db::name('language')->order('id asc')->select();
                foreach ($langRow as $key => $val) {
                    tpCache('php', [$vars2=>$data['pid']], $val['mark']);
                }
            } else { // 单语言
                tpCache('php', [$vars2=>$data['pid']]);
            }
            /*--end*/
        }
    }

    /**
     * 更新后台自定义的样式表文件
     * @return [type] [description]
     */
    public function admin_update_theme_css()
    {
        $r = false;
        $file = APP_PATH.'admin/template/public/theme_css.htm';
        if (file_exists($file)) {
            $view = \think\View::instance(\think\Config::get('template'), \think\Config::get('view_replace_str'));
            $view->assign('global', tpCache('global'));
            $css = $view->fetch($file);
            $css = str_replace(['<style type="text/css">','</style>'], '', $css);
            @chmod($file, 0755);
            $r = @file_put_contents(ROOT_PATH.'public/static/admin/css/theme_style.css', $css);
        }

        return $r;
    }

    /**
     * 更新会员中心自定义的样式表文件
     * @return [type] [description]
     */
    public function users_update_theme_css()
    {
        
    }

    public function admin_logic_1623036205()
    {
        $arr = [
            ROOT_PATH."application/weapp",
            ROOT_PATH."data/model/application/admin",
            ROOT_PATH."data/model/custom_model_path/recruit.filelist.txt",
            ROOT_PATH."data/weapp/Sample/weapp/Sample/behavior/weapp",
            ROOT_PATH."data/weapp/Sample/template/plugins/sample/index.htm",
            ROOT_PATH."public/static/admin/js/qtip",
            ROOT_PATH."application/admin/controller/OrderVerify.php",
            ROOT_PATH."application/admin/controller/Recruit.php",
            ROOT_PATH."application/admin/logic/AuthModularLogic.php",
            ROOT_PATH."application/admin/logic/AuthRoleLogic.php",
            ROOT_PATH."application/admin/logic/NavigationLogic.php",
            ROOT_PATH."application/admin/model/FormField.php",
            ROOT_PATH."application/admin/model/Recruit.php",
            ROOT_PATH."application/admin/template/admin/admin_pwd.htm",
            ROOT_PATH."application/admin/template/admin/admin_pwd_ajax.htm",
            ROOT_PATH."application/admin/template/ajax/new_appoint_tplfile.htm",
            ROOT_PATH."application/admin/template/archives/left.htm",
            ROOT_PATH."application/admin/template/channeltype/field_management.htm",
            ROOT_PATH."application/admin/template/encodes/theme_conf.htm",
            ROOT_PATH."application/admin/template/field/bar.htm",
            ROOT_PATH."application/admin/template/field/modelfield.htm",
            ROOT_PATH."application/admin/template/foreign/htmlfilename_handel.htm",
            ROOT_PATH."application/admin/template/foreign/htmlfilename_index.htm",
            ROOT_PATH."application/admin/template/foreign/seo_desc_handel.htm",
            ROOT_PATH."application/admin/template/form/view_form_data.htm",
            ROOT_PATH."application/admin/template/guestbook/btn.htm",
            ROOT_PATH."application/admin/template/index/switch_map_new.htm",
            ROOT_PATH."application/admin/template/index/theme_add_welcome_tplfile.htm",
            ROOT_PATH."application/admin/template/level/level_bar.htm",
            ROOT_PATH."application/admin/template/member/ajax_set_oauth_config.htm",
            ROOT_PATH."application/admin/template/member/pay_set.htm",
            ROOT_PATH."application/admin/template/order_verify",
            ROOT_PATH."application/admin/template/product/btn.htm",
            ROOT_PATH."application/admin/template/recruit",
            ROOT_PATH."application/admin/template/security/ajax_force_verify.htm",
            ROOT_PATH."application/admin/template/system/clearCache.htm",
            ROOT_PATH."application/admin/template/system/customvar_add.htm",
            ROOT_PATH."application/admin/template/system/customvar_edit.htm",
            ROOT_PATH."application/admin/template/system/customvar_recycle.htm",
            ROOT_PATH."application/admin/template/system/oss.htm",
            ROOT_PATH."application/admin/template/system/pay_set.htm",
            ROOT_PATH."application/admin/template/system/region.htm",
            ROOT_PATH."application/admin/template/uploadify/site.htm",
            ROOT_PATH."application/api/controller/Count.php",
            ROOT_PATH."application/common/common.php",
            ROOT_PATH."core/library/think/behavior/admin/WeappBehavior.php",
            ROOT_PATH."core/library/think/template/taglib/api/TagPickUpPointsList.php",
            ROOT_PATH."core/library/think/template/taglib/eyou/TagForm.php",
            ROOT_PATH."core/library/think/template/taglib/eyou/TagLogin.php",
            ROOT_PATH."public/plugins/bootstrap/img/Thumbs.db",
            ROOT_PATH."public/plugins/Ueditor/third-party/snapscreen/UEditorSnapscreen.exe",
            ROOT_PATH."public/static/admin/font/css/font-awesome.css",
            ROOT_PATH."public/static/admin/images/admin.png",
            ROOT_PATH."public/static/admin/images/ajaxLoader.gif",
            ROOT_PATH."public/static/admin/images/channel_bg.gif",
            ROOT_PATH."public/static/admin/images/circle_level_bg.png",
            ROOT_PATH."public/static/admin/images/cms_edit_bg.png",
            ROOT_PATH."public/static/admin/images/cms_edit_bg_line.png",
            ROOT_PATH."public/static/admin/images/combine_img2.png",
            ROOT_PATH."public/static/admin/images/cut_bg.png",
            ROOT_PATH."public/static/admin/images/ddn.png",
            ROOT_PATH."public/static/admin/images/down.gif",
            ROOT_PATH."public/static/admin/images/iframe_bg.png",
            ROOT_PATH."public/static/admin/images/login_formBg.png",
            ROOT_PATH."public/static/admin/images/login_icon.png",
            ROOT_PATH."public/static/admin/images/logo-login.png",
            ROOT_PATH."public/static/admin/images/size.gif",
            ROOT_PATH."public/static/admin/images/size_channel.gif",
            ROOT_PATH."public/static/admin/images/socp.png",
            ROOT_PATH."public/static/admin/images/subbag.png",
            ROOT_PATH."public/static/admin/images/theme/default.png",
            ROOT_PATH."public/static/admin/images/up.gif",
            ROOT_PATH."public/static/admin/images/user1-128x128.jpg",
            ROOT_PATH."public/static/admin/images/uup.png",
            ROOT_PATH."public/static/admin/images/vertline.gif",
            ROOT_PATH."public/static/admin/images/xianbg.png",
            ROOT_PATH."public/static/admin/js/jquery.purebox.js",
            ROOT_PATH."public/static/common/js/jquery.editable.min.js",
            ROOT_PATH."public/static/common/js/tag_login.js",
            ROOT_PATH."vendor/PHPExcel.zip",
            ROOT_PATH."vendor/phpmailer/.gitattributes",
            ROOT_PATH."vendor/phpmailer/.gitignore",
            ROOT_PATH."vendor/phpmailer/.scrutinizer.yml",
            ROOT_PATH."vendor/phpmailer/.travis.yml",
            ROOT_PATH."vendor/phpmailer/changelog.md",
            ROOT_PATH."vendor/phpmailer/README.md",
            ROOT_PATH."vendor/phpmailer/SECURITY.md",
            ROOT_PATH."vendor/phpmailer/travis.phpunit.xml.dist",
        ];
        foreach ($arr as $key => $val) {
            if (is_dir($val)) {
                try {
                    delFile($val, true);
                } catch (\Exception $e) {}
            } else if (file_exists($val)) {
                @unlink($val);
            }
        }
        
        // 同步模板的付费选择支付文件到前台模板指定位置
        $this->copy_tplpayfile();
        // 自动更新插件里的jquery文件为最新版本，修复jquery漏洞
        $this->copy_jquery();
        // 升级v1.6.7版本要处理的数据
        $this->eyou_v167_handle_data();
        // 升级后，清理缓存文件
        // $this->upgrade_clear_cache();
    }

    /**
     * 升级后，清理缓存文件
     * @return [type] [description]
     */
    private function upgrade_clear_cache()
    {
        $version = getVersion();
        $syn_admin_logic_1726881989 = tpSetting('syn.syn_admin_logic_1726881989', [], 'cn');
        if ($syn_admin_logic_1726881989 != $version) {
            try {
                delFile(rtrim(RUNTIME_PATH, '/'));
                tpSetting('syn', ['syn_admin_logic_1726881989' => $version], 'cn');
            } catch (\Exception $e) {}
        }
    }

    /**
     * 自动更新插件里的jquery文件为最新版本，修复jquery漏洞
     * @return [type] [description]
     */
    private function copy_jquery()
    {
        $list = glob('weapp/*/template/skin/js/jquery.js');
        if (!empty($list)) {
            $list[] = 'public/static/common/diyminipro/js/jquery.min.js';
            $minilist = glob('weapp/*/template/*/js/jquery.min.js');
            if (!empty($minilist)) {
                $list = array_merge($list, $minilist);
            }
            foreach ($list as $key => $val) {
                if (file_exists('./'.$val)) {
                    @copy(realpath('public/static/admin/js/jquery.js'), realpath($val));
                }
            }
        }
    }
    
    /*
    * 初始化原来的菜单栏目
    */
    public function initialize_admin_menu(){
        $total = Db::name("admin_menu")->count();
        if (empty($total)){
            $menuArr = getAllMenu();
            $insert_data = [];
            foreach ($menuArr as $key => $val){
                foreach ($val['child'] as $nk=>$nrr) {
                    $sort_order = 100;
                    $is_switch = 1;
                    if ($nrr['id'] == 2004){
                        $sort_order = 10000;
                        $is_switch = 0;
                    }
                    $insert_data[] = [
                        'menu_id' => $nrr['id'],
                        'title' => $nrr['name'],
                        'controller_name' => $nrr['controller'],
                        'action_name' => $nrr['action'],
                        'param' => !empty($nrr['param']) ? $nrr['param'] : '',
                        'is_menu' => $nrr['is_menu'],
                        'is_switch' => $is_switch,
                        'icon' =>  $nrr['icon'],
                        'sort_order' => $sort_order,
                        'add_time' => getTime(),
                        'update_time' => getTime()
                    ];
                }
            }
            Db::name("admin_menu")->insertAll($insert_data);
        }
    }

    // 升级v1.6.7版本要处理的数据
    private function eyou_v167_handle_data()
    {
        $Prefix = config('database.prefix');

        // 售后数据表加入原路退回功能需要的字段
        $serviceTableInfo = Db::query("SHOW COLUMNS FROM {$Prefix}shop_order_service");
        $serviceTableInfo = get_arr_column($serviceTableInfo, 'Field');
        if (!empty($serviceTableInfo) && !in_array('refund_way', $serviceTableInfo)) {
            $sql = "ALTER TABLE `{$Prefix}shop_order_service` ADD COLUMN `refund_way`  tinyint(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '退款方式(1:退款到余额; 2:线下退款; 3:原路退回(微信))' AFTER `refund_note`;";
            @Db::execute($sql);
        }
        schemaTable('shop_order_service');

        // 修复外贸助手的文案错误
        $syn_admin_logic_1719815413 = tpSetting('syn.syn_admin_logic_1719815413', [], 'cn');
        if (empty($syn_admin_logic_1719815413)) {
            try {
                Db::name('foreign_pack')->where(['name'=>'users7','value'=>'%已存在！'])->update(['value'=>'%s已存在！']);
                Db::name('foreign_pack')->where(['name'=>'users7','value'=>'% already exists!'])->update(['value'=>'%s already exists!']);
                tpSetting('syn', ['syn_admin_logic_1719815413' => 1], 'cn');
            } catch (\Exception $e) {
            }
        }

        // 修复消息推送的多余数据
        $syn_admin_logic_1721179406 = tpSetting('syn.syn_admin_logic_1721179406', [], 'cn');
        if (empty($syn_admin_logic_1721179406)) {
            try {
                $tpl_ids = [];
                $markList = Db::name('language')->field('mark')->getAllWithIndex('mark');
                $result = Db::name('wechat_template')->order('send_scene asc, lang asc, tpl_id asc')->select();
                $result = group_same_key($result, 'send_scene');
                foreach ($result as $key => $val) {
                    $mark_arr = $markList;
                    foreach ($val as $_k => $_v) {
                        if (isset($mark_arr[$_v['lang']])) {
                            $tpl_ids[] = $_v['tpl_id'];
                            unset($mark_arr[$_v['lang']]);
                        }
                        if (empty($mark_arr)) {
                            break;
                        }
                    }
                }
                if (!empty($tpl_ids)) {
                    Db::name('wechat_template')->where(['tpl_id'=>['NOTIN', $tpl_ids]])->delete();
                }
                Db::name('wechat_template')->where(['send_scene'=>1])->update(['tpl_title'=>'新表单']);
                Db::name('wechat_template')->where(['send_scene'=>9])->update(['tpl_title'=>'新订单']);
                
                tpSetting('syn', ['syn_admin_logic_1721179406' => 1], 'cn');
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * 同步模板的付费选择支付文件到前台模板指定位置
     * @return [type] [description]
     */
    public function copy_tplpayfile($channel = 0)
    {
        $shop_open_comment = getUsersConfigData('shop.shop_open_comment');
        $channelRow = Db::name('channeltype')->where(['status'=>1])->getAllWithIndex('id');
        foreach ($channelRow as $key => $val) {
            $data = json_decode($val['data'], true);
            $val['data'] = empty($data) ? [] : $data;
            $channelRow[$key] = $val;
        }
        $source_path = ROOT_PATH.'public/html/template/';
        $dest_path = ROOT_PATH.'template/'.THEME_STYLE_PATH.'/system/';
        if (stristr($dest_path, '/pc/system/')) {
            tp_mkdir($dest_path);
            if (!empty($channelRow[1]['data']['is_article_pay'])) {
                if (in_array($channel, [0,1]) && !file_exists($dest_path.'article_pay.htm') && file_exists($source_path.'pc/system/article_pay.htm')) {
                    @copy($source_path.'pc/system/article_pay.htm', $dest_path.'article_pay.htm');
                }
            }
            if (!empty($channelRow[4]['data']['is_download_pay'])) {
                if (in_array($channel, [0,4]) && !file_exists($dest_path.'download_pay.htm') && file_exists($source_path.'pc/system/download_pay.htm')) {
                    @copy($source_path.'pc/system/download_pay.htm', $dest_path.'download_pay.htm');
                }
            }
            if (!empty($shop_open_comment)) {
                if (in_array($channel, [0,2]) && !file_exists($dest_path.'product_comment.htm') && file_exists($source_path.'pc/system/product_comment.htm')) {
                    @copy($source_path.'pc/system/product_comment.htm', $dest_path.'product_comment.htm');
                }
            }
        }

        $dest_path = ROOT_PATH.'template/'.THEME_STYLE_PATH;
        $dest_path = preg_replace('/\/pc$/i', '/mobile', $dest_path);
        if (file_exists($dest_path)) {
            $dest_path .= '/system/';
            tp_mkdir($dest_path);
            if (!empty($channelRow[1]['data']['is_article_pay'])) {
                if (in_array($channel, [0,1]) && !file_exists($dest_path.'article_pay.htm') && file_exists($source_path.'mobile/system/article_pay.htm')) {
                    @copy($source_path.'mobile/system/article_pay.htm', $dest_path.'article_pay.htm');
                }
            }
            if (!empty($channelRow[4]['data']['is_download_pay'])) {
                if (in_array($channel, [0,4]) && !file_exists($dest_path.'download_pay.htm') && file_exists($source_path.'mobile/system/download_pay.htm')) {
                    @copy($source_path.'mobile/system/download_pay.htm', $dest_path.'download_pay.htm');
                }
            }
            if (!empty($shop_open_comment)) {
                if (in_array($channel, [0,2]) && !file_exists($dest_path.'product_comment.htm') && file_exists($source_path.'mobile/system/product_comment.htm')) {
                    @copy($source_path.'mobile/system/product_comment.htm', $dest_path.'product_comment.htm');
                }
                if (in_array($channel, [0,2]) && !file_exists($dest_path.'comment_list.htm') && file_exists($source_path.'mobile/system/comment_list.htm')) {
                    @copy($source_path.'mobile/system/comment_list.htm', $dest_path.'comment_list.htm');
                }
            }
        }
    }
}
