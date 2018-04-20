# 更新日志

## v1.0.4.20180416_beta
> 增加两处,优化一处,修复一处

 - [x] `增加`后台删除缓存文件,上传项目分发完成后自动删除缓存文件
 - [x] `增加`前台media文件路径录入，自动分发到相应目录
 - [x] `优化`项目结构,减少冗余文件
 - [x] `修复`bower包管理默认安装路径方法，解决兼容性问题

## v1.0.3.20180414_beta
> 修改一处

 - [x] `修改`bower包管理默认安装路径`public/assets/libs`

## v1.0.2.20180413_beta
> 新增一处

 - [x] `增加`部署前台页面自动替换静态资源文件路径

## v1.0.1.20180413_beta
> 新增两处,修复两处,升级一处

 - [x] `增加`后台部署前端项目完毕后自动删除缓存文件
 - [x] `增加`删除目录及其目录下所有文件delDirAndFile方法
 - [x] `修复`前端自动部署控制器第一个字母大写问题
 - [x] `修复`数据库默认密码为空
 - [x] `升级`内核到`1.0.0.20180406_beta`

### 修改
```$xslt
application/database.php
application/common/view/tpl/dispatch_jump.tpl
application/admin/view/index/login.html
application/admin/view/common/menu.html
application/admin/view/common/header.html
public/assets/css/backend.css
application/admin/view/index/index.html
application/admin/command/Install/fastadmin.sql 站点配置blueadmin
```
### 删除
```$xslt
application/index/view/index
```
### 新增
```$xslt
jquery.fullPage.min.css
main.css
main.js
plugin.js
A.png
B.png
C.png
``` 

测试信息






123
