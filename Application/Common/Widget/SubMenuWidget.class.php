<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 14-7-2
 * Time: 上午11:18
 * @author 郑钟良<zzl@ourstu.com>
 */

namespace Common\Widget;

use Think\Action;

/**通用二级子菜单组件
 * Class SubMenuWidget
 * @package Common\Widget
 * @auth 陈一枭
 */
class SubMenuWidget extends Action
{
    public function render($menu_list, $current, $brand, $id)
    {
        //tpl仅作为例子

        $tpl = array(
            'left' =>
                array(
                    array('tab' => 'home', 'title' => '顶级菜单A', 'href' => U('blog/index/index'), 'icon' => 'home'),
                    array('tab' => 'category_1', 'title' => '顶级菜单B', 'href' => U('blog/article/lists', array('category' => 1))),
                    array('tab' => 'chuangye', 'title' => '顶级菜单C', 'href' => U('blog/article/lists', array('category' => 42)),
                        'children' => array(
                            array('tab' => 'child_1', 'title' => '子菜单1', 'href' => U('blog/index/index'), 'icon' => 'home'),
                            array('tab' => 'child_2', 'title' => '子菜单2', 'href' => U('blog/article/lists', array('category' => 1)))
                        )
                    )
                ),
            'right' =>
                array(
                    array('tab' => 'user', 'title' => '用户', 'href' => U('blog/index/index'), 'icon' => 'user',
                        'children' => array(
                            array('tab' => 'child_1', 'title' => '个人中心', 'href' => U('blog/index/index'), 'icon' => 'home'),
                            array('tab' => 'child_2', 'title' => '注销', 'href' => U('blog/article/lists', array('category' => 1)))
                        )
                    ),
                    array('title' => '我的财富:20'),
                    array('title' => '我的订单', 'href' => U('blog/article/lists'))
                )
        );
        $this->assign('current', $current);
        $this->assign('menu_list', $menu_list);
        $this->assign('brand', $brand);
        $this->display(T('Application://Common@Widget/menu'));
    }
}