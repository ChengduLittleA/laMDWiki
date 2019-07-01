# 那么的维基！

欢迎使用LaMDWiki（也叫做“那么的维基”）！

## 简介

这是小A编写的一个用来取代老式MDWiki的多功能超轻量网站后端，特别适合用来作为个人博客核知识库的框架。如果你想拥有一个小巧的个人网站来张贴自己的内容，可以考虑使用LaMDWiki。[点击这里](http://www.wellobserve.com/MDWiki/Release/lamdwiki_20190524.zip)即可下载LaMDWiki的最新版本到您的计算机上。

LaMDWiki具有下面的特点:

- 字大看得清，减少近视风险。
- 界面丑，防止长时间使用。
- 巨轻量，适合个人使用。
- 网页自适应布局，使用手机和电脑都可以完成同样的工作。
- 方便的写文和修改功能。
- 等等……

## 教程

将LaMDWiki配置好，就可以立刻开始编写你的网站了。如果你是初次搭建网站，那么可以参考下一节的说明。LaMDWiki和MDWiki的文件结构与URL互相兼容，如果你正在使用MDWiki，则不需要对你现有的网站做任何改动，以MDWiki形式书写的URL仍然可以被LaMDWiki正确识别。

初次使用LaMDWiki？请查看[网站配置原理](MarkdownConf.md)。

已经将网站全局配置成了需要的样子？试试[文章编辑](Writing.md)。

如何控制网站的文件结构？请参考[网站内容管理](Management.md)。

想添加多媒体文件？不妨看下[LaMDWiki独特的多媒体支持](3DDemo/index.md)。

想要添加动态的页面内容？可以使用LaMDWiki的[附加内容](DynamicContent.md)。

使用LaMDWiki展示图像内容？尝试使用灵活的[嵌入式画框](ImageShowCase.md)。

想要自适应手机显示器的文字布局？LaMDWiki也提供了[这样的功能](AdaptiveLayout.md)。

## 如何安装

LaMDWiki在首次运行时自动为您创建一个简单的示例网站，如果本机安装有http和php服务，即可立即访问以查看效果。如果希望直接将LaMDWiki安装到服务器上，则只需要将文件上传到服务器的网站根目录即可。

下面列出了包内不同文件的用途，用户可以按需定制：

```
--------[ 必要文件 ]----------
index.php            ---> lamdwiki响应
lawebmanagement.php  ---> lamdwiki核心
ParseDown.php        ---> Markdown语法解析器

--------[ 选装功能 ]----------
ParseDownExtra.php   ---> Markdown扩展语法
three.min.js        |
Controls.js         |
GLTFLoader.js       |---> 三维查看器功能部件
```

## 高级内容

如果你希望自定义LaMDWiki的外观，可以在lawebmanagement.php中找到默认的样式表。LaMDWiki之后会加入主题修改功能。LaMDWiki的页面结构相对简单，但是仍然有层级嵌套，并且多数类在不同区域都有复用。此外，一些元素拥有内联样式，在修改时需要检查是否符合预期。

LaMDWiki目前支持多个管理员，但是没有独立的用户系统，

LaMDWiki的登陆系统不安全，请不要在重要的场合使用LaMDWiki，如果必要，请申请HTTPS证书。

LaMDWiki使用Cookie提供页面跳转和访客设置记录等实用功能。

LaMDWiki使用Javascript提供页面交互和多媒体功能。

## 笔记本

LaMDWiki的基本功能已经很完备，接下来将再为它设计一些轻量的附加功能。[在这里查看LaMDWiki的功能小点子](Notes/index.md)。

