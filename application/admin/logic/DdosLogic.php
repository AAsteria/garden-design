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

use think\Db;
use think\Model;
use think\Cache;

// load_trait('controller/Jump');
class DdosLogic extends Model
{
    // use \traits\controller\Jump;
    public $admin_info = array();
    public $admin_id = 0;
    public $admin_lang = 'cn';
    public $times;
    public static $ddosData = null;
    const FEATURE_MSG_CODE_100 = 100; // 多余文件
    const FEATURE_MSG_CODE_101 = 101; // 多余文件
    const FEATURE_MSG_CODE_110 = 110; // 多余目录
    const FEATURE_MSG_CODE_600 = 600; // 疑似木马
    const FEATURE_MSG_CODE_601 = 601; // 疑似木马
    const FEATURE_MSG_CODE_990 = 990; // 高危漏洞
    const FEATURE_MSG_CODE_991 = 991; // 高危漏洞

    /**
     * 初始化操作
     */
    public function initialize() {
        parent::initialize();
        $this->admin_info = session('admin_info');
        $this->admin_id = empty($this->admin_info) ? 0 : $this->admin_info['admin_id'];
        $this->admin_lang = get_admin_lang();
        $this->times = getTime();
        if (null === self::$ddosData) {
            $ddosData = tpSetting('ddos', [], 'cn');
            self::$ddosData['ddos_feature_pattern'] = json_decode(base64_decode($ddosData['ddos_feature_pattern']), true);
            // self::$ddosData['ddos_feature_pattern_grade'] = json_decode(base64_decode($ddosData['ddos_feature_pattern_grade']), true);
            self::$ddosData['ddos_feature_imgpattern'] = json_decode(base64_decode($ddosData['ddos_feature_imgpattern']), true);
            self::$ddosData['ddos_feature_msg'] = json_decode(base64_decode($ddosData['ddos_feature_msg']), true);
            self::$ddosData['ddos_feature_msg_grade'] = json_decode(base64_decode($ddosData['ddos_feature_msg_grade']), true);
            self::$ddosData['ddos_feature_other'] = json_decode(base64_decode($ddosData['ddos_feature_other']), true);
            foreach ([10010,10030,10070,10090] as $key => $val) {
                self::$ddosData['ddos_feature_other'][$val]['value'] = explode(',', self::$ddosData['ddos_feature_other'][$val]['value']);
            }
        }
    }

    /**
     * 处理扫描文件
     * $achievepage 已扫描文件数
     * $batch       是否分批次执行，true：分批，false：不分批
     * limit        每次执行多少条数据
     */
    public function ddosHandelScanFile($doubtotal, $achievepage = 0, $batch = true, $limit = 100)
    {
        if (empty($achievepage)) {
            // 初始化第一批要处理扫描文件的逻辑
        }

        $msg                  = "";
        $result               = $this->getScanFileData($achievepage, $limit);
        $info                 = $result['info'];
        $data['allpagetotal'] = $pagetotal = $result['pagetotal'];
        $data['achievepage']  = $achievepage;
        $data['pagetotal']    = 0;
        $data['doubtotal']    = $doubtotal;

        if ($batch && $pagetotal > $achievepage) {
            $redata = $this->ddosInspectFile($info, $data['achievepage'], $data['doubtotal'], $data['allpagetotal']);
            if (!empty($redata['msg'])) {
                $msg .= $redata['msg'];
            }
            $data['doubtotal'] = $redata['doubtotal'];
            $data['doubthtml'] = $this->ddos_doubtdata('html');
            $data['achievepage'] += count($info);
        }

        return [$msg, $data];
    }

    /**
     * 获取要扫描文件的数据
     */
    private function getScanFileData($offset = 0, $limit = 0)
    {
        empty($limit) && $limit = 100;
        $info = [];
        $filelist = $this->ddos_setting('web.filelist');
        $list = json_decode($filelist, true);
        $result = array_slice($list, $offset, $limit, true);
        foreach ($result as $key=>$val){
            $info_value = [];
            $info_value['filepath'] = $val;
            $info[] = $info_value;
        }
        // 总文件数
        $pagetotal = (int)count($list);

        return ['info' => $info, 'pagetotal' => $pagetotal];
    }

    /**
     * 获取后台登录入口文件
     * @return [type] [description] 
     */
    public function getAdminbasefile($route = true)
    {
        $web_adminbasefile = tpCache('global.web_adminbasefile');
        $web_adminbasefile = !empty($web_adminbasefile) ? $web_adminbasefile : ROOT_DIR.'/login.php';
        if (stristr($web_adminbasefile, 'index.php')) {
            $web_adminbasefile = request()->baseFile();
        }
        if (false === $route) {
            $arr = explode('/', $web_adminbasefile);
            $web_adminbasefile = end($arr);
        }

        return $web_adminbasefile;
    }

