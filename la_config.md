# 网站配置信息

除了网站后台管理员，请不要手动修改这个文件的用户描述数据。

## 网站全局设置

<!-- Website -->

Title = LAMDWIKI

<!-- End of Website -->

## 用户、密码和登录信息配置

<!-- Users -->

admin
- Password = Admin
- DisplayName = WikiAdmin
- LoginTry = 0
- Locked = 0
- Admin = 1
- Mature = 1

<!-- End of Users -->



<!-- Groups -->

<!-- End of Groups -->

## MDConf示例

<!-- Demo of how to modify markdown conf -->

admin1 admin



ad min = 123
- password = abc
- my data = 123123



yiming admin



你可以随意在配置区域写东西，只是不建议这么做



配置格式会自动整理。



admin = infinite
- admin = Nico
- 只有赋值的 = 参数才会被保留

<!-- End of Demo of how to modify markdown conf -->

一个汉字是四个字长：

<!-- Dam -->

admin admin

<!-- End of Dam -->

## 对单独页面的附加配置

<!-- index.md -->

Layout = Gallery



Additional
- Path = ./img
- Style = 2
- Count = 1000
- Title = 照片墙功能
- ColumnCount = 3



Additional
- Path = ./SubFolder
- Style = 3
- Title = 我在说什么
- More = 啥
- QuickPost = 1

<!-- End of index.md -->

