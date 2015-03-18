<?php

namespace Admin\Controller;


use Admin\Builder\AdminConfigBuilder;
use Admin\Builder\AdminListBuilder;

/**
 * Class ActionLimitController  后台行为限制控制器
 * @package Admin\Controller
 * @author:xjw129xjt(肖骏涛) xjt@ourstu.com
 */
class ActionLimitController extends AdminController
{


    public function limitList()
    {
        $action_name = I('get.action','','op_t') ;
        !empty($action_name) && $map['action_list'] = array(array('like', '%[' . $action_name . ']%'),'','or');
        //读取规则列表
        $map['status'] = array('EGT', 0);
        $model = M('action_limit');
        $List = $model->where($map)->order('id asc')->select();
        foreach($List as &$val){
            $timeUnit = $this->getTimeUnit();
            $val['time_unit'] = $timeUnit[$val['time_unit']];
            empty( $val['action_list']) &&  $val['action_list'] = '所有行为';
        }
        unset($val);
        //显示页面
        $builder = new AdminListBuilder();
        $builder->title('行为限制列表')
            ->buttonNew(U('editLimit'))
            ->setStatusUrl(U('setLimitStatus'))->buttonEnable()->buttonDisable()->buttonDelete()
            ->keyId()
            ->keyTitle()
            ->keyText('name', '名称')
            ->keyText('frequency', '频率')
            ->keyText('time_unit', '时间单位')
            ->keyText('punish', '处罚')
            ->keyBool('if_message', '是否发送提醒')
            ->keyText('message_content', '消息提示内容')
            ->keyText('action_list', '行为')
            ->keyStatus()
            ->keyDoActionEdit('editLimit?id=###')
            ->data($List)
            ->display();
    }

    public function editLimit()
    {
        $aId = I('id', 0, 'intval');
        $model = D('ActionLimit');
        if (IS_POST) {

            $data['title'] = I('post.title', '', 'op_t');
            $data['name'] = I('post.name', '', 'op_t');
            $data['frequency'] = I('post.frequency', 1, 'intval');
            $data['time_unit'] = I('post.time_unit', '', 'op_t');
            $data['punish'] = I('post.punish', '', 'op_t');
            $data['if_message'] = I('post.if_message', '', 'op_t');
            $data['message_content'] = I('post.message_content', '', 'op_t');
            $data['action_list'] = I('post.action_list', '', 'op_t');
            $data['status'] = I('post.status', 1, 'intval');

            $data['punish'] = implode(',', $data['punish']);

            foreach($data['action_list'] as &$v){
                $v = '['.$v.']';
            }
            unset($v);
            $data['action_list'] = implode(',', $data['action_list']);
            if ($aId != 0) {
                $data['id'] = $aId;
                $res = $model->editActionLimit($data);
            } else {
                $res = $model->addActionLimit($data);
            }
            $this->success(($aId == 0 ? '添加' : '编辑') . '成功', $aId == 0 ? U('', array('id' => $res)) : '');

        } else {
            $builder = new AdminConfigBuilder();
            if ($aId != 0) {
                $limit = $model->getActionLimit(array('id' => $aId));
                $limit['punish'] = explode(',', $limit['punish']);
                $limit['action_list'] = str_replace('[','',$limit['action_list']);
                $limit['action_list'] = str_replace(']','',$limit['action_list']);
                $limit['action_list'] = explode(',', $limit['action_list']);
            } else {
                $limit = array('status' => 1);
            }
            $opt_punish = $this->getPunish();
            $opt = D('Action')->getActionOpt();
            $builder->title(($aId == 0 ? '新增' : '编辑') . '行为限制')->keyId()
                ->keyTitle()
                ->keyText('name', '名称')
                ->keyText('frequency', '频率')
                ->keySelect('time_unit', '时间单位', '', $this->getTimeUnit())
                ->keyChosen('punish', '处罚', '可多选', $opt_punish)
                ->keyBool('if_message', '是否发送提醒')
                ->keyTextArea('message_content', '消息提示内容')
                ->keyChosen('action_list', '行为', '可多选', $opt)
                ->keyStatus()
                ->data($limit)
                ->buttonSubmit(U('editLimit'))->buttonBack()->display();
        }
    }


    public function setLimitStatus($ids, $status)
    {
        $builder = new AdminListBuilder();
        $builder->doSetStatus('action_limit', $ids, $status);
    }

    private function getTimeUnit()
    {
        return array('second' => '秒', 'minute' => '分', 'hour' => '小时', 'day' => '天', 'week' => '周', 'month' => '月', 'year' => '年');
    }


    private function getPunish()
    {
        $obj = new \ActionLimit();
        return $obj->punish;

    }


}