    /*
     * 逐个检查扫描的文件
     */
    private function ddosInspectFile($result, $achievepage = 0, $doubtotal = 0, $allpagetotal = 0)
    {
        $return_data = [
            'msg' => "",
            'achievepage' => $achievepage,
            'doubtotal' => $doubtotal,
            'doubtlist' => [],
        ];
        $auth_code = tpCache('system.system_auth_code');
        if (!empty($result)) {
            $html_dir_list = $this->ddos_html_dir_list();
            $dir_pattern = implode('|', $html_dir_list);
        }
        // 后台入口文件
        $web_adminbasefile = $this->getAdminbasefile(false);
        // 官方对应版本的文件列表
        $eyoufilelist = json_decode($this->ddos_setting('sys.eyoufilelist'), true);
        empty($eyoufilelist) && $eyoufilelist = [];

        $insertData = [];
        foreach ($result as $key => $val) {
            $filepath = $val['filepath'];
            $return_data['achievepage'] += 1;
            $md5key = md5('files'.$filepath.$auth_code);
            $file_excess = 0; // 是否多余文件/目录
            $file_grade = 0; // 异常类型
            $is_eyoufile = true;
            $suspicious_html = "";
            if (is_dir($filepath)) {
                if (preg_match('/^install(.*)$/i', $filepath)) {
                    $file_grade = self::FEATURE_MSG_CODE_110;
                }
            }
            else {
                $filetype = strtolower(preg_replace("/^(.*)\.([a-z]+)$/i", '${2}', $filepath));

                if (0 == $file_grade) {
                    // 在官方对应版本中，是否存在该文件
                    if (!empty($eyoufilelist)) {
                        $filepath_tmp = $filepath;
                        if (preg_match('/^install(.*)$/i', $filepath_tmp)) {
                            $filepath_tmp = preg_replace('/^(install)([^\/]*)\/(.*)$/i', '${1}/${3}', $filepath_tmp);
                        }
                        if (!empty($filepath_tmp) && !in_array($filepath_tmp, $eyoufilelist)) {
                            if (!preg_match(self::$ddosData['ddos_feature_other'][10033]['value'], $filepath_tmp)) { // 排除在特定目录/文件
                                if (!preg_match(self::$ddosData['ddos_feature_other'][10031]['value'], $filepath_tmp) || preg_match(self::$ddosData['ddos_feature_other'][10032]['value'], $filepath_tmp)) {
                                    $file_grade = self::FEATURE_MSG_CODE_100;
                                    $file_excess = 1;
                                    $is_eyoufile = false;
                                }
                            }
                        }
                    }

                    // 如果不是易优本身文件，在特定的目录中，不应该存在的类型文件
                    if (false === $is_eyoufile) {
                        if (0 == $file_grade) {
                            if ('html' != $filetype) {
                                // 生成静态目录里存在html以外的其他文件
                                if (!empty($dir_pattern) && preg_match('/^('.$dir_pattern.')\//i', $filepath)) {
                                    $file_grade = self::FEATURE_MSG_CODE_100;
                                    $file_excess = 1;
                                    // $suspicious_html = self::$ddosData['ddos_feature_msg'][$file_grade]['value'];
                                }
                            }
                        }

                        if (0 == $file_grade) {
                            // 指定扩展名的多余文件
                            if (!in_array($filepath, self::$ddosData['ddos_feature_other'][10030]['value'])) {
                                // 图片文件，在指定目录如果存在，肯定是多余图片，有可疑行为。
                                if (0 == $file_grade && in_array($filetype, self::$ddosData['ddos_feature_other'][10070]['value'])) {
                                    if (1 == count(explode('/', $filepath))) {
                                        if (!in_array($filepath, ['favicon.ico'])) {
                                            $file_grade = self::FEATURE_MSG_CODE_100;
                                        }
                                    } else if (preg_match(self::$ddosData['ddos_feature_other'][10071]['value'], $filepath) && !preg_match(self::$ddosData['ddos_feature_other'][10072]['value'], $filepath)) {
                                        $file_grade = self::FEATURE_MSG_CODE_100;
                                    }
                                }
                                // 压缩包文件，在指定目录如果存在，肯定是多余文件，有可疑行为。
                                if (0 == $file_grade && in_array($filetype, self::$ddosData['ddos_feature_other'][10090]['value'])) {
                                    if (preg_match(self::$ddosData['ddos_feature_other'][10091]['value'], $filepath) && !preg_match(self::$ddosData['ddos_feature_other'][10092]['value'], $filepath)) {
                                        $file_grade = self::FEATURE_MSG_CODE_100;
                                    }
                                }
                                // 其他，比如：根目录只能存在特定的php文件、特定目录不能存在php等动态语言文件
                                if (0 == $file_grade && in_array($filetype, self::$ddosData['ddos_feature_other'][10010]['value'])) {
                                    if (1 == count(explode('/', $filepath))) {
                                        if (!in_array($filepath, ['index.php',$web_adminbasefile])) {
                                            $file_grade = self::FEATURE_MSG_CODE_100;
                                        }
                                    } else if (preg_match(self::$ddosData['ddos_feature_other'][10011]['value'], $filepath) && !preg_match(self::$ddosData['ddos_feature_other'][10012]['value'], $filepath)) {
                                        $file_grade = self::FEATURE_MSG_CODE_100;
                                    }
                                }

                                if (!empty($file_grade)) {
                                    $file_excess = 1;
                                    // $suspicious_html = self::$ddosData['ddos_feature_msg'][$file_grade]['value'];
                                }
                            }
                        }
                    }
                }

                // 检查文件是否含有病毒特征，排除压缩包
                if (in_array($filetype, self::$ddosData['ddos_feature_other'][10070]['value'])) { // 图片
                    $redata = $this->ddos_checkImgFeatures($filepath, $filetype);
                    if (!empty($redata['bool'])) {
                        $file_grade = empty($redata['file_grade']) ? self::FEATURE_MSG_CODE_600 : $redata['file_grade'];
                        // $suspicious_html = "";
                        // $suspicious_html .= empty($redata['msg']) ? self::$ddosData['ddos_feature_msg'][$file_grade]['value'] : $redata['msg'];
                    }
                }
                else if (!in_array($filetype, self::$ddosData['ddos_feature_other'][10090]['value'])) { // 文件
                    $fd = realpath($filepath);
                    $fp = fopen($fd, "r");
                    $i = 0;
                    $suspicious_html = "";
                    while ($buffer = fgets($fp, 4096)) {
                        $i++;
                        $redata = $this->ddos_checkCodeFeatures($i, $buffer, $filetype);
                        if (!empty($redata['bool'])) {
                            $file_grade = empty($redata['file_grade']) ? self::FEATURE_MSG_CODE_600 : $redata['file_grade'];
                            // $suspicious_html .= empty($redata['msg']) ? self::$ddosData['ddos_feature_msg'][$file_grade]['value'] : $redata['msg'];
                            $suspicious_html .= htmlspecialchars($this->ddos_cut_str($buffer,120,0));
                            break;
                        }
                    }
                    fclose($fp);
                }
            }

            if (!empty($file_grade)) {
                $return_data['doubtotal']++;
                $return_data['doubtlist'][] = $filepath;
                $insertData[] = [
                    'md5key'    => $md5key,
                    'file_name'   => $filepath,
                    'file_num'    => $return_data['achievepage'],
                    'file_total'  => $allpagetotal,
                    'file_doubt_total'    => $return_data['doubtotal'],
                    'file_excess' => $file_excess,
                    'file_grade' => $file_grade,
                    'html'        => empty($suspicious_html) ? '' : htmlspecialchars($suspicious_html),
                    'admin_id' => $this->admin_id,
                    'add_time'      => getTime(),
                    'update_time'      => getTime(),
                ];
            }
        }

        if (!empty($insertData)) {
            try {
                Db::name('ddos_log')->insertAll($insertData);
            } catch (\Exception $e) {
                $return_data['msg'] .= '<span>' . '扫描失败：' . $e->getMessage() . '</span><br>';
            }
        }

        if ($return_data['achievepage'] >= $allpagetotal) {
            $log_id = Db::name('ddos_log')->where(['admin_id'=>$this->admin_id])->max('id');
            if (!empty($log_id)) {
                Db::name('ddos_log')->where(['id'=>$log_id])->update(['file_num'=>$return_data['achievepage']]);
            }
        }

        return $return_data;
    }

