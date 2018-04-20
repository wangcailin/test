<?php

namespace app\index\controller\vietnam;;

use app\common\controller\Wechat;

/**
 * @project vietnam
 */
class Before extends Wechat
{

    public function _initialize()
    {
        parent::_initialize();
    }
    

    public function index()
    {
        return $this->view->fetch();
    }

    public function participantnotice()
    {
        return $this->view->fetch();
    }

    public function agenda()
    {
        return $this->view->fetch();
    }

    public function allinem()
    {
        return $this->view->fetch();
    }

    public function photoshareing()
    {
        return $this->view->fetch();
    }

    public function voting()
    {
        return $this->view->fetch();
    }

}
