# 官方网站/Official Website

[中文：成都小A的**那么的维基**](http://www.wellobserve.com/?page=MDWiki/index.md&set_translation=zh) / [EN: ChengduLittleA's **LaMDWiki**!](http://www.wellobserve.com/?page=MDWiki/index.md&set_translation=en)

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

初次使用LaMDWiki？请查看[网站配置原理](http://www.wellobserve.com/index.php?page=MDWiki/MarkdownConf.md)。

已经将网站全局配置成了需要的样子？试试[文章编辑](http://www.wellobserve.com/index.php?page=MDWiki/Writing.md)。

如何控制网站的文件结构？请参考[网站内容管理](http://www.wellobserve.com/index.php?page=MDWiki/Management.md)。

想添加多媒体文件？不妨看下[LaMDWiki独特的多媒体支持](http://www.wellobserve.com/index.php?page=MDWiki/3DDemo/index.md)。

想要添加动态的页面内容？可以使用LaMDWiki的[附加内容](http://www.wellobserve.com/index.php?page=MDWiki/DynamicContent.md)。

使用LaMDWiki展示图像内容？尝试使用灵活的[嵌入式画框](http://www.wellobserve.com/index.php?page=MDWiki/ImageShowCase.md)。

想要自适应手机显示器的文字布局？LaMDWiki也提供了[这样的功能](http://www.wellobserve.com/index.php?page=MDWiki/AdaptiveLayout.md)。

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

LaMDWiki的基本功能已经很完备，接下来将再为它设计一些轻量的附加功能。[在这里查看LaMDWiki的功能小点子](http://www.wellobserve.com/index.php?page=MDWiki/Notes/index.md)。

-----------------

# LaMDWiki!

Thanks for choosing LaMDWiki!

## Introduction

ChengduLittleA wrote this lightweight website back-end, it is most suitable for personal wiki or blog. If you want to have a tiny personal website to post your own stuff there, you can consider using LaMDWiki.

[Click Here](http://www.wellobserve.com/MDWiki/Release/lamdwiki_20190524.zip) to download the latest LaMDWiki release to your computer. 

LaMDWiki has folowing features:

- Fully compatible with MDWiki.
- Ugly user interface, effectively prevent addiction.
- Larger fonts, reduce the risk of myopia.
- Lightweight, not safe, easy to be attacked.
- Adaptive interface, optimal viewing and operating experience on mobile and desktop devices.
- One-click story posting.
- Folder permission configuration.
- Multi-language automatic switching.
- and many more...

## Tutorial

Upload LaMDWiki to your server and you are ready to go. If this is the first time you make a website, please refer to the next link for explainations. LaMDWiki is fully compatible with MDWiki, if you are using MDWiki, you don't need to modify any thing existing on your original website. Also, MDWiki-styled URL can be correctly interpreted by LaMDWiki.

First time using LaMDWiki? Check out [How configuration works](http://www.wellobserve.com/index.php?page=MDWiki/MarkdownConf.md)。

Already configured your website? Try out [Passage editing](http://www.wellobserve.com/index.php?page=MDWiki/Writing.md)。

How to manage your website structure? Please refer to [Content management](http://www.wellobserve.com/index.php?page=MDWiki/Management.md)。

Want to embed multimedia contents? Why not use [LaMDWiki multimedia support](http://www.wellobserve.com/index.php?page=MDWiki/3DDemo/index.md)。

Want dynamic page contents? It's all included in [Advanced functions](http://www.wellobserve.com/index.php?page=MDWiki/DynamicContent.md)。

Displaying images using LaMDWiki？Use [embedded image frames](http://www.wellobserve.com/index.php?page=MDWiki/ImageShowCase.md).

Adaptive column layout on your mobile devices? LaMDWiki also provide [such function](http://www.wellobserve.com/index.php?page=MDWiki/AdaptiveLayout.md).

## How to Install

LaMDWiki will create a demonstration website the first time you access it. If you have http and php running on localhost, then you can check out the result immediately. Uploading LaMDWiki to your server root, then access through your URL will also do.

Different files and their usages:

```
--------[ Necessary Files ]----------
index.php            ---> lamdwiki response
lawebmanagement.php  ---> lamdwiki kernel
ParseDown.php        ---> Markdown grammer solver

--------[ Optional Files ]----------
ParseDownExtra.php   ---> Markdown extensions
three.min.js        |
Controls.js         |
GLTFLoader.js       |---> 3D viewer components
```

## Advanced

Customizing LaMDWiki's look is possible, you can find the default CSS style sheet in lawebmanagement.php. LaMDWiki will include theme choosing functions in the future. LaMDWiki has a relatively simple page structure, which makes CSS modifications easier. There are some amount of re-use of some certain classes, always check with your browse when changing the style.

LaMDWiki Supports multiple administrators.

LaMDWiki doesn't have a secure login system. If necessary, please register for a HTTPS certification for your site.

LaMDWiki uses Cookie to store visitor settings and to provide page relocation service.

LaMDWiki uses Javascript to provide page interactions.


## Notebook

LaMDWiki now has a pretty stable basic structure. Now we can design some more additional lightweight functions. [Check new ideas for LaMDWiki Here](http://www.wellobserve.com/index.php?page=MDWiki/Notes/index.md)。