    /**
     * 处理扫描附件
     * $achievepage 已扫描文件数
     * $batch       是否分批次执行，true：分批，false：不分批
     * limit        每次执行多少条数据
     */
    public function ddosHandelScanAttachment($doubtotal, $achievepage = 0, $achievefile = 0, $allscantotal = 0, $batch = true, $limit = 50)
    {
        if (empty($achievepage)) {
            // 初始化第一批要处理扫描附件的逻辑
        }

        $msg                  = "";
        $result               = $this->getScanAttachmentData($achievepage, $limit);
        $info                 = $result['info'];
        $data['allpagetotal'] = $pagetotal = $result['pagetotal'];
        $data['achievepage']  = $achievepage;
        $data['achievefile']  = $achievefile;
        $data['doubtotal']    = $doubtotal;
        $data['allscantotal']    = $allscantotal;

        if ($batch && $pagetotal > $achievepage) {
            $redata = $this->ddosInspectAttachment($info, $data['achievepage'], $data['achievefile'], $data['allscantotal'], $data['doubtotal'], $data['allpagetotal']);
            if (!empty($redata['msg'])) {
                $msg .= $redata['msg'];
            }
            $data['doubtotal'] = $redata['doubtotal'];
            $data['allscantotal'] = $redata['allscantotal'];
            $data['doubthtml'] = $this->ddos_doubtdata('html');
            $data['achievefile'] = $redata['achievefile'];
            $data['achievepage'] += count($info);
        }

        return [$msg, $data];
    }

