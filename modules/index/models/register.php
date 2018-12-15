<?php
/**
 * @filesource modules/index/models/register.php
 *
 * @copyright 2016 Goragod.com
 * @license http://www.kotchasan.com/license/
 *
 * @see http://www.kotchasan.com/
 */

namespace Index\Register;

use Gcms\Login;
use Kotchasan\Http\Request;
use Kotchasan\Language;

/**
 * module=register.
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * บันทึกข้อมูล (register.php).
     *
     * @param Request $request
     */
    public function submit(Request $request)
    {
        $ret = array();
        // session, token
        if ($request->initSession() && $request->isSafe()) {
            // แอดมิน
            $isAdmin = Login::isAdmin();
            // โหมดตัวอย่างไม่สามารถ register ด้วยอีเมล์ได้ หรือ แอดมิน
            if (self::$cfg->demo_mode == false || $isAdmin) {
                // รับค่าจากการ POST
                $save = array(
                    'username' => $request->post('register_username')->username(),
                    'name' => $request->post('register_name')->topic(),
                    'status' => $isAdmin ? $request->post('register_status')->toInt() : 0,
                    'active' => 1,
                );
                $permission = $request->post('register_permission', array())->topic();
                if (empty($save['username'])) {
                    $ret['ret_register_username'] = 'Please fill in';
                } else {
                    // ตรวจสอบ username ซ้ำ
                    $search = $this->db()->first($this->getTableName('user'), array('username', $save['username']));
                    if ($search) {
                        $ret['ret_register_username'] = Language::replace('This :name already exist', array(':name' => Language::get('Email')));
                    }
                }
                // name
                if (empty($save['name'])) {
                    $ret['ret_register_name'] = 'Please fill in';
                }
                // password
                $password = $request->post('register_password')->password();
                $repassword = $request->post('register_repassword')->password();
                if (mb_strlen($password) < 4) {
                    // รหัสผ่านต้องไม่น้อยกว่า 4 ตัวอักษร
                    $ret['ret_register_password'] = 'this';
                } elseif ($repassword != $password) {
                    // กรอกรหัสผ่านสองช่องให้ตรงกัน
                    $ret['ret_register_repassword'] = 'this';
                } else {
                    $save['password'] = $password;
                }
                if (empty($ret)) {
                    // ลงทะเบียนสมาชิกใหม่
                    self::execute($this, $save, $permission);
                    if ($isAdmin) {
                        // คืนค่า
                        $ret['alert'] = Language::get('Saved successfully');
                        // ไปหน้าสมาชิก
                        $ret['location'] = 'index.php?module=member';
                    } else {
                        // ส่งอีเมล
                        $subject = '['.self::$cfg->web_title.'] '.Language::get('Welcome new members');
                        $msg = "{LNG_Your registration information}<br>\n<br>\n";
                        $msg .= '{LNG_Username} : '.$save['username']."<br>\n";
                        $msg .= '{LNG_Password} : '.$password."<br>\n";
                        $msg .= '{LNG_Name} : '.$save['name'];
                        $msg = Language::trans($msg);
                        $err = \Kotchasan\Email::send($save['username'], self::$cfg->noreply_email, $subject, $msg);
                        if ($err->error()) {
                            // คืนค่า error
                            $ret['alert'] = $err->getErrorMessage();
                        } else {
                            // คืนค่า
                            $ret['alert'] = Language::replace('Register successfully, We have sent complete registration information to :email', array(':email' => $save['username']));
                        }
                        // ไปหน้าเข้าระบบ
                        $ret['location'] = 'index.php?action=login';
                    }
                    // เคลียร์
                    $request->removeToken();
                }
            }
        }
        if (empty($ret)) {
            $ret['alert'] = Language::get('Unable to complete the transaction');
        }
        // คืนค่าเป็น JSON
        echo json_encode($ret);
    }

    /**
     * ลงทะเบียนสมาชิกใหม่
     * คืนค่าแอเรย์ของข้อมูลสมาชิกใหม่.
     *
     * @param Model $model
     * @param array $save       ข้อมูลสมาชิก
     * @param array $permission
     *
     * @return array
     */
    public static function execute($model, $save, $permission = null)
    {
        // permission ถ้าเป็น null สามารถทำได้ทั้งหมด
        if ($permission === null) {
            $permission = array_keys(\Gcms\Controller::getPermissions());
        }
        if (!isset($save['username'])) {
            $save['username'] = '';
        }
        if (!isset($save['password'])) {
            $save['password'] = '';
        } else {
            $save['salt'] = uniqid();
            $save['password'] = sha1($save['password'].$save['salt']);
        }
        $save['permission'] = empty($permission) ? '' : ','.implode(',', $permission).',';
        $save['create_date'] = date('Y-m-d H:i:s');
        // บันทึกลงฐานข้อมูล
        $save['id'] = $model->db()->insert($model->getTableName('user'), $save);
        // คืนค่าแอเรย์ของข้อมูลสมาชิกใหม่
        $save['permission'] = array();
        foreach ($permission as $key => $value) {
            $save['permission'][] = $value;
        }

        return $save;
    }
}
