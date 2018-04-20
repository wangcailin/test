<?php

namespace app\common\model;

use think\Model;

class ProjectModule extends Model
{
    // 表名
    protected $name = 'project_module';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = 'updatetime';
    
    // 追加属性
    protected $append = [
        'monitorlist_text'
    ];
    

    
    public function getMonitorlistList()
    {
        return ['0' => __('Monitorlist 0'),'1' => __('Monitorlist 1'),'2' => __('Monitorlist 2')];
    }     


    public function getMonitorlistTextAttr($value, $data)
    {
        $value = $value ? $value : $data['monitorlist'];
        $valueArr = explode(',', $value);
        $list = $this->getMonitorlistList();
        return implode(',', array_intersect_key($list, array_flip($valueArr)));
    }

    protected function setMonitorlistAttr($value)
    {
        return is_array($value) ? implode(',', $value) : $value;
    }


}