    /**
     * 获取要扫描目录的数据
     */
    private function getScanAttachmentData($offset = 0, $limit = 0)
    {
        empty($limit) && $limit = 50;
        $info = [];
        $uploads_dirlist = $this->ddos_setting('web.uploads_dirlist');
        $dirlist = json_decode($uploads_dirlist, true);
        $result = array_slice($dirlist, $offset, $limit, true);
        $ext = self::$ddosData['ddos_feature_other'][10080]['value'];
        foreach ($result as $key=>$val){
            $files = glob("{$val}/*.{".$ext."}", GLOB_BRACE);
            $info_value = [];
            $info_value['dir'] = $val;
            $info_value['files'] = $files;
            $info[] = $info_value;
        }
        // 总附件目录数
        $pagetotal = (int)count($dirlist);

        return ['info' => $info, 'pagetotal' => $pagetotal];
    }

    /*
     * 逐个检查扫描的附件
     */
    private function ddosInspectAttachment($result, $achievepage = 0, $achievefile = 0, $allscantotal = 0, $doubtotal = 0, $allpagetotal = 0)
    {
        $return_data = [
            'msg' => "",
            'achievefile' => $achievefile,
            'achievepage' => $achievepage,
            'doubtotal' => $doubtotal,
            'allscantotal' => $allscantotal,
            'doubtlist' => [],
        ];
        $auth_code = tpCache('system.system_auth_code');
        foreach ($result as $key => $val) {
            $return_data['achievepage'] += 1;
            $insertData = [];
            foreach ($val['files'] as $_k => $_v) {
                $filepath = $_v;
                $filetype = strtolower(preg_replace("/^(.*)\.([a-z]+)$/i", '${2}', $filepath));
                if ('html' == $filetype) {
                    $content_tmp = @file_get_contents($filepath);
                    if (false !== $content_tmp && (empty($content_tmp) || 'dir' == $content_tmp)) {
                        continue;
                    }
                }
                $return_data['achievefile'] += 1;
                $md5key = md5('attachment'.$filepath.$auth_code);
                $file_excess = 1; // 多余文件
                $file_grade = self::FEATURE_MSG_CODE_100; // 异常类型
                $suspicious_html = "";

                // 检查文件是否含有病毒特征，排除压缩包
                if (!in_array($filetype, self::$ddosData['ddos_feature_other'][10090]['value'])) { // 文件
                    $fd = realpath($filepath);
                    $fp = fopen($fd, "r");
                    $i = 0;
                    $suspicious_html = "";
                    while ($buffer = fgets($fp, 4096)) {
                        $i++;
                        $redata = $this->ddos_checkCodeFeatures($i, $buffer, $filetype);
                        if (!empty($redata['bool'])) {
                            $file_grade = empty($redata['file_grade']) ? self::FEATURE_MSG_CODE_600 : $redata['file_grade'];
                            // $suspicious_html .= empty($redata['msg']) ? self::$ddosData['ddos_feature_msg'][$file_grade]['value'] : $redata['msg'];
                            $suspicious_html .= htmlspecialchars($this->ddos_cut_str($buffer,120,0));
                            break;
                        }
                    }
                    fclose($fp);
                }

                if (!empty($file_grade)) {
                    $return_data['doubtotal']++;
                    $return_data['doubtlist'][] = $filepath;
                    $insertData[] = [
                        'md5key'    => $md5key,
                        'file_name'   => $filepath,
                        'file_num'    => $return_data['achievefile'] + $return_data['achievepage'],
                        'file_total'  => $allpagetotal,
                        'file_doubt_total'    => $return_data['doubtotal'],
                        'file_excess' => $file_excess,
                        'file_grade' => $file_grade,
                        'html'        => empty($suspicious_html) ? '' : htmlspecialchars($suspicious_html),
                        'admin_id' => $this->admin_id,
                        'add_time'      => getTime(),
                        'update_time'      => getTime(),
                    ];
                }
            }

            if (!empty($insertData)) {
                try {
                    Db::name('ddos_log')->insertAll($insertData);
                } catch (\Exception $e) {
                    $return_data['msg'] .= '<span>' . '扫描失败：' . $e->getMessage() . '</span><br>';
                }
            }

            $return_data['allscantotal'] = $allscantotal + $return_data['achievefile'] + $return_data['achievepage'];
            if ($return_data['achievepage'] >= $allpagetotal) {
                $log_id = Db::name('ddos_log')->where(['admin_id'=>$this->admin_id])->max('id');
                if (!empty($log_id)) {
                    Db::name('ddos_log')->where(['id'=>$log_id])->update([
                        'file_num' => $return_data['allscantotal'],
                        'file_total' => $return_data['allscantotal'],
                    ]);
                }
            }
        }

        return $return_data;
    }

    /**
     * 是否在特征库里的高危文件
     * @param  string $buffer [description]
     * @return [type]         [description]
     */
    private function ddos_checkCodeFeatures($i = 0, $buffer = '', $filetype = '')
    {
        $bool = false;
        $msg = '';
        $file_grade = 0;
        if (!empty($buffer)) {
            if (!empty(self::$ddosData['ddos_feature_pattern'])) {
                $filetype = strtolower($filetype);
                foreach (self::$ddosData['ddos_feature_pattern'] as $key => $patterns) {
                    if ('js' == $filetype) {
                        if (preg_match(self::$ddosData['ddos_feature_other'][10041]['value'], $buffer) || $i > 5) {
                            continue;
                        }
                    } else {
                        if (990001 <= $key && $key <= 990010) {
                            continue;
                        }
                    }

                    if (!empty($patterns['value']) && preg_match($patterns['value'], $buffer)) {
                        $bool = true;
                        $file_grade = preg_replace('/^(\d{3,3})(.*)$/i', '${1}', $key);
                        if ('js' == $filetype) {
                            if (990001 <= $key && $key <= 990010) {
                                $msg = empty(self::$ddosData['ddos_feature_msg'][$key]['value']) ? self::$ddosData['ddos_feature_msg'][$file_grade]['value'] : self::$ddosData['ddos_feature_msg'][$key]['value'];
                            }
                        } else {
                            $msg = empty(self::$ddosData['ddos_feature_msg'][$key]['value']) ? self::$ddosData['ddos_feature_msg'][$file_grade]['value'] : self::$ddosData['ddos_feature_msg'][$key]['value'];
                        }
                        break;
                    }
                }
            }
        }

        return [
            'bool' => $bool,
            'msg'  => $msg,
            'file_grade' => $file_grade,
        ];
    }

    /**
     * 是否在特征库里的高危图片
     * @param  string $buffer [description]
     * @return [type]         [description]
     */
    private function ddos_checkImgFeatures($filepath = '', $filetype = '')
    {
        $bool = false;
        $msg = '';
        $file_grade = 0;
        if (!empty(self::$ddosData['ddos_feature_imgpattern']) && file_exists($filepath)) {
            $filetype = strtolower($filetype);
            $fd = realpath($filepath);
            $fp      = fopen($fd, 'r');
            $fsize = filesize($fd);
            if (false === $fsize) {
                $buffer = 'ZmlsZXNpemXov5Tlm57mlofku7blpKflsI/lrZfoioLmlbDkuLpmYWxzZQ==';
                $buffer = base64_decode($buffer);
            } else {
                if (0 == $fsize) {
                    $buffer = '';
                } else {
                    $buffer = fread($fp, $fsize);
                }
            }
            fclose($fp);
            if (!empty($buffer)) {
                foreach (self::$ddosData['ddos_feature_imgpattern'] as $key => $patterns) {
                    if (!empty($patterns['value']) && preg_match($patterns['value'], $buffer)) {
                        $bool = true;
                        $file_grade = preg_replace('/^(\d{3,3})(.*)$/i', '${1}', $key);
                        $msg = empty(self::$ddosData['ddos_feature_msg'][$key]['value']) ? self::$ddosData['ddos_feature_msg'][$file_grade]['value'] : self::$ddosData['ddos_feature_msg'][$key]['value'];
                        break;
                    }
                }
            }
        }

        return [
            'bool' => $bool,
            'msg'  => $msg,
            'file_grade' => $file_grade,
        ];
    }

    private function ddos_cut_str($string, $sublen, $start = 0, $code = 'UTF-8') {
        if ($code == 'UTF-8') {
            $pa = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|\xe0[\xa0-\xbf][\x80-\xbf]|[\xe1-\xef][\x80-\xbf][\x80-\xbf]|\xf0[\x90-\xbf][\x80-\xbf][\x80-\xbf]|[\xf1-\xf7][\x80-\xbf][\x80-\xbf][\x80-\xbf]/";
            preg_match_all($pa, $string, $t_string);
            if (count($t_string[0]) - $start > $sublen) {
                return join('', array_slice($t_string[0], $start, $sublen)) . "...";
            }
            return join('', array_slice($t_string[0], $start, $sublen));
        } else {
            $start = $start * 2;
            $sublen = $sublen * 2;
            $strlen = strlen($string);
            $tmpstr = '';
            for($i = 0; $i < $strlen; $i++) {
                if ($i >= $start && $i < ($start + $sublen)) {
                    if (ord(substr($string, $i, 1)) > 129) {
                        $tmpstr .= substr($string, $i, 2);
                    } else {
                        $tmpstr .= substr($string, $i, 1);
                    } 
                } 
                if (ord(substr($string, $i, 1)) > 129) {
                    $i++;
                }
            } 
            if (strlen($tmpstr) < $strlen) {
                $tmpstr .= "...";
            }

            return $tmpstr;
        } 
    }

    /**
     * 静态模式下的存放html目录集合
     * @return [type] [description]
     */
    private function ddos_html_dir_list()
    {
        $html_dir_list = [];
        $html_arcdir = tpCache("seo.seo_html_arcdir"); // 检测页面保存目录
        if (!empty($html_arcdir)) {
            $html_dir_list[] = $html_arcdir;
        }
        $arctype_list = Db::name('arctype')->field('dirpath,diy_dirpath')->select();
        if (!empty($arctype_list)) {
            foreach ($arctype_list as $key => $val) {
                $dirpath = trim($val['dirpath'], '/');
                $dirpathArr = explode('/', $dirpath);
                $dirpath_tmp = current($dirpathArr);
                if (!empty($dirpath_tmp) && !in_array($dirpath_tmp, $html_dir_list)) {
                    $html_dir_list[] = $dirpath_tmp;
                }

                $diy_dirpath = trim($val['diy_dirpath'], '/');
                $diy_dirpathArr = explode('/', $diy_dirpath);
                $diy_dirpath_tmp = current($diy_dirpathArr);
                if (!empty($diy_dirpath_tmp) && !in_array($diy_dirpath_tmp, $html_dir_list)) {
                    $html_dir_list[] = $diy_dirpath_tmp;
                }
            }
        }

        return $html_dir_list;
    }

    public function ddos_setting($setting_key, $value = null)
    {
        $param = explode('.', $setting_key);
        $inc_type = $param[0];
        $name = $param[0].'_'.$param[1];
        $where = ['name'=>$name, 'inc_type'=>$inc_type, 'admin_id'=>$this->admin_id];
        $cacheKey = md5("admin-DdosLogic-ddos_setting-".json_encode($where));
        if (null === $value) {
            $value = cache($cacheKey);
            if (empty($value)) {
                $value = Db::name('ddos_setting')->where($where)->value('value');
                cache($cacheKey, $value, null, 'ddos_setting');
            }
            return $value;

        } else {
            $id = (int)Db::name('ddos_setting')->where($where)->value('id');
            if (!empty($id)) {
                $r = Db::name('ddos_setting')->where(['id'=>$id])->update([
                        'value'=>$value,
                        'update_time'=>getTime(),
                    ]);
            } else {
                $r = Db::name('ddos_setting')->where($where)->insert([
                        'name' => $name,
                        'value' => $value,
                        'inc_type' => $inc_type,
                        'admin_id' => $this->admin_id,
                        'add_time'=>getTime(),
                        'update_time'=>getTime(),
                    ]);
            }
            if ($r !== false) {
                cache($cacheKey, $value, null, 'ddos_setting');
                return $value;
            }
        }

        return false;
    }

    /**
     * 获取对应版本的易优cms源码文件列表
     * @return [type] [description]
     */
    public function ddos_eyou_source_files()
    {
        $version = getVersion();
        $url = 'ht'.'tp'.':/'.'/'.'up'.'da'.'te'.'.e'.'yo'.'u.5'.'f'.'a.'.'c'.'n/other/repair/'.$version.'/filelist.txt';
        $response = @httpRequest2($url, 'GET', [], [], 3);
        if (empty($response)) {
            $context = stream_context_set_default(array('http' => array('timeout' => 3,'method'=>'GET')));
            $response = @file_get_contents($url, false, $context);
        }
        if (!empty($response)) {
            $path = DATA_PATH.'conf/eyoufilelist.txt';
            if (!file_exists($path) || is_writeable($path)) {
                try {
                    $fp = fopen($path, "w+");
                    if (!empty($fp) && fwrite($fp, $response)) {
                        fclose($fp);
                    }
                } catch (\Exception $e) {}
            }
        } else {
            $response = getVersion('eyoufilelist', '');
        }

        if (empty($response)) {
            $this->error('文件 data/conf/eyoufilelist.txt 没有读写权限', null, '', 20);
        }
        $response = preg_replace("#[\r\n]{1,}#", "\n", $response);
        $filelist = explode("\n", $response);

        // 追加后台入口文件
        $filelist[] = $this->getAdminbasefile(false);
        // 追加自定义模型文件
        $channeltypefiles = [];
        if (is_dir(ROOT_PATH.'data/model/application')) {
            $this->ddos_getDirFile('data/model/application', 'application', $channeltypefiles);
        }
        $row = Db::name('channeltype')->where(['ifsystem'=>0])->order('id asc')->select();
        foreach ($row as $key => $val) {
            foreach ($channeltypefiles as $_k => $_v) {
                $_v = str_replace('CustomModel', $val['ctl_name'], $_v);
                $_v = str_replace('custommodel', $val['nid'], $_v);
                $filelist[] = $_v;
            }
        }
        // 追加多语言的语言包文件
        $row = Db::name('language')->order('id asc')->select();
        foreach ($row as $key => $val) {
            $filelist[] = "application/lang/{$val['mark']}.php";
        }

        $this->ddos_setting('sys.eyoufilelist', json_encode($filelist));

        return $filelist;
    }

    /**
     * 扫描后，追加可疑文件的页面html
     * @param  [type] $num_ky [description]
     * @param  [type] $fd     [description]
     * @param  [type] $rows_text      [description]
     * @param  [type] $buffer [description]
     * @param  [type] $md5key [description]
     * @return [type]         [description]
     */
    public function ddos_doubtdata($arr_attr = null)
    {
        $html = "";
        $file_total = $file_doubt_total = 0;
        $redata = [];
        $list = Db::name('ddos_log')->where(['admin_id' => $this->admin_id, 'file_grade'=>['gt',0]])->order('file_grade desc, file_excess asc')->select();
        if (!empty($list)) {
            $is_trojan_horse = 0; // 是否有挂马文件
            $down_url = url('Security/ddos_download_file');
            foreach ($list as $key => $val) {
                if ($file_total < $val['file_total']) {
                    $file_total = $val['file_total'];
                }
                if ($file_doubt_total < $val['file_doubt_total']) {
                    $file_doubt_total = $val['file_doubt_total'];
                }
                $file_grade = intval($val['file_grade']);
                $file_grade = $file_grade + intval($val['file_excess']);
                $val['html'] = htmlspecialchars_decode($val['html']);
                $val['html'] = self::$ddosData['ddos_feature_msg'][$file_grade]['value'] . $val['html'];
                $file_all_name = !empty($val['file_name']) ? trim($val['file_name'], '/') : '';
                $file_alias_name = preg_replace('/^(.*)(\/|\\\)([^\/\\\]+)$/i', '${3}', $file_all_name);
                // $filetype = strtolower(preg_replace("/^(.*)\.([a-z]+)$/i", '${2}', $file_all_name)); // pathinfo($file_all_name, PATHINFO_EXTENSION);
                $grade_value = self::$ddosData['ddos_feature_msg_grade'][$file_grade]['value'];
                $operation_html = "";
                $opt_arr = self::$ddosData['ddos_feature_msg'][$file_grade]['opt'];
                if ('see' == $opt_arr['event']) {
                    $operation_html = "<a href='{$opt_arr['value']}' target='_blank'>查看</a>";
                } else if ('replace' == $opt_arr['event']) {
                    $operation_html = "<a href='javascript:void(0);' data-md5key='{$val['md5key']}' onclick='replacefile(this);'>{$opt_arr['value']}</a>";
                } else if ('del' == $opt_arr['event']) {
                    $operation_html = "<a href='javascript:void(0);' data-md5key='{$val['md5key']}' onclick='delfile(this);'>{$opt_arr['value']}</a>";
                }

                switch ($val['file_grade']) {
                    case self::FEATURE_MSG_CODE_990:
                    case self::FEATURE_MSG_CODE_991:
                        $html .=<<<EOF
                            <li class="li_problem">
                                <span class="label red">{$grade_value}</span>
                                <div class="name"><a href="{$down_url}&md5key={$val['md5key']}" target="_blank" title="{$file_all_name}">{$file_alias_name}</a><em>|</em>{$val['html']}</div>
                                <div class="operation">
                                    {$operation_html}
                                </div>
                            </li>
EOF;
                        break;

                    case self::FEATURE_MSG_CODE_600:
                    case self::FEATURE_MSG_CODE_601:
                        $is_trojan_horse = 1;
                        $html .=<<<EOF
                            <li class="li_problem">
                                <span class="label orange">{$grade_value}</span>
                                <div class="name"><a href="{$down_url}&md5key={$val['md5key']}" target="_blank" title="{$file_all_name}">{$file_alias_name}</a><em>|</em>{$val['html']}</div>
                                <div class="operation">
                                    {$operation_html}
                                </div>
                            </li>
EOF;
                        break;

                    case self::FEATURE_MSG_CODE_100:
                    case self::FEATURE_MSG_CODE_101:
                    case self::FEATURE_MSG_CODE_110:
                        $html .=<<<EOF
                            <li class="li_problem">
                                <span class="label">{$grade_value}</span>
                                <div class="name"><a href="{$down_url}&md5key={$val['md5key']}" target="_blank" title="{$file_all_name}">{$file_alias_name}</a><em>|</em>{$val['html']}</div>
                                <div class="operation">
                                    {$operation_html}
                                </div>
                            </li>
EOF;
                        break;
                    
                    default:
                        # code...
                        break;
                }
            }

            if (1 == $is_trojan_horse) {
                $msg = self::$ddosData['ddos_feature_msg'][1]['value'];
                $html .=<<<EOF
                    <li>
                        <div>
                            {$msg}
                        </div>
                    </li>
EOF;
            }
        }

        $redata['html'] = $html;
        $redata['file_total'] = $file_total;
        $redata['file_doubt_total'] = $file_doubt_total;

        if (null === $arr_attr) {
            return $redata;
        } else {
            return empty($redata[$arr_attr]) ? '' : $redata[$arr_attr];
        }
    }

    /**
     * ddos_log表清空、重置ID、修复表
     * @return [type] [description]
     */
    public function ddos_log_reset()
    {
        $Prefix = config('database.prefix');
        Db::name('ddos_log')->where(['admin_id'=>$this->admin_id])->delete(true);
        @Db::execute("ALTER TABLE `{$Prefix}ddos_log` AUTO_INCREMENT 1");
        @Db::query("REPAIR TABLE `{$Prefix}ddos_log`");

        Db::name('ddos_setting')->where([
                'admin_id'=>$this->admin_id,
                'inc_type'=>['NOTIN', ['sys']],
            ])->delete(true);
        @Db::execute("ALTER TABLE `{$Prefix}ddos_setting` AUTO_INCREMENT 1");
        @Db::query("REPAIR TABLE `{$Prefix}ddos_setting`");
        Cache::clear('ddos_setting');
    }

    /**
     * 递归读取文件夹文件
     */
    public function ddos_getDirFile($directory, $dir_name = '', &$arr_file = array(), $ignore_dirs = [])
    {
        if (!file_exists($directory)) {
            return false;
        }
        $self = '';//'DdosLogic.php';
        $mydir = dir($directory);
        while ($file = $mydir->read()) {
            if (!in_array($file, ['.','..']) && is_dir("$directory/$file")) {
                if ($dir_name) {
                    $dir_name_tmp = "$dir_name/$file";
                } else {
                    $dir_name_tmp = $file;
                }
                if (!in_array($dir_name_tmp, $ignore_dirs)) {
                    $this->ddos_getDirFile("$directory/$file", $dir_name_tmp, $arr_file, $ignore_dirs);
                }
            } else {
                if($file != $self){
                    if (!in_array($file, ['.','..']) && preg_match(self::$ddosData['ddos_feature_other'][10050]['value'], $file)) {
                        if ($dir_name) {
                            $file_tmp = "$dir_name/$file";
                        } else {
                            $file_tmp = "$file";
                        }

                        if ($this->ddos_is_gb2312($file_tmp) && function_exists('mb_convert_encoding')){
                            $file_tmp = mb_convert_encoding($file_tmp,'UTF-8','GBK');
                        }

                        $arr_file[] = $file_tmp;
                    } 
                }
            } 
        }
        $mydir->close();

        return $arr_file;
    }

    private function ddos_is_gb2312($str)
    {
        for($i=0; $i<strlen($str); $i++) {
            $v = ord( $str[$i] );
            if( $v > 127) {
                if( ($v >= 228) && ($v <= 233) )
                {
                    if( ($i+2) >= (strlen($str) - 1)) return true; // not enough characters
                    $v1 = ord( $str[$i+1] );
                    $v2 = ord( $str[$i+2] );
                    if( ($v1 >= 128) && ($v1 <=191) && ($v2 >=128) && ($v2 <= 191) ) // utf编码
                        return false;
                    else
                        return true;
                }
            }
        }
        return true;
    }

    public function get_dir_list($dirs = [], &$list = [])
    {
        foreach ($dirs as $key => $val) {
            $list[] = $val;
            if (is_dir(ROOT_PATH.$val)) {
                $this->ddos_getDir(ROOT_PATH.$val, $val, $list);
            }
        }

        return $list;
    }

    /**
     * 递归读取文件夹，返回文件夹列表
     */
    public function ddos_getDir($directory, $dir_name = '', &$arr_dir = array(), $ignore_dirs = [])
    {
        if (!file_exists($directory)) {
            return false;
        }
        $ignore_dirs_pattern = implode('|', $ignore_dirs);
        $ignore_dirs_pattern = str_replace('/', '\/', $ignore_dirs_pattern);

        $mydir = dir($directory);
        while ($file = $mydir->read()) {
            if (!in_array($file, ['.','..']) && is_dir("$directory/$file")) {
                if ($dir_name) {
                    $dir_name_tmp = "$dir_name/$file";
                } else {
                    $dir_name_tmp = $file;
                }
                if ($this->ddos_is_gb2312($dir_name_tmp) && function_exists('mb_convert_encoding')){
                    $dir_name_tmp = mb_convert_encoding($dir_name_tmp,'UTF-8','GBK');
                }
                if (!in_array($dir_name_tmp, $ignore_dirs)) {
                    $is_recursion = false;
                    if (!empty($ignore_dirs_pattern)) {
                        if (!preg_match('/(\/)?('.$ignore_dirs_pattern.')(\/)?/i', $dir_name_tmp)) {
                            $is_recursion = true;
                        }
                    } else {
                        $is_recursion = true;
                    }

                    if ($is_recursion === true) {
                        $arr_dir[] = $dir_name_tmp;
                        $this->ddos_getDir("$directory/$file", $dir_name_tmp, $arr_dir, $ignore_dirs);
                    }
                }
            } 
        }
        $mydir->close();

        return $arr_dir;
    }
}
