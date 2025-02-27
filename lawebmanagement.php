<?php

/**
 * Send email class using SMTP Authentication
 *
 * @class Email
 * @package Snipworks\SMTP
 */
class Email
{
    const CRLF = "\r\n";
    const TLS = 'tcp';
    const SSL = 'ssl';
    const OK = 250;

    protected $server;
    protected $hostname;
    protected $port;
    protected $socket;
    protected $username;
    protected $password;
    protected $connectionTimeout;
    protected $responseTimeout;
    protected $subject;
    protected $to = array();
    protected $cc = array();
    protected $bcc = array();
    protected $from = array();
    protected $replyTo = array();
    protected $attachments = array();
    protected $protocol = null;
    protected $textMessage = null;
    protected $htmlMessage = null;
    protected $isHTML = false;
    protected $isTLS = false;
    protected $logs = array();
    protected $charset = 'utf-8';
    protected $headers = array();
    
    protected $result;
    
    public function getSendResult(){
        return $this->result;
    }
    
    public function __construct($server, $port = 25, $connectionTimeout = 30, $responseTimeout = 8, $hostname = null)
    {
        $this->port = $port;
        $this->server = $server;
        $this->connectionTimeout = $connectionTimeout;
        $this->responseTimeout = $responseTimeout;
        $this->hostname = empty($hostname) ? gethostname() : $hostname;
        $this->headers['X-Mailer'] = 'PHP/' . phpversion();
        $this->headers['MIME-Version'] = '1.0';
    }
    public function addTo($address, $name = null)
    {
        $this->to[] = array($address, $name);

        return $this;
    }
    public function addCc($address, $name = null)
    {
        $this->cc[] = array($address, $name);

        return $this;
    }
    public function addBcc($address, $name = null)
    {
        $this->bcc[] = array($address, $name);

        return $this;
    }
    public function addReplyTo($address, $name = null)
    {
        $this->replyTo[] = array($address, $name);

        return $this;
    }
    public function addAttachment($attachment)
    {
        if (file_exists($attachment)) {
            $this->attachments[] = $attachment;
        }

        return $this;
    }
    public function setLogin($username, $password)
    {
        $this->username = $username;
        $this->password = $password;

        return $this;
    }
    public function setCharset($charset)
    {
        $this->charset = $charset;

        return $this;
    }
    public function setProtocol($protocol = null)
    {
        if ($protocol === self::TLS) {
            $this->isTLS = true;
        }

        $this->protocol = $protocol;

        return $this;
    }
    public function setFrom($address, $name = null)
    {
        $this->from = array($address, $name);

        return $this;
    }
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }
    public function setTextMessage($message)
    {
        $this->textMessage = $message;

        return $this;
    }
    public function setHtmlMessage($message)
    {
        $this->htmlMessage = $message;

        return $this;
    }
    public function getLogs()
    {
        return $this->logs;
    }
    public function send($wait_timeout)
    {
        $message = NULL;
        $this->socket = fsockopen(
            $this->getServer(),
            $this->port,
            $errorNumber,
            $errorMessage,
            $this->connectionTimeout
        );
        
        if (empty($this->socket)) {
            return false;
        }
        
        $this->logs['CONNECTION'] = $this->getResponse();
        $this->logs['HELLO'][1] = $this->sendCommand('EHLO ' . $this->hostname);

        if ($this->isTLS) {
            $this->logs['STARTTLS'] = $this->sendCommand('STARTTLS');
            stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $this->logs['HELLO'][2] = $this->sendCommand('EHLO ' . $this->hostname);
        }

        $this->logs['AUTH'] = $this->sendCommand('AUTH LOGIN');
        $this->logs['USERNAME'] = $this->sendCommand(base64_encode($this->username));
        $this->logs['PASSWORD'] = $this->sendCommand(base64_encode($this->password));
        $this->logs['MAIL_FROM'] = $this->sendCommand('MAIL FROM: <' . $this->from[0] . '>');

        $recipients = array_merge($this->to, $this->cc, $this->bcc);
        foreach ($recipients as $address) {
            $this->logs['RECIPIENTS'][] = $this->sendCommand('RCPT TO: <' . $address[0] . '>');
        }

        $this->headers['Date'] = date('r');
        $this->headers['Subject'] = $this->subject;
        $this->headers['From'] = $this->formatAddress($this->from);
        $this->headers['Return-Path'] = $this->formatAddress($this->from);
        $this->headers['To'] = $this->formatAddressList($this->to);

        if (!empty($this->replyTo)) {
            $this->headers['Reply-To'] = $this->formatAddressList($this->replyTo);
        }

        if (!empty($this->cc)) {
            $this->headers['Cc'] = $this->formatAddressList($this->cc);
        }

        if (!empty($this->bcc)) {
            $this->headers['Bcc'] = $this->formatAddressList($this->bcc);
        }

        $boundary = md5(uniqid(microtime(true), true));

        if (!empty($this->attachments)) {
            $this->headers['Content-Type'] = 'multipart/mixed; boundary="mixed-' . $boundary . '"';
            $message = '--mixed-' . $boundary . self::CRLF;
            $message .= 'Content-Type: multipart/alternative; boundary="alt-' . $boundary . '"' . self::CRLF . self::CRLF;
        } else {
            $this->headers['Content-Type'] = 'multipart/alternative; boundary="alt-' . $boundary . '"';
        }

        if (!empty($this->textMessage)) {
            $message .= '--alt-' . $boundary . self::CRLF;
            $message .= 'Content-Type: text/plain; charset=' . $this->charset . self::CRLF;
            $message .= 'Content-Transfer-Encoding: base64' . self::CRLF . self::CRLF;
            $message .= chunk_split(base64_encode($this->textMessage)) . self::CRLF;
        }

        if (!empty($this->htmlMessage)) {
            $message .= '--alt-' . $boundary . self::CRLF;
            $message .= 'Content-Type: text/html; charset=' . $this->charset . self::CRLF;
            $message .= 'Content-Transfer-Encoding: base64' . self::CRLF . self::CRLF;
            $message .= chunk_split(base64_encode($this->htmlMessage)) . self::CRLF;
        }

        $message .= '--alt-' . $boundary . '--' . self::CRLF . self::CRLF;

        if (!empty($this->attachments)) {
            foreach ($this->attachments as $attachment) {
                $filename = pathinfo($attachment, PATHINFO_BASENAME);
                $contents = file_get_contents($attachment);
                $type = mime_content_type($attachment);
                if (!$type) {
                    $type = 'application/octet-stream';
                }

                $message .= '--mixed-' . $boundary . self::CRLF;
                $message .= 'Content-Type: ' . $type . '; name="' . $filename . '"' . self::CRLF;
                $message .= 'Content-Disposition: attachment; filename="' . $filename . '"' . self::CRLF;
                $message .= 'Content-Transfer-Encoding: base64' . self::CRLF . self::CRLF;
                $message .= chunk_split(base64_encode($contents)) . self::CRLF;
            }

            $message .= '--mixed-' . $boundary . '--';
        }

        $headers = '';
        foreach ($this->headers as $k => $v) {
            $headers .= $k . ': ' . $v . self::CRLF;
        }

        $this->logs['MESSAGE'] = $message;
        $this->logs['HEADERS'] = $headers;
        $this->logs['DATA'][1] = $this->sendCommand('DATA');
        $this->logs['DATA'][2] = $this->sendCommand($headers . self::CRLF . $message . self::CRLF . '.');
        $this->logs['QUIT'] = $this->sendCommand('QUIT');
        fclose($this->socket);
        
        $this->result['recipients'] = $recipients;
        $this->result['status'] = substr($this->logs['DATA'][2], 0, 3);

        return $this->result['status'] == self::OK;
    }
    protected function getServer()
    {
        return ($this->protocol) ? $this->protocol . '://' . $this->server : $this->server;
    }
    protected function getResponse()
    {
        $response = '';
        stream_set_timeout($this->socket, $this->responseTimeout);
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= trim($line) . "\n";
            if (substr($line, 3, 1) == ' ') {
                break;
            }
        }

        return trim($response);
    }
    protected function sendCommand($command)
    {
        fputs($this->socket, $command . self::CRLF);

        return $this->getResponse();
    }
    protected function formatAddress($address)
    {
        return (empty($address[1])) ? $address[0] : '"' . $address[1] . '" <' . $address[0] . '>';
    }
    protected function formatAddressList(array $addresses)
    {
        $data = array();
        foreach ($addresses as $address) {
            $data[] = $this->formatAddress($address);
        }

        return implode(', ', $data);
    }
}


#
# LAManagement version 1.0 By ChengduLittleA-YimingWu
# http://www.wellobserve.com
# xp8110@outlook.com
#

$UserData = "LAUsers.md";

include 'Parsedown.php';    
include 'ParsedownExtra.php';


class LAManagement{
    protected $PDE;
    protected $UserConfig;
    
    protected $List301;
    
    protected $UserDsipName;
    protected $UserID;
    protected $userIsMature;
    
    protected $PagePath;
    protected $LanguageAppendix;
    protected $FileTitle;
    
    protected $FolderNameList;
    protected $FileNameList;
    protected $OtherFileNameList;
    protected $RecentUpdatedList;
    protected $PrivateFolderList;
    
    protected $IsEditing;
    
    protected $IsTaskManager;
    protected $TaskManagerEntries;
    protected $TaskManagerTitle;
    protected $TaskManagerGroups;
    protected $TaskManagerSelf;
    protected $TrackerFile;
    protected $Trackable;
    protected $GLOBAL_TASK_I;
    
    protected $MailHost;
    protected $MailUser;
    protected $MailPort;
    protected $MailPassword;
    protected $MailTitle;
    protected $MailTitleEN;
    protected $MailFoot;
    protected $MailFootEN;
    
    protected $MailSendResults;
    
    protected $SubscriberTitle;
    protected $SubscriberFolder;
    protected $SubscriberID;
    protected $SubscriberIDExisting;
    protected $SubscriberMailAddress;
    protected $SubscriberLanguage;
    
    protected $MailSubscribers;
    
    protected $PrevFile;
    protected $NextFile;
    
    protected $Additional;
    protected $AdditionalLayout;
    
    protected $IsMainPassage;
    
    protected $AudioList;
    protected $SceneList;
    protected $BlockImageList; //doubles as video
    protected $LinkList;
    
    protected $AfterPassage2D;
    protected $AfterPassage3D;
        
    protected $MainFileTitle;
    protected $MainFileIsNSFW;
    protected $FileIsNSFW;
    
    
    protected $Title;
    protected $TitleEN;
    protected $StringTitle;
    protected $StringTitleEN;
    protected $Footnote;
    protected $FootnoteEN;    
    protected $SmallQuoteName;
    
    protected $StatsFile;
    protected $IsStatsDisplay;
    
    protected $TaskHighlightInvert;
    
    protected $BackgroundSemi;
    
    protected $MainContentAlreadyBegun;
    
    protected $unique_item_count;
    
    protected $force_last_line;
    
    protected $DICT;
    
    protected $prefer_dark;
    protected $cblack;
    protected $cwhite;
    protected $csemiwhite;
    protected $chalfhighlight;
    protected $chighlight;
    
    protected $lock_file;
    
    function ChooseColorScheme(){
        if($this->prefer_dark){
            $this->cwhite = 'black';
            $this->cblack = 'white';
            $this->csemiwhite = "rgba(0,0,0,0.9)";
            $this->chighlight = 'cornflowerblue';
            $this->chalfhighlight = '#365181';
        }else{
            $this->cwhite = 'white';
            $this->cblack = 'black';
            $this->csemiwhite = "rgba(255,255,255,0.9)";
            $this->chighlight = 'gold';
            $this->chalfhighlight = '#fff4b9';
        }
    }

    function DoSetColorScheme(){
        $hour = intval(date('H'));

        if(isset($_GET['theme'])){
            $theme = $_GET['theme'];
            if($theme == 'unset') unset($_SESSION['la_theme']);
            else $_SESSION['la_theme']=$theme;
        }

        if(isset($_SESSION['la_theme'])){
            if($_SESSION['la_theme'] == 'black'){
                if($hour>=19 || $hour<6){
                    unset($_SESSION['la_theme']);
                }
                $this->prefer_dark = 1;
            }else{
                if(!($hour>=19 || $hour<6)){
                    unset($_SESSION['la_theme']);
                }
            }
        }else{
            if($hour>=19 || $hour<6){
                $this->prefer_dark = 1;
            }
        }
    }
    
    function AddTranslationEntry($zh,$en){
        $entry['zh'] = $zh;
        $entry['en'] = $en;
        $this->DICT[] = $entry;
    }
    
    function FROM_ZH($zh){
        if(!$this->LanguageAppendix) return $zh;
        foreach($this->DICT as $entry){
            if($entry['zh']==$zh)
                return $entry[$this->LanguageAppendix];
        }
        return $zh;
    }
    
    function FROM_EN($en){
        if(!$this->LanguageAppendix) return $en;
        foreach($this->DICT as $entry){
            if($entry['en']==$en)
                return $entry[$this->LanguageAppendix];
        }
        return $en;
    }
    
    function UseLanguage(){
        if(!$this->LanguageAppendix) return 'zh';
        else return $this->LanguageAppendix;
    }
    
    function LockRoot(){
        if(!file_exists("la_lock")){
            $f = fopen("la_lock","w");
            fclose($f);
        }
        $this->lock_file = fopen("la_lock","r");
        flock($this->lock_file,LOCK_EX);
    }
    
    function __construct() {
        $this->PDE = new ParsedownExtra();
        $this->PDE->SetInterlinkPath('/');
        $this->AddTranslationEntry('返回','Back');
        $this->AddTranslationEntry('上级','Up');
        $this->AddTranslationEntry('首页','Home');
        $this->AddTranslationEntry('列表','List');
        $this->AddTranslationEntry('夜间','Night');
        $this->AddTranslationEntry('明亮','Day');
        $this->AddTranslationEntry('在夜间模式','In night mode');
        $this->AddTranslationEntry('调成明亮','Brighten up');
        $this->AddTranslationEntry('进入夜间模式','Go to night mode');
        $this->AddTranslationEntry('进入夜间','Night mode');
        $this->AddTranslationEntry('列表','List');
        $this->AddTranslationEntry('管理','Files');
        $this->AddTranslationEntry('写文','Write');
        $this->AddTranslationEntry('查看全部','View all');
        
        $this->AddTranslationEntry('那么的','LAMD');
        $this->AddTranslationEntry('相册','ALBUM');
        $this->AddTranslationEntry('声音','Sound');
        
        $this->AddTranslationEntry('今天','Today');
        $this->AddTranslationEntry('更多','More');
        $this->AddTranslationEntry('编辑','Edit');
        $this->AddTranslationEntry('完成','Finish');
        $this->AddTranslationEntry('放弃','Discard');
        $this->AddTranslationEntry('放弃修改','Discard changes');
        $this->AddTranslationEntry('个字','words');
        $this->AddTranslationEntry('长度','len');
        $this->AddTranslationEntry('已编辑','Modified');
        
        $this->AddTranslationEntry('登录','Log in');
        $this->AddTranslationEntry('用户名','User name');
        $this->AddTranslationEntry('密码','Password');
        $this->AddTranslationEntry('登出','Log out');
        
        $this->AddTranslationEntry('过滤','Filter');
        $this->AddTranslationEntry('春','Spring');
        $this->AddTranslationEntry('夏','Summer');
        $this->AddTranslationEntry('秋','Altumn');
        $this->AddTranslationEntry('冬','Winter');
        
        $this->AddTranslationEntry('跟踪','Tracker');
        $this->AddTranslationEntry('正在跟踪','Tracking');
        $this->AddTranslationEntry('总览','Overview');
        $this->AddTranslationEntry('当前在','At');
        $this->AddTranslationEntry('新增','New');
        $this->AddTranslationEntry('在事件组','in event group');
        $this->AddTranslationEntry('事件描述','Event description');
        $this->AddTranslationEntry('标签','Tags');
        $this->AddTranslationEntry('取消','Cancel');
        $this->AddTranslationEntry('保存','Save');
        $this->AddTranslationEntry('修改','Edit');
        $this->AddTranslationEntry('进行','Run');
        $this->AddTranslationEntry('丢弃','Cancel');
        $this->AddTranslationEntry('删除','Del');
        $this->AddTranslationEntry('暂缓','Wait');
        $this->AddTranslationEntry('放回队列','Put back');
        $this->AddTranslationEntry('删除条目','Delete item');
        $this->AddTranslationEntry('确认','Confirm');
        $this->AddTranslationEntry('确定','Confirm');
        $this->AddTranslationEntry('新增事件','New event');
        $this->AddTranslationEntry('到','to');
        $this->AddTranslationEntry('设置分组','Config grouping');
        $this->AddTranslationEntry('进入分组','Detailes');
        $this->AddTranslationEntry('编辑文字','Edit text');
        $this->AddTranslationEntry('添加组','New group');
        $this->AddTranslationEntry('删','Del');
        $this->AddTranslationEntry('创建索引','Create index');
        $this->AddTranslationEntry('选组','Select groups');
        $this->AddTranslationEntry('位于','at');
        $this->AddTranslationEntry('正常','Normal');
        $this->AddTranslationEntry('较早','Delayed');
        $this->AddTranslationEntry('很早','Ancient');
        
        $this->AddTranslationEntry('下一页','Next');
        $this->AddTranslationEntry('上一页','Prev');
        $this->AddTranslationEntry('不看了','Leave');
        
        $this->AddTranslationEntry('返回顶部','Back to top');
        $this->AddTranslationEntry('今日更新数','Updates<br />today');
        
        $this->AddTranslationEntry('选项','Options');
        $this->AddTranslationEntry('上传','Upload');
        $this->AddTranslationEntry('新文件夹','New folder');
        $this->AddTranslationEntry('新文件夹名','folder name');
        $this->AddTranslationEntry('到这里','Put');
        $this->AddTranslationEntry('选这个','Select');
        $this->AddTranslationEntry('进入','Enter');
        $this->AddTranslationEntry('移动','Move');
        $this->AddTranslationEntry('改名','Rename');
        $this->AddTranslationEntry('调整','Adjust');
        $this->AddTranslationEntry('选这个','Select');
        $this->AddTranslationEntry('的新名字','\'s new name');
        
        $this->AddTranslationEntry('网站设置','Settings');
        $this->AddTranslationEntry('查看为','View as');
        $this->AddTranslationEntry('退出','Exit');
        $this->AddTranslationEntry('设置中心','Settings');
        $this->AddTranslationEntry('网站信息','Website');
        $this->AddTranslationEntry('链接跳转项目','Redirect');
        $this->AddTranslationEntry('管理员','Admin');
        $this->AddTranslationEntry('网站标题','Title');
        $this->AddTranslationEntry('标签显示标题','HTML Title');
        $this->AddTranslationEntry('页脚附加文字','Footer string');
        $this->AddTranslationEntry('“我说”名片抬头文字','"Say" region title');
        $this->AddTranslationEntry('站点事件跟踪器','Site event tracker');
        $this->AddTranslationEntry('事件高亮显示','Event highlights');
        $this->AddTranslationEntry('反转','Invert');
        $this->AddTranslationEntry('自动重定向的链接','Redirected links');
        $this->AddTranslationEntry('修改账户昵称','Change display name');
        $this->AddTranslationEntry('重设管理账户名','Change admin id');
        $this->AddTranslationEntry('重设管理密码','Change admin password');
        $this->AddTranslationEntry('保存所有更改','Save all changes');
        
        $this->AddTranslationEntry('新闻稿','Newletter');
        $this->AddTranslationEntry('过往新闻稿','Old newsletters');
        $this->AddTranslationEntry('在这里填写您的邮箱','E-mail address here');
        $this->AddTranslationEntry('成功','Success');
        $this->AddTranslationEntry('不再订阅','Unsubscribe');
        $this->AddTranslationEntry('重新发送确认邮件','Re-send confirmation e-mail');
        $this->AddTranslationEntry('邮件发送状态','Mail sender status');
        $this->AddTranslationEntry('页面不存在。','Page does not exist.');
        $this->AddTranslationEntry('创建这个页面','Create this page');
        $this->AddTranslationEntry('停一下','Whoa there');
        $this->AddTranslationEntry('访客不能访问这个页面。','Visitors can not access this page.');
        $this->AddTranslationEntry('出错','Ooops');
        $this->AddTranslationEntry('文件上传遇到了未知问题，上传的文件可能不完整。','Unknown error during uploading, file could be incomplete.');
        $this->AddTranslationEntry('完成','Finished');
        $this->AddTranslationEntry('文件上传到下面的目录：','File has been uploaded to the path below:');
        $this->AddTranslationEntry('已收到您的订阅','Request received');
        $this->AddTranslationEntry('请检查您收件箱中的确认信，您需要通过确认信中的链接确认订阅。','Please check your mailbox for confirmation e-mail, you need the link there to activate your subscription.');
        $this->AddTranslationEntry('我知道了','Got it');
        $this->AddTranslationEntry('设置订阅','Configure Subscription');
        $this->AddTranslationEntry('您已经订阅过这个栏目。','You have already subscribed to this channel.');
        $this->AddTranslationEntry('请到您的收件箱检查确认信，它可能被某些邮件提供商标记为垃圾邮件。','Please check your mailbox for confirmation e-mail, it may be marked as trash by some e-mail providers.');
        $this->AddTranslationEntry('邮件传输错误','Mail Transfer Error');
        $this->AddTranslationEntry('已经记录下您的订阅申请，但是未能发送确认邮件。','Your subscription request is recorded, however we are not able to send you a confirmation e-mail.');
        $this->AddTranslationEntry('订阅已确认','Subscription Confirmed');
        $this->AddTranslationEntry('您将收到新闻稿。','You will recieve newsletters in the future.');
        
        $this->AddTranslationEntry('附加显示','Additional');
        $this->AddTranslationEntry('应用','Apply');
        $this->AddTranslationEntry('显示','Show');
        $this->AddTranslationEntry('显示为','Show as');
        $this->AddTranslationEntry('最近篇目数量','Display count');
        $this->AddTranslationEntry('天内完成的','Days Limit');
        $this->AddTranslationEntry('区域标题','Region Title');
        $this->AddTranslationEntry('方块列数量','Columns');
        $this->AddTranslationEntry('一行的照片数','In a row');
        $this->AddTranslationEntry('时间线列表按钮','Timeline Btn');
        $this->AddTranslationEntry('关闭快速发帖','Disable quick post');
        $this->AddTranslationEntry('启用快速发帖','Enable quick post');
        $this->AddTranslationEntry('改显示为摘要','Set excript');
        $this->AddTranslationEntry('改显示为全文','Set full');
        $this->AddTranslationEntry('描述文字','Description');
        
        $this->AddTranslationEntry('小声哔哔…','Chatter quietly...');
        $this->AddTranslationEntry('大声宣扬','Speak Loudly');
  
        $this->GLOBAL_TASK_I=0;
    }
    
    function __deconstruct(){
        if(!isset($this->lock_file)) return;
        flock($this->lock_file,LOCK_UN);
        fclose($this->lock_file);
    }
    
    function SetSubscriberCustomizationInfo($title, $folder, $mail_address, $id, $lang){
        $this->SubscriberTitle = $title;
        $this->SubscriberFolder = $folder;
        $this->SubscriberID = $id;
        $this->SubscriberMailAddress = $mail_address;
        $this->SubscriberLanguage = $lang;
    }
    
    function LimitAccess($mode){
        echo $this->MakeCenterContainerBegin();
        echo "<div class='the_body'>";
        echo "<div class='main_content' style='text-align:center;'>";
        
        if($mode==-1){
            echo "<h1>".$this->FROM_ZH("成功")."</h1><p>";
            if(isset($this->MailSendResults[0])){
                echo "<div style='text-align:left; max-height:50vh;'>";
                echo $this->FROM_ZH("邮件发送状态")."<br />";
                foreach($this->MailSendResults as $result){
                    echo '['.$result['status'].']&nbsp;&nbsp;';
                    foreach($result['recipients'] as $people){
                        echo $people[0].'&nbsp;';
                    }
                    echo "<br />";
                }
                echo "</div>";
            }
            if(isset($_SERVER["HTTP_REFERER"])) echo "<a href='".$_SERVER["HTTP_REFERER"]."'>🡰 ".$this->FROM_ZH("返回")."</a>";
            else echo "&nbsp;<a href='?page=index.md'>⌂ ".$this->FROM_ZH("首页")."</a></p>";
        }else if($mode==0){
            echo "<h1>404</h1>";
            echo "<p>".$this->FROM_ZH("页面不存在。")."<br />Page does not exist.<br />".$_GET["page"]."</p><p>";
            if(isset($_SERVER["HTTP_REFERER"])) echo "<a href='".$_SERVER["HTTP_REFERER"]."'>🡰 ".$this->FROM_ZH("返回")."</a>";
            echo "&nbsp;<a href='?page=index.md'>⌂ ".$this->FROM_ZH("首页")."</a></p>";
            if($this->IsLoggedIn()) echo "<p><a href='?page=".$_GET["page"]."&operation=new&title=".pathinfo($_GET["page"],PATHINFO_FILENAME)."'>".$this->FROM_ZH("创建这个页面")."</a></p>";
        }else if($mode==1){
            echo "<h1>".$this->FROM_ZH("停一下")."</h1>";
            echo $this->FROM_ZH("访客不能访问这个页面。");
            if(isset($_SERVER["HTTP_REFERER"])) echo "<a href='".$_SERVER["HTTP_REFERER"]."'>🡰 ".$this->FROM_ZH("返回")."</a>";
            echo "&nbsp;<a href='?page=index.md'>⌂ ".$this->FROM_ZH("首页")."</a></p>";
        }else if($mode==2){
            echo "<h1>".$this->FROM_ZH("出错")."</h1>";
            echo $this->FROM_ZH("文件上传遇到了未知问题，上传的文件可能不完整。");
            echo "<!--FILE_UPLOAD_ERROR-->";
            if(isset($_SERVER["HTTP_REFERER"])) echo "<a href='".$_SERVER["HTTP_REFERER"]."'>🡰 ".$this->FROM_ZH("返回")."</a>";
            else echo "&nbsp;<a href='?page=index.md'>⌂ ".$this->FROM_ZH("首页")."</a></p>";
        }else if($mode==3){
            echo "<h1>".$this->FROM_ZH("完成")."</h1>";
            echo $this->FROM_ZH("文件上传到下面的目录：");
            echo $this->InterlinkPath().'/'.$_FILES['upload_file_name']['name']."<p>";
            echo "<!--FILE_UPLOADED-->";
            if(isset($_SERVER["HTTP_REFERER"])) echo "<a href='".$_SERVER["HTTP_REFERER"]."'>🡰 ".$this->FROM_ZH("返回")."</a>";
            else echo "&nbsp;<a href='?page=index.md'>⌂ ".$this->FROM_ZH("首页")."</a></p>";
        }else if($mode==4){
            echo "<h1>".$this->FROM_ZH("已收到您的订阅")."</h1>";
            echo $this->FROM_ZH("请检查您收件箱中的确认信，您需要通过确认信中的链接确认订阅。");
            if(isset($_SERVER["HTTP_REFERER"])) echo "<a href='".$_SERVER["HTTP_REFERER"]."'>🡰 ".$this->FROM_ZH("我知道了")."</a>";
            else echo "&nbsp;<a href='?page=index.md'>⌂ ".$this->FROM_ZH("首页")."</a></p>";
        }else if($mode==5){
            echo "<h1>".$this->FROM_ZH("设置订阅")."</h1>";
            echo $this->FROM_ZH("您已经订阅过这个栏目。");
            if(isset($this->SubscriberIDExisting) &&$this->SubscriberIDExisting!='CONFIRMED'){
                echo "<p>".$this->FROM_ZH("请到您的收件箱检查确认信，它可能被某些邮件提供商标记为垃圾邮件。")."<br /><a href='?page=index.md".
                "&resend_email=true&folder=".$this->SubscriberFolder."&id=".$this->SubscriberIDExisting.
                "&title=".$this->SubscriberTitle."&mail_address=".$this->SubscriberMailAddress."&subscribe_language=".$this->SubscriberLanguage."'>".$this->FROM_ZH("重新发送确认邮件")."</a></p>";
            }else{
                echo "<p>选择您喜欢的新闻稿语言。（部分稿件可能只有一种语言）<br />".
                     "Choose preferred language for this subscription. (Some letters may only have one language)</p>".
                "<a href='?page=index.md&select_subscriber_langguage=true&folder=".$this->SubscriberFolder."&mail_address=".$this->SubscriberMailAddress."&subscribe_language=zh&set_translation=zh'>中文</a>&nbsp".
                "<a href='?page=index.md&select_subscriber_langguage=true&folder=".$this->SubscriberFolder."&mail_address=".$this->SubscriberMailAddress."&subscribe_language=en&set_translation=en'>English</a>".
                "</p>";
                echo "<p><a href='?page=index.md&unsubscribe=true&folder=".$this->SubscriberFolder."&mail_address=".$this->SubscriberMailAddress."'>".$this->FROM_ZH("不再订阅")."</a></p>";
            }
            echo "<p>";
            if(isset($_SERVER["HTTP_REFERER"])) echo "<a href='".$_SERVER["HTTP_REFERER"]."'>🡰 ".$this->FROM_ZH("返回")."</a>";
            echo "&nbsp;<a href='?page=index.md'>⌂ ".$this->FROM_ZH("首页")."</a></p>";
        }else if($mode==6){
            echo "<h1>".$this->FROM_ZH("邮件传输错误")."</h1>";
            echo "<p>".$this->FROM_ZH("已经记录下您的订阅申请，但是未能发送确认邮件。")."</p>";
            if(isset($_SERVER["HTTP_REFERER"])) echo "<a href='".$_SERVER["HTTP_REFERER"]."'>🡰".$this->FROM_ZH("返回")."</a>";
            else echo "&nbsp;<a href='?page=index.md'>⌂ ".$this->FROM_ZH("首页")."</a></p>";
        }else if($mode==7){
            echo "<h1>".$this->FROM_ZH("订阅已确认")."</h1>";
            echo $this->FROM_ZH("您将收到新闻稿。");
            echo "<p><a href='?page=index.md'>⌂ ".$this->FROM_ZH("首页")."</a></p>";
        }
        echo "</div>";
        echo "</div>";
        echo $this->MakeCenterContainerEnd();  
        exit;
    }
    
    function InstallLaMDWiki(){
        $index      = fopen('index.md','w');
        $navigation = fopen('navigation.md','w');
        $conf       = fopen('la_config.md','w');
        
        fwrite($index,'# 欢迎使用那么的维基！'.PHP_EOL.PHP_EOL);
        fwrite($index,'那么的维基已经成功安装在您的服务器。'.PHP_EOL.PHP_EOL.'点击右上角的&#127760;&#xfe0e;图标以登录管理，管理员默认帐号是admin，密码是Admin。注意二者均区分大小写。'.PHP_EOL.PHP_EOL);
        fwrite($index,'登录以后，点击您的用户名可以显示账户选项，也可进入网站设置页面。你可以在设置页面修改你的帐号显示名、登录名和密码，并配置网站的全局选项。'.PHP_EOL.PHP_EOL);
        fwrite($index,'打开[那么的维基手册](http://www.wellobserve.com/?page=MDWiki/index.md)，立即学习更多网站管理窍门。'.PHP_EOL.PHP_EOL);
        fwrite($index,'---------'.PHP_EOL.PHP_EOL.'那么的维基由BlenderCN-成都小A编写，请访问[小A的网站](http://www.wellobserve.com/)了解更多信息。'.PHP_EOL.PHP_EOL);
        fclose($index);
        
        fwrite($navigation,'[首页](index.md)');
        fclose($navigation);
        
        fwrite($conf,'网站配置文件'.PHP_EOL.PHP_EOL);
        fwrite($conf,'<!-- Users -->'.PHP_EOL.PHP_EOL);
        fwrite($conf,'admin'.PHP_EOL);
        fwrite($conf,'- DisplayName = WikiAdmin'.PHP_EOL);
        fwrite($conf,'- Password = Admin'.PHP_EOL);
        fwrite($conf,'- Mature = 0'.PHP_EOL);
        fwrite($conf, PHP_EOL);
        fwrite($conf,'<!-- end of Users -->'.PHP_EOL.PHP_EOL);
        fclose($conf);
    }
    
    function GetRelativePath($from, $to) {
        //echo $from.' -- '.$to;
        //$from = preg_replace('/^\.\//','',$from);
        //if($from == '.') $from='';
        $dir = explode('/', is_file($from) ? dirname($from) : rtrim($from, '/'));
        $file = explode('/', $to);
        
        //if(count($dir)==1 && $dir[0]=='.') array_shift($dir);

        while ($dir && $file && ($dir[0] == $file[0])) {
            array_shift($dir);
            array_shift($file);
        }
        return str_repeat('..'.'/', count($dir)) . implode('/', $file);
    }
    
    function DoRedirect($path){
        if(!isset($this->List301))
            return;
        foreach($this->List301 as $item){
            if($item['from'] == $path)
                header('Location:index.php?page='.$item['to']);
        }
    }
    
    function SetPagePath($path){
        if ((!file_exists('la_config.md') || is_readable('la_config.md') == false) && 
            (!file_exists('index.md') || is_readable('index.md') == false)){
            $this->InstallLaMDWiki();
        }
        
        $this->GetWebsiteSettings();
        
        $this->DoRedirect($path);
        
        $this->Trackable = $this->TryExtractTaskManager($this->TrackerFile,1);
        
        while($path[0]=='/' || $path[0]=='\\') $path = substr($path,1);
        $len = strlen($path);
        if (is_dir($path)){
            if($path[$len-1] != '/' && $path[$len-1] != '\\') $path = $path.'/';
            $path=$path.'index.md';
        }

        $this->PagePath = $path;
        
        $this->SwitchToTargetLanguageIfPossible();
        
        if((!file_exists($this->PagePath) || is_readable($this->PagePath) == false)
            &&!isset($_GET['operation'])
            &&!isset($_GET['moving'])
            &&!isset($_POST['button_new_passage'])) {
            return false;
        }
        
        $this->StatsFile = preg_replace('/^\.\/(.*)/','$1',$this->StatsFile);
        $path_clean = preg_replace('/^\.\/(.*)/','$1',$this->PagePath);
        if($this->StatsFile == $path_clean){
            $this->IsStatsDisplay = 1;
        }
        
        return true;
    }
    
    function SetEditMode($mode){
        $this->IsEditing = $mode;
    }
    
    function GetEditMode(){
        return $this->IsEditing;
    }
    
    function ConfirmMainPassage(){
        $this->IsMainPassage=1;
    }
    
    //===========================================================================================================
    function ParseMarkdownConfig($Content){
        $Returns = Null;
        $BeginOffset = 0;
        $i = 0;
        
        $Content = preg_replace("/([\S\s]*)```([\S\s]*)```/U", "$1", $Content);
        
        while (preg_match("/([\S\s]*)<!--([^@]*)-->([\S\s]*)<!--([^@]*)-->/U", $Content, $Matches, PREG_OFFSET_CAPTURE)){
            $BlockName = trim($Matches[2][0]);
            
            $Returns[$i]["IsConfig"] = False;
            $Returns[$i]["Content"] = trim($Matches[1][0]);
            $i++;
            
            $Returns[$i]["IsConfig"] = True;
            $Returns[$i]["BlockName"] = $BlockName;
            
            $j = -1; $k = 0; $new=False;
            $lines=explode("\n",trim($Matches[3][0]));
            foreach($lines as $Line){
                if (isset($Line[0]) && $Line[0] == "-"){
                    if ($j<0) continue;
                    else{
                        $Analyze = explode("=",$Line,2);
                        if(count($Analyze)<2) continue;
                        $Returns[$i]["Items"][$j]["Items"][$k]["Argument"] = trim(substr($Analyze[0],1));
                        $Returns[$i]["Items"][$j]["Items"][$k]["Value"] = trim($Analyze[1]);
                        $k++;
                    }
                }else{
                    if(!preg_match("/[\S]+/",$Line)) $new=True; else $new=False;
                    if($new==True) continue;
                    $j++;
                    $k=0;
                    $Analyze = explode("=", $Line,2);
                    if(count($Analyze)<2){
                        $Returns[$i]["Items"][$j]["Name"] = trim($Line);
                        $Returns[$i]["Items"][$j]["Value"] = NULL;
                    }else{
                        $Returns[$i]["Items"][$j]["Name"] = trim($Analyze[0]);
                        $Returns[$i]["Items"][$j]["Value"] = trim($Analyze[1]);
                    }
                }
            }
            
            $i++;
            
            $Content = preg_replace("/([\S\s]*)<!--([^@]*)-->([\S\s]*)<!--([^@]*)-->/U", "", $Content, $limit=1);
        }
        return $Returns;
    }
    
    function WriteMarkdownConfig($Config,$File){
        foreach($Config as $Block){
            if(!isset($Block["IsConfig"])) continue;
            if($Block["IsConfig"]==False){
                fwrite($File,$Block["Content"]);
                fwrite($File,PHP_EOL.PHP_EOL);
            }else{
                fwrite($File,"<!-- ".$Block["BlockName"]." -->".PHP_EOL.PHP_EOL);
                if(isset($Block["Items"])){
                   foreach($Block["Items"] as $Name){
                        fwrite($File,$Name["Name"]);
                        if(isset($Name["Value"])&&$Name["Value"]!='') fwrite($File," = ".$Name["Value"]);
                        fwrite($File,PHP_EOL);
                        if(isset($Name["Items"]))
                            foreach($Name["Items"] as $Argument){
                                fwrite($File,"- ".$Argument["Argument"]." = ".$Argument["Value"].PHP_EOL);
                            }
                        fwrite($File,PHP_EOL);
                    }
                }
                fwrite($File,"<!-- End of ".$Block["BlockName"]." -->".PHP_EOL.PHP_EOL);
            }
        }
    }
    
    function AddBlock(&$Config,$BlockName){
        $i=0;
        while(isset($Config[$i]))$i++;
        $Config[$i]['IsConfig'] = True;
        $Config[$i]['BlockName'] = $BlockName;
        return $i;
    }
    
    function RemoveBlockByName(&$Config,$BlockName){
        $i=0;
        foreach($Config as $Block){
            if(isset($Block["BlockName"]) && $Block["BlockName"]==$BlockName){
                unset($Config[$i]); break;
            }
            $i++;
        }
    }
    
    function RemoveBlock(&$Config,$Block){
        unset($Config[$Block]);
    }
    
    function GetBlock(&$Config,$BlockName){
        $i=0;
        if(!$Config) return Null;
        foreach($Config as $Block){
            if(isset($Block["BlockName"]) && $Block["BlockName"]==$BlockName){
                return $i;
            }
            $i++;
        }
        return Null;
    }
    
    function EditBlock(&$Config,$BlockName){
        if (($block = $this->GetBlock($Config,$BlockName))==Null){
            return $this->AddBlock($Config,$BlockName);
        }else{
            return $block;
        }
    }
    
    function SetBlockName(&$Config,$Block,$BlockName){
        $Config[$Block]["BlockName"] = $BlockName;
    }
    
    function FindGeneralLine(&$Config,$Block,$Name){
        $i=0;
        if(!isset($Config[$Block]['Items']))return Null;
        foreach($Config[$Block]['Items'] as $Line){
            if(isset($Line["Name"]) && $Line["Name"]==$Name){
                return $i;
            }
            $i++;
        }
        return Null;
    }
    
    function ForceLastGeneralLine(){
        $this->force_last_line=1;
    }
    
    function RestoreGeneralLine(){
        $this->force_last_line=0;
    }
    
    function FindGeneralLineN(&$Config,$Block,$Name,$Number){
        $i=0;
        $c=0;
        if(!isset($Config[$Block]['Items']))return Null;
        foreach($Config[$Block]['Items'] as $Line){
            if(isset($Line["Name"]) && $Line["Name"]==$Name){
                if($c==$Number) return $i;
                $c++;
            }
            $i++;
        }
        if ($this->force_last_line) return ($i-1);
        return Null;
    }
    
    function AddGeneralLine(&$Config,$Block,$Name,$Value=''){
        $i=0;
        while(isset($Config[$Block]['Items'][$i]['Name'])) $i++;
        
        $Config[$Block]['Items'][$i]['Name'] = $Name;
        if($Value!='') $Config[$Block]['Items'][$i]['Value'] = $Value;
        return $i;
    }
    
    function RemoveGeneralLine(&$Config,$Block,$Line){
        unset($Config[$Block]['Items'][$Line]);
    }
    
    function RemoveGeneralLineByName(&$Config,$Block,$LineName){
        $Line = $this->FindGeneralLine($Config,$Block,$LineName);
        if ($Line === Null) return;
        $this->RemoveGeneralLine($Config,$Block,$Line);
    }
    
    function RemoveGeneralLineByNameN(&$Config,$Block,$LineName,$Number){
        $Line = $this->FindGeneralLineN($Config,$Block,$LineName,$Number);
        if ($Line === Null) return;
        $this->RemoveGeneralLine($Config,$Block,$Line);
    }
    
    function EditGeneralLineByName(&$Config,$BlockName,$LineName,$Value=''){
        $block = $this->GetBlock($Config,$BlockName);
        $line = $this->FindGeneralLine($Config,$block,$LineName);
        if (isset($line)) {
            if($Value!='') $Config[$block]['Items'][$line]['Value'] = $Value;
            else unset($Config[$block]['Items'][$line]['Value']);
        }else{
            $this->AddGeneralLine($Config,$block,$LineName,$Value);
        }
    }
    
    function EditGeneralLineByNameSelf(&$Config,$BlockName,$LineName,$NewLine){
        $block = $this->GetBlock($Config,$BlockName);
        $line = $this->FindGeneralLine($Config,$block,$LineName);
        if (isset($line)) {
            if($NewLine!='') $Config[$block]['Items'][$line]['Name'] = $NewLine;
            else unset($Config[$block]['Items'][$line]);
        }else{
            $this->AddGeneralLine($Config,$block,$LineName,'');
        }
    }
    
    function EditGeneralLineNByName(&$Config,$BlockName,$LineName,$LineNumber,$Value=''){
        $block = $this->GetBlock($Config,$BlockName);
        $line = $this->FindGeneralLineN($Config,$block,$LineName,$LineNumber);
        if (isset($line)) {
            if($Value!='') $Config[$block]['Items'][$line]['Value'] = $Value;
            else unset($Config[$block]['Items'][$line]['Value']);
        }else{
            $this->AddGeneralLine($Config,$block,$LineName,$Value);
        }
    }
    
    function AddArgument(&$Config,$Block,$Line,$Argument,$Value){
        $i=0;
        while(isset($Config[$Block]['Items'][$Line]['Items'][$i]['Argument'])) $i++;
        
        $Config[$Block]['Items'][$Line]['Items'][$i]['Argument'] = $Argument;
        $Config[$Block]['Items'][$Line]['Items'][$i]['Value'] = $Value;
        return $i;
    }
    
    function RemoveArgument(&$Config,$Block,$Line,$ArgumentID){
        unset($Config[$Block]['Items'][$Line]['Items'][$ArgumentID]);
    }
    
    function FindArgument(&$Config,$Block,$Line,$Argument){
        $i=0;
        if(isset($Config[$Block]['Items'][$Line]['Items'])) foreach($Config[$Block]['Items'][$Line]['Items'] as $Arg){
            if(isset($Arg["Argument"]) && $Arg["Argument"]==$Argument){
                return $i;
            }
            $i++;
        }
        return Null;

    }
    
    function RemoveArgumentByName(&$Config,$Block,$Line,$Argument){
        $Arg = FindArgument($Config,$Block,$Line,$Argument);
        if ($Arg == Null) return;
        RemoveArgument($Config,$Block,$Line,$Arg);
    }
    
    function CheckLine(&$Config,$Block,$Line,$Name,$Value){
        return ($Config[$Block]['Items'][$Line]['Name'] == $Name && 
         $Config[$Block]['Items'][$Line]['Value'] == $Value);
    }
    
    function SetLine(&$Config,$Block,$Line,$Name,$Value){
        $Config[$Block]['Items'][$Line]['Name'] = $Name;
        if (isset($Value) && $Value!='')
        $Config[$Block]['Items'][$Line]['Value'] = $Value;
    }
    
    function CheckLineByNames(&$Config,$BlockName,$LineName,$Value){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLine($Config,$B,$LineName);
        if(isset($L)){
            if(!isset($Config[$B]['Items'][$L]['Value'])) return False;
            else return $this->CheckLine($Config,$B,$L,$LineName,$Value);
        }
    }
    
    function GetLineValueByNames(&$Config,$BlockName,$LineName){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLine($Config,$B,$LineName);
        if(isset($L)) return $Config[$B]['Items'][$L]['Value'];
        return False;
    }
    
    function GetLineValueByNamesN(&$Config,$BlockName,$LineName,$LineNumber){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLineN($Config,$B,$LineName,$LineNumber);
        if(isset($L)) return $Config[$B]['Items'][$L]['Value'];
        return False;
    }
    function GetLineByNamesN(&$Config,$BlockName,$LineName,$LineNumber){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLineN($Config,$B,$LineName,$LineNumber);
        if(isset($L)) return $L;
        return Null;
    }
    
    function RemoveLineByNamesN(&$Config,$BlockName,$LineName,$LineNumber){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $this->RemoveGeneralLineByNameN($Config,$B,$LineName,$LineNumber);
    }
    
    function SetLineValueByNames(&$Config,$BlockName,$LineName,$Value){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLine($Config,$B,$LineName);
        if(isset($L)){
            SetLine($Config,$B,$L,$LineName,$Value);
        }
    }
    
    function SetLineValueByNamesN(&$Config,$BlockName,$LineName,$LineNumber,$Value){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLineN($Config,$B,$LineName,$LineNumber);
        if(isset($L)){
            SetLine($Config,$B,$L,$LineName,$Value);
        }
    }
    
    function CheckArgument(&$Config,$Block,$Line,$ArgumentID,$Value){
        return ($Config[$Block]['Items'][$Line]['Items'][$ArgumentID]['Value'] == $Value);
    }
    
    
    function SetArgument(&$Config,$Block,$Line,$ArgumentID,$Value){
        $Config[$Block]['Items'][$Line]['Items'][$ArgumentID]['Value'] = $Value;
    }
    
    function CheckArgumentByNames(&$Config,$BlockName,$LineName,$ArgumentName,$Value){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLine($Config,$B,$LineName);
        if(isset($L)) $A = $this->FindArgument($Config,$B,$L,$ArgumentName);
        if(isset($A)) return $this->CheckArgument($Config,$B,$L,$A,$Value);
        return Null;
    }
    
    function GetArgumentByNames(&$Config,$BlockName,$LineName,$ArgumentName){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLine($Config,$B,$LineName);
        if(isset($L)) $A = $this->FindArgument($Config,$B,$L,$ArgumentName);
        if(isset($A)) return $Config[$B]['Items'][$L]['Items'][$A]['Value'];
        return Null;
    }
    
    function GetArgumentByNamesN(&$Config,$BlockName,$LineName,$LineNumber,$ArgumentName){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLineN($Config,$B,$LineName,$LineNumber);
        if(isset($L)) $A = $this->FindArgument($Config,$B,$L,$ArgumentName);
        if(isset($A)) return $Config[$B]['Items'][$L]['Items'][$A]['Value'];
        return Null;
    }
    
    function SetArgumentByNames(&$Config,$BlockName,$LineName,$ArgumentName,$Value){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLine($Config,$B,$LineName);
        if(isset($L)) $A = $this->FindArgument($Config,$B,$L,$ArgumentName);
        if(isset($A)) $this->SetArgument($Config,$B,$L,$A,$Value);
    }
    
    function SetArgumentByNamesN(&$Config,$BlockName,$LineName,$LineNumber,$ArgumentName,$Value){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLineN($Config,$B,$LineName,$LineNumber);
        if(isset($L)) $A = $this->FindArgument($Config,$B,$L,$ArgumentName);
        if(isset($A)) $this->SetArgument($Config,$B,$L,$A,$Value);
    }
    
    function EditArgumentByNamesN(&$Config,$BlockName,$LineName,$LineNumber,$ArgumentName,$Value){
        $B = $this->GetBlock($Config,$BlockName);
        if(isset($B)) $L = $this->FindGeneralLineN($Config,$B,$LineName,$LineNumber);
        if(isset($L)) $A = $this->FindArgument($Config,$B,$L,$ArgumentName);
        if(isset($A)) $this->SetArgument($Config,$B,$L,$A,$Value); 
        else $this->AddArgument($Config,$B,$L,$ArgumentName,$Value);
    }
    
    //======================================================================================================
    
    function ScanForTagsInContent($Content){
        if(!$this->IsMainPassage) return;
        $this->AudioList = [];
        preg_match_all("/!@@\[(.*)\]\((.*)\)/U", $Content, $Matches, PREG_SET_ORDER);
        if($Matches){
            foreach($Matches as $m){
                $group['id'] = $m[1];
                $group['src'] = $m[2];
                $this->AudioList[] = $group;
            }
        }
        $this->IsMainPassage=0;
    }
    function LookForKeywordsFromContent($content,&$is_nsfw){
        if(preg_match("/\([nN][sS][fF][wW]\)/",$content,$match,PREG_OFFSET_CAPTURE)) $is_nsfw=True;
    }
    function ContentOfMarkdownFile($FileName){
        if(!isset($FileName)) return Null;
        while($FileName[0]=='/' || $FileName[0]=='\\') $FileName = substr($FileName,1);
        $len = strlen($FileName);
        if (is_dir($FileName)){
            if($FileName[$len-1] != '/' || $FileName[$len-1] != '\\') $FileName = $FileName.'/';
            $FileName=$FileName.'index.md';
        }
        if (!file_exists($FileName) || is_readable($FileName) == false) {
            return Null;
        }
        
        $File = fopen($FileName,'r');
        $length=filesize($FileName);
        $R='';
        if ($length==0) $R="*空文件*";
        else $R = fread($File,$length);
        fclose($File);
        
        $is_nsfw=False;
        $this->LookForKeywordsFromContent($R,$is_nsfw);
        if(!isset($_GET['no_nsfw']) && $is_nsfw && (($this->IsLoggedIn() && !$this->UserIsMature) || !$this->IsLoggedIn())){
            $this_file = pathinfo($FileName,PATHINFO_BASENAME);
            $R=
            "# 请留意\n\n".
            "本文中含有可能不适合在工作或其他正式场合下阅读的内容。\n\n".
            "作者建议仅成年人阅读其中的材料。\n\n".
            "[仍然继续]($this_file&no_nsfw=1)";
            if($this->IsMainPassage) $this->MainFileIsNSFW=True;
            else $this->FileIsNSFW=True;
            return $R;
        }
        
        if($this->IsMainPassage){
            $this->MainFileIsNSFW=False;
        }else{
            $this->FileIsNSFW=False;
        }
        
        return $R;
    }
    
    function ExtractPassageConfig($Content){
        $Conf = $this->ParseMarkdownConfig($Content);
        $this->SceneList=[];
        $i=0;
        
        while($this->GetLineByNamesN($Conf,'3D','Scene',$i)!==Null){
            $scene['file']    = $this->GetArgumentByNamesN($Conf,'3D','Scene',$i,'File');
            $scene['id']      = $this->GetArgumentByNamesN($Conf,'3D','Scene',$i,'ID');
            $scene['mode']    = $this->GetArgumentByNamesN($Conf,'3D','Scene',$i,'Mode');   // Block - Inline - Background (2nd background treat as block)
            $scene['expand']  = $this->GetArgumentByNamesN($Conf,'3D','Scene',$i,'Expand'); // 0 - 1 (default 0)
            $scene['hook']    = $this->GetArgumentByNamesN($Conf,'3D','Scene',$i,'Hook');   // some heading
            $scene['hook_before']  = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'HookBefore');   // 0 - 1 (default 0)
            $scene['padding']      = $this->GetArgumentByNamesN($Conf,'3D','Scene',$i,'Padding');// 0 - 1 (default 1)
            $scene['hang']         = $this->GetArgumentByNamesN($Conf,'3D','Scene',$i,'Hang');   // 0 - 1 (default 0)
            $scene['lock_center']  = $this->GetArgumentByNamesN($Conf,'3D','Scene',$i,'LockCenter');   // 0 - 1 (default 0)
            $scene['paralax']      = $this->GetArgumentByNamesN($Conf,'3D','Scene',$i,'Paralax');      // 0 - 1 (default 0)
            $scene['paralax_size'] = $this->GetArgumentByNamesN($Conf,'3D','Scene',$i,'ParalaxSize');  
            $this->SceneList[] = $scene;
            $i++;
        }
        
        $i=0;
        while($this->GetLineByNamesN($Conf,'2D','Image',$i)!==Null){
            $img['TYPE'] = 'IMAGE';
            $img['file']    = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'File');
            $img['file2']   = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'File2');
            $img['file3']   = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'File3');
            $img['file4']   = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'File4');
            $img['file5']   = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'File5');
            $img['mode']    = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'Mode');   // Block - Background (2nd background treat as block)
            $img['expand']  = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'Expand'); // 0 - 1 (default 0)
            $img['hook']    = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'Hook');   // some heading
            $img['hook_before']  = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'HookBefore');   // 0 - 1 (default 0)
            $img['padding']      = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'Padding');// 0 - 1 (default 1)
            $img['click_zoom']   = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'ClickZoom');// 0 - 1 (default 0)
            $img['max_out']      = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'MaxOut');// 0 - 1 (default 0)
            $img['note']         = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'Note');// Corner flag
            $img['note2']        = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'Note2');
            $img['note3']        = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'Note3');
            $img['note4']        = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'Note4');
            $img['note5']        = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'Note5');
            $img['same_width']   = $this->GetArgumentByNamesN($Conf,'2D','Image',$i,'SameWidth');
            $this->BlockImageList[] = $img;
            $i++;
        }
        while($this->GetLineByNamesN($Conf,'2D','Video',$i)!==Null){
            $img['TYPE'] = 'VIDEO';
            $img['file']    = $this->GetArgumentByNamesN($Conf,'2D','Video',$i,'File');
            $img['mode']    = $this->GetArgumentByNamesN($Conf,'2D','Video',$i,'Mode');   // Block - Background (2nd background treat as block)
            $img['expand']  = $this->GetArgumentByNamesN($Conf,'2D','Video',$i,'Expand'); // 0 - 1 (default 0)
            $img['hook']    = $this->GetArgumentByNamesN($Conf,'2D','Video',$i,'Hook');   // some heading
            $img['hook_before']  = $this->GetArgumentByNamesN($Conf,'2D','Video',$i,'HookBefore');   // 0 - 1 (default 0)
            $img['padding']      = $this->GetArgumentByNamesN($Conf,'2D','Video',$i,'Padding');// 0 - 1 (default 1)
            $img['click_zoom']   = $this->GetArgumentByNamesN($Conf,'2D','Video',$i,'ClickZoom');// 0 - 1 (default 0)
            $img['max_out']      = $this->GetArgumentByNamesN($Conf,'2D','Video',$i,'MaxOut');// 0 - 1 (default 0)
            $img['note']         = $this->GetArgumentByNamesN($Conf,'2D','Video',$i,'Note');// Corner flag
            $this->BlockImageList[] = $img;
            $i++;
        }
        
        $this->AfterPassage2D = $this->GetLineValueByNamesN($Conf,'2D','AfterPassage',0)!=0?1:0;
        $this->AfterPassage3D = $this->GetLineValueByNamesN($Conf,'3D','AfterPassage',0)!=0?1:0;
    }
    
    function ExtractPassageConfigFromFile($FileName){
        $Content = $this->ContentOfMarkdownFile($this->ChooseLanguageMain($FileName));
        if($Content) $this->ExtractPassageConfig($Content);
    }
    
    function RemoveMarkdownConfig($Content){
        $TMP='';
        if(preg_match_all("/([\S\s]*)```([\S\s]*)(```)/U", $Content, $Matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE)){
            foreach($Matches as $m){
                $m[2][0] = preg_replace("/!@@\[(.*)\]\((.*)\)/U", "!@@@[$1]($2)",$m[2][0]);
                $TMP.= $m[1][0]."```".preg_replace("/([\S\s]*)<!--([^@]*)-->([\S\s]*)<!--([^@]*)-->/U", "$1<!--@$2-->$3<!--@$4-->", $m[2][0])."```";
            }
            $TMP.= substr($Content,end($Matches)[3][1]+3);
            $TMP = preg_replace("/([\S\s]*)<!--([^@]*)-->([\S\s]*)<!--([^@]*)-->/U", "$1", $TMP);
        }else{
            $TMP = $Content;
            $TMP = preg_replace("/([\S\s]*)<!--(.*)-->([\S\s]*)<!--(.*)-->/U", "$1", $TMP);
        }
        
        // also make audio tags
        $this->ScanForTagsInContent($TMP);
        $TMP = preg_replace("/!@@\[(.*)\]\((.*)\)/U", 
            '<audio id="AUDIO_$1" class="audio_item"><source src="'.$this->InterlinkPath().'/$2" type="audio/ogg"></audio>
<div class="plain_block inline_block" style="pointer-events:none;">'.$this->FROM_ZH('声音').' $1</div>'
            ,$TMP);

        $TMP = preg_replace("/!@@@\[(.*)\]\((.*)\)/U", "!@@[$1]($2)",$TMP);
        return preg_replace("/([\S\s]*)<!--@(.*)-->([\S\s]*)<!--@(.*)-->/U", "$1<!--$2-->$3<!--$4-->", $TMP);
    }
    
    function ProcessHREFForPrint($HTMLContent){
        $TMP='';
        $number=0;
        $trans = preg_replace_callback("/<a(.*)href=[\'\"](\?page=[^\'\"]*)_(..)\.md[\'\"](.*)>(.*)(<\/a>)/U",
                    function (&$matches){
                        if($matches[3]=='en'||$matches[3]=='zh'){
                            return '<a'.$matches[1].'href="'.$matches[2].'_'.$matches[3].'.md&translation=disabled"'.$matches[4].'>'.$matches[5].'</a>';
                        }
                        return $matches[0];
                    },
                    $HTMLContent);
        if(preg_match_all("/([\S\s]*)<a(.*)href=[\'\"]([^\'\"]*)[\'\"](.*)>(.*)(<\/a>)/U", $trans, $Matches, PREG_SET_ORDER|PREG_OFFSET_CAPTURE)){
            foreach($Matches as $m){
                $number+=1;
                $this->LinkList[] = [$number,$m[3][0]];
                $TMP.= $m[1][0]."<a".$m[2][0].'href="'.$m[3][0].'"'.$m[4][0].'>'.$m[5][0].'<sup class="only_on_print">链'.$number.'</sup>'.'</a>';
            }
            $TMP.= substr($trans,end($Matches)[6][1]);
        }else{
            $TMP = $trans;
        }
        return $TMP;
    }
    
    function MakeHREFListForPrint(){
        if(!isset($this->LinkList[0])) return;
        ?>
        <div class='appendix only_on_print'>
            <hr /> 
            <h2>链接列表</h2>
            <?php
            foreach($this->LinkList as $l){
                $url = preg_replace('/^\?page=/','http://'.$_SERVER['HTTP_HOST'].'/',$l[1]);
                $url = preg_replace('/^index.php\?page=/','http://'.$_SERVER['HTTP_HOST'].'/',$url);
                echo '<p>'.$url.' 链'.$l[0].'</p>';
            }
            ?>
        </div>
        <?php
    }
    
    function ProcessHREFToRemote($HTML){
        $replaced = preg_replace('/^\?page=/','http://'.$_SERVER['HTTP_HOST'].'/',$HTML);
        $replaced = preg_replace('/^index.php\?page=/','http://'.$_SERVER['HTTP_HOST'].'/',$replaced);
        return $replaced;
    }
    
    function HTMLFromMarkdown($Content){
        return $this->PDE->text($this->InsertReplacementSymbols($this->RemoveMarkdownConfig($Content)));
    }
    
    function HTMLFromMarkdownFile($FileName){
        $Content = $this->ContentOfMarkdownFile($FileName);
        if($Content) return $this->HTMLFromMarkdown($Content);
        return "<i>空文件</i>";
    }
    function FirstRow($content){
        $array = explode("\n",$content);
        if (isset($array[0]))
            return $array[0];
        else
            return null;
    }
    function FirstRows($content,$rows){
        $array = null;
        $a = explode("\n",$content);
        $i=0;
        while ($i<$rows){
            if (isset($a[$i]) )
                $array[]=$a[$i];
            $i=$i+1;
        }
        return implode("\n",$array);
    }
    function TitleOfFile($content){
        if(preg_match('/# [ ]*(.*)\n/U',$content,$match,PREG_OFFSET_CAPTURE)){
            return '**'.$match[1][0].'**';
        }
        return $this->FirstRows($content,1);
    }
    
    //============================================================================================================
    
    function SetInterlinkPath($Path){
        $this->PDE->SetInterlinkPath($Path);
    }
    function InterlinkPath(){
        $path = $this->PDE->InterlinkPath();
        if ($path=='') return '.';
        return $path; 
    }
    function GetInterlinkPath($target){
         return $this->PDE->GetInterlinkPath($target);
    }
    function FileNameExists($name){
        if(is_readable($this->InterlinkPath().'/'.$name.'.md')) return True;
        return False;
    }
    function GetUniqueName($original_name){
        $new_name=$original_name;
        $i=0;
        while ($this->FileNameExists($new_name)){
            $new_name=$original_name.'_'.$i;
            $i++;
        }
        return $new_name;
    }
    function GeneralNameExists($name){
        if(is_readable($this->InterlinkPath().'/'.$name)) return True;
        return False;
    }
    function GetUniqueGeneralName($original_name){
        $new_name=$original_name;
        $i=0;
        while ($this->GeneralNameExists($new_name)){
            $new_name=$original_name.'_'.$i;
            $i++;
        }
        return $new_name;
    }
    function GetUniquePath($original_path){
        $new_name=$original_path;
        $i=0;
        $pe = pathinfo($original_path);
        while (is_readable($new_name)){
            $new_name=$pe['dirname'].'/'.$pe['filename'].'_'.$i.'.'.$pe['extension'];
            $i++;
        }
        return $new_name;
    }
    function DeleteDirectory($dir) {
        $dh=opendir($dir);
        while ($file=readdir($dh)) {
            if($file!="." && $file!="..") {
                $fullpath=$dir."/".$file;
                if(!is_dir($fullpath)) {
                    unlink($fullpath);
                } else {
                    DeleteDirectory($fullpath);
                }
            }
        }

        closedir($dh);

        if(rmdir($dir)) {
            return true;
        } else {
            return false;
        }
    }
    function GetPrevNextPassage($this_passage_path){
        $dir = $this->InterlinkPath();
        if(!is_readable($dir)) return;
        $dh=opendir($dir);
        $prev=null;$next=null;
        while ($file=readdir($dh)) {
            if($file!="." && $file!="..") {
                $fullpath=$dir."/".$file;
                if(!is_dir($fullpath)) {
                    $ext=pathinfo($file,PATHINFO_EXTENSION);
                    if($ext!='md') continue;
                    if(!$next && pathinfo($this_passage_path,PATHINFO_BASENAME)!=$file){
                        $prev=$file;
                    }else{
                        $file=readdir($dh);
                        if(!$file) break;
                        $fullpath=$dir."/".$file;
                        $ext=pathinfo($file,PATHINFO_EXTENSION);
                        while (is_dir($fullpath) || $ext!='md'){
                            $file=readdir($dh);
                            if(!$file) break;
                            $fullpath=$dir."/".$file;
                            $ext=pathinfo($file,PATHINFO_EXTENSION);
                        }
                        $next=$file;
                        break;
                    }
                } else {
                    continue;
                }
            }
        }
        $this->PrevFile = $prev;
        $this->NextFile = $next;
    }
    //========================================================================================================
    
    function ActuallPath(){
        return $this->PagePath;
    }
    
    function ChooseLanguageAppendix($file_path, $appendix){
        if(!$file_path) return;
    
        $path_parts = pathinfo($file_path);
        
        $file_orig = $path_parts['dirname'].'/'.preg_replace('/_\D\D\.md$/','.md',$path_parts['basename']);
        $file_orig = preg_replace('/DRAFT\./','.',$file_orig);
        
        $file_en = preg_replace('/\.md$/','_en.md',$file_orig);
        $file_zh = preg_replace('/\.md$/','_zh.md',$file_orig);
        
        $file_orig_draft = preg_replace('/\.md$/','DRAFT.md',$file_orig);
        $file_en_draft = preg_replace('/\.md$/','_enDRAFT.md',$file_orig);
        $file_zh_draft = preg_replace('/\.md$/','_zhDRAFT.md',$file_orig);
        
        $avail_orig_draft = 0;
        $avail_en_draft = 0;
        $avail_zh_draft = 0;
        
        $avail_orig = (file_exists($file_orig) && is_readable($file_orig));
        if(!$avail_orig) $avail_orig_draft = (file_exists($file_orig_draft) && is_readable($file_orig_draft));
        $avail_en = (file_exists($file_en) && is_readable($file_en));
        if(!$avail_en) $avail_en_draft = (file_exists($file_en_draft) && is_readable($file_en_draft));
        $avail_zh = (file_exists($file_zh) && is_readable($file_zh));
        if(!$avail_zh) $avail_zh_draft = (file_exists($file_zh_draft) && is_readable($file_zh_draft));
        
        if(!$avail_orig && $avail_orig_draft){ $avail_orig = 1; $file_orig = $file_orig_draft; }
        if(!$avail_en && $avail_en_draft)    { $avail_en = 1;   $file_en   = $file_en_draft;   }
        if(!$avail_zh && $avail_zh_draft)    { $avail_zh = 1;   $file_zh   = $file_zh_draft;   }
        
        if($appendix=='zh'){
            if ($avail_zh){
                $path_prefer = $file_zh;
            }else if($avail_en && $avail_orig){
                $path_prefer = $file_orig;
            }
        }else if($appendix=='en'){
            if ($avail_en){
                $path_prefer = $file_en;
            }else if($avail_zh && $avail_orig){
                $path_prefer = $file_orig;
            }
        }
        if(isset($path_prefer))
            return $path_prefer;
        
        return $file_orig;
    }
    
    function ChooseLanguage($file_path){
        if(!isset($this->LanguageAppendix)) return $file_path;
        
        return $this->ChooseLanguageAppendix($file_path,$this->LanguageAppendix);
    }
    
    function ChooseLanguageMain($file_path){
        if(isset($_GET['translation'])&&$_GET['translation']=='disabled') return $file_path;
        
        return $this->ChooseLanguageAppendix($file_path,$this->LanguageAppendix);
    }
    
    function SwitchToTargetLanguageIfPossible(){        
        if(isset($_COOKIE['la_language'])){
            $this->LanguageAppendix = $_COOKIE['la_language'];
        }else{
            if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
                $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
                $lang = substr($lang,0,5);
                if(preg_match("/zh/i",$lang))$this->LanguageAppendix = 'zh';
                else $this->LanguageAppendix = 'en';
            }
        }
        
        if((isset($_GET['operation']) && $_GET['operation']!='settings')||isset($_GET['moving'])||isset($_POST['button_new_passage'])) return;
        
        $this->PagePath = $this->ChooseLanguageMain($this->PagePath);
    }
    
    function ProcessHTMLLanguageForLinks($html_content){
        return preg_replace_callback('/<a([\s\S]*)href=[\'\"]?page=([\s\S]*)[\'\"]([\s\S]*)>([\s\S]*)<\/a>/U',
                                     function (&$matches) {
                                         return '<a'.$matches[1].'href="?page='.$this->ChooseLanguage($matches[2]).'"'.$matches[3].'>'.$matches[4].'</a>';
                                     },
                                     $html_content);
    }
    
    function DoLogin(){
        session_start();
        $error_msg = "";
        //登出
        if(isset($_GET['logout'])){
            //要清除会话变量，将$_SESSION超级全局变量设置为一个空数组
            unset($_SESSION['user_id']);
            unset($_SESSION['la_theme']);
            //如果存在一个会话cookie，通过将到期时间设置为之前1个小时从而将其删除
            if(isset($_COOKIE[session_name()])){
                setcookie(session_name(),'',time()-3600);
            }
            //使用内置session_destroy()函数调用撤销会话
            session_destroy();
        }
        //登录
        else if(!isset($_SESSION['user_id'])){
            if(isset($_POST['button_login'])){//用户提交登录表单时执行如下代码
                $UserName = trim($_POST['username']);
                $Password = trim($_POST['password']);
                
                $this->UserConfig = fopen("la_config.md",'r');
                $ConfContent = fread($this->UserConfig,filesize("la_config.md"));
                $Conf = $this->ParseMarkdownConfig($ConfContent);
                
                if(!empty($UserName)&&!empty($Password)){
                    if($this->CheckArgumentByNames($Conf, "Users",$UserName,"Password",$Password)){
                        $_SESSION['user_id']=$UserName;
                        header('Location:index.php?page='.$this->PagePath);
                        exit;
                    }else{//若查到的记录不对，则设置错误信息
                        $error_msg = '你怕是没输对哦';
                    }
                }else{
                    $error_msg = '你怕是没输对哦';
                }
                
                fclose($this->UserConfig);
            }
        }else{
        
            $this->UserConfig = fopen("la_config.md",'r');
            $ConfContent = fread($this->UserConfig,filesize("la_config.md"));
            $Conf = $this->ParseMarkdownConfig($ConfContent);
            $this->UserDisplayName = $this->GetArgumentByNames($Conf, "Users",$_SESSION['user_id'],'DisplayName');
            $this->UserIsMature = $this->GetArgumentByNames($Conf, "Users",$_SESSION['user_id'],'Mature');
            $this->UserID = $_SESSION['user_id'];
            
            fclose($this->UserConfig);
        }
    }
    function IsLoggedIn(){
        return isset($_SESSION['user_id']);
    }
    function DoSetTranslation(){
        if(isset($_GET['set_translation'])){
            setcookie('la_language',$_GET['set_translation']);
            $_COOKIE['la_language'] = $_GET['set_translation'];
        }
    }
    function DoHandleUpload(){
        if(!isset($_FILES['upload_file_name'])) return;
        if($_FILES['upload_file_name']['error']>0){
            return -1;
        }else{
            move_uploaded_file($_FILES['upload_file_name']['tmp_name'], $this->InterlinkPath().'/'.$_FILES['upload_file_name']['name']);
            return 1;
        }
        return 0;
    }
    function MarkPassageUpdate($path,$updated){
        $path_clean = preg_replace('/^\.\/(.*)/','$1',$path);
    
        $this->UserConfig = fopen("la_config.md",'r');
        $ConfContent = fread($this->UserConfig,filesize("la_config.md"));
        fclose($this->UserConfig);
        $Conf = $this->ParseMarkdownConfig($ConfContent);
        
        $this->EditBlock($Conf,"RecentUpdates");
        
        if($updated){
            $i=0;
            while(($entry = $this->GetLineByNamesN($Conf,'RecentUpdates','Entry',$i))!==Null && $this->GetArgumentByNamesN($Conf,'RecentUpdates','Entry',$i,"Path")!=$path_clean){
                $i++;
            }            
            $this->EditGeneralLineNByName($Conf,'RecentUpdates','Entry',$i,'');
            $this->EditArgumentByNamesN($Conf,'RecentUpdates','Entry',$i,'Path',$path_clean);
            $this->EditArgumentByNamesN($Conf,'RecentUpdates','Entry',$i,'Time',$this->CurrentTimeReadable());
        }else{
            $i=0;
            $a=Null;
            while(($a = $this->GetArgumentByNamesN($Conf,'RecentUpdates','Entry',$i,'Path'))){
                if($a!=$path_clean){
                    $i++;
                    continue;
                }
                if($a!==Null){
                    $this->RemoveLineByNamesN($Conf,'RecentUpdates','Entry',$i);
                }
            }
        }
        
        $this->UserConfig = fopen("la_config.md",'w');
        $this->WriteMarkdownConfig($Conf,$this->UserConfig);
        fclose($this->UserConfig);
    }
    function DoMarkPassageUpdate(){
        if(isset($_GET['mark_update'])){
            if($_GET['mark_update'] != 0){
                $this->MarkPassageUpdate($this->PagePath, 1);
            }else{
                $this->MarkPassageUpdate($this->PagePath, 0);
            }
            header('Location:?page='.$this->PagePath);
        }
    }
    function IsPathUpdated($path){
        $path_clean = preg_replace('/^\.\/(.*)/','$1',$this->ChooseLanguage($path));
        $is_folder_index = preg_match('/(.*)\/index(_zh|_en)?.md$/', $path_clean, $folder);
        if(isset($this->RecentUpdatedList)) foreach($this->RecentUpdatedList as $item){
            if($item['path'] == $path_clean){
                if(!$this->IsLoggedIn()){
                    if(preg_match('%DRAFT.md%', $path_clean)){
                        continue;
                    }
                }
                return True;
            }
            if($is_folder_index){
                if(preg_match('%^'.$folder[1].'%',$item['path'])){
                    if(!$this->IsLoggedIn()){
                        if(preg_match('%DRAFT.md%',$item['path'])){
                            continue;
                        }
                    }
                    return true;
                }
            }
        }
        return False;
    }
    function PathUpdatedLevel($path){
        $path_clean = preg_replace('/^\.\/(.*)/','$1',$this->ChooseLanguage($path));
        $is_folder_index = preg_match('/(.*)\/index(_zh|_en)?.md$/', $path_clean, $folder);
        $folder_max = 100;
        if(isset($this->RecentUpdatedList)) foreach($this->RecentUpdatedList as $item){
            if($item['path'] == $path_clean || $is_folder_index){
                if(!$this->IsLoggedIn()){
                    if(preg_match('%DRAFT.md%',$item['path'])){
                        continue;
                    }
                }
                $time = $this->CurrentTimeReadable();
                $days_diff = $this->ReadableTimeDifference($item['time'], $time)/3600/24;
                if($is_folder_index){
                    if(preg_match('%^'.$folder[1].'%',$item['path'])){
                        if($days_diff<1) $folder_max = min($folder_max,1);
                        else if($days_diff<3) $folder_max = min($folder_max,2);
                    }
                }else{
                    if($item['path'] == $path_clean){
                        if($days_diff<1) return 1;
                        if($days_diff<3) return 2;
                    }
                }
            }
        }
        if($folder_max > 50) return 0;
        return $folder_max;
    }
    function DoUpdateStatsFile(){
        $stats_file = $this->StatsFile;
        if(!file_exists($stats_file) || !is_readable($stats_file)) return;
        
        $f = file_get_contents($stats_file);
        
        $today_date = date("Y-m-d");
        $updates = "[".$this->CountTodayUpdates()." Updates]";
        $new_stats = $today_date.": ".$updates.PHP_EOL.PHP_EOL;
        
        preg_match_all("/([0-9]{4}-[0-9]{2}-[0-9]{2}):[\s]*(.*)\R\R/Uu", $f, $matches, PREG_SET_ORDER); 
        
        $fi = fopen($stats_file,'w');
        $found = 0;
        
        if(isset($matches[0])) foreach($matches as $match){
            if($today_date == $match[1]){
                $match[2] = preg_replace("/\[[\s]*[0-9]*[\s]*Updates[\s]*\]/", $updates, $match[2]);
                $found = 1;
            }
            fwrite($fi, $match[1].": ".$match[2].PHP_EOL.PHP_EOL);
        }
        
        if(!$found){
            fwrite($fi, $new_stats);
        }
        
        fclose($fi);
    }
    function DoNewPassage(){
        if(isset($_POST['button_new_passage'])){
            $passage = $_POST['data_passage_content'];
            $file_path = $this->PagePath;

            if(isset($_POST['editor_file_name'])){  //new passage
                $file_name = $this->GetUniqueName($_POST['editor_file_name']);
                $file_path = (isset($_GET['quick'])?$_GET['quick']:$this->InterlinkPath()).'/'.$file_name.'.md';
                $file_path = $this->GetUniquePath($file_path);
            }
            
            if((isset($_GET['delete_on_empty'])&&$_GET['delete_on_empty']==true) && $passage==''){
                if(file_exists($file_path)) unlink($file_path);
                $this->MarkPassageUpdate($file_path, 0);
            }else{
                $file = fopen($file_path, "w");
                fwrite($file,$passage);
                fclose($file);
                $this->MarkPassageUpdate($file_path, 1);
                $this->DoUpdateStatsFile();
            }

            header('Location:?page='.(isset($_GET['return_to'])?$_GET['return_to']:((isset($_GET['quick'])?$this->PagePath:$file_path))).'&translation=disabled');
            exit;
        }
    }
    function DoNewSideNote(){
        if(isset($_POST['sn_confirm'])){
            $content = $_POST['data_sidenote_content'];
            $file_path = $_GET['page'];

            $file = fopen($file_path, "w");

            header('Location:?page='.(isset($_GET['quick'])?$this->PagePath:$file_path).'&translation=disabled');
            exit;
        }
    }
    function DoNewSmallQuote(){
        if(isset($_POST['button_new_quote'])){
            $passage = $_POST['data_small_quote_content'];
            if($passage == $this->FROM_ZH("小声哔哔…"))
                return;
            $file_path = $this->PagePath;
            if(!isset($_GET['quote_quick'])) return;
            $folder = $_GET['quote_quick'];
            $this->AddSmallQuoteEntry($folder,$passage);
            header('Location:?page='.$this->PagePath);
            exit;
        }
    }
    function SendConfirmationMail($title, $folder, $mail_address, $id, $lang){
        if($id!=NULL){
            $confirm_link = "http://".$_SERVER['HTTP_HOST']."/?page=index.md&confirm_target=".$folder."&confirm_address=".$mail_address."&confirm_id=".$id;
            $content_zh = $this->HTMLFromMarkdown(
                          "# 订阅确认信".PHP_EOL.PHP_EOL.
                          "我们收到了您在".$this->StringTitle."网（".$_SERVER['HTTP_HOST']."）上订阅“".$title."”栏目新闻稿的申请。".PHP_EOL.PHP_EOL.
                          "[点击这里以确认订阅申请](".$confirm_link.")或在浏览器中访问下面的连接。".PHP_EOL.PHP_EOL.$confirm_link.PHP_EOL.PHP_EOL.
                          "若这不是您的操作，请忽略这封邮件。");
            if($this->SendMail([$mail_address], $this->FROM_ZH("确认订阅").$title.$this->FROM_ZH("吗？"), $content_zh, NULL, NULL, NULL, NULL)){
                return 1;
            }else{
                return -2;
            }
        }else{
            return -1;
        }
    }
    function DoSendNewsletter(){
        if(isset($_GET['send_newsletter']) && $_GET['send_newsletter']=='run'){
            if($this->IsNewsletterFolder($_GET['folder'])){
                $this->ReadSubscribers($_GET['folder']);
                $content_en = $this->ProcessHREFToRemote(
                           $this->HTMLFromMarkdown(
                           $this->HTMLFromMarkdownFile(
                           $this->ChooseLanguageAppendix(
                           $this->PagePath,"en"))));
                $content_zh = $this->ProcessHREFToRemote(
                           $this->HTMLFromMarkdown(
                           $this->HTMLFromMarkdownFile(
                           $this->ChooseLanguageAppendix(
                           $this->PagePath,"zh"))));
                if(isset($this->MailSubscribers[0])) foreach($this->MailSubscribers as $people){
                    $appendix_en = "<hr>".$this->MailFootEN.($this->MailFootEN!=''?"<br />":"").
                                   $this->TitleEN." Newsletter | <a href='http://".$_SERVER['HTTP_HOST']."/?page=index.md'>Home</a><br />".date("Y-m-d").
                                   "<br /><a href='http://".$_SERVER['HTTP_HOST']."/?page=index.md&configure_address=".$people['address']."&folder=".$_GET['folder']."&mail_address=".$people['address']."&set_tranlstion=en'>Configure newsletter</a>";
                    $appendix_zh = "<hr>".$this->MailFoot.($this->MailFoot!=''?"<br />":"").
                                   $this->Title." 新闻稿 | <a href='http://".$_SERVER['HTTP_HOST']."/?page=index.md'>首页</a><br />".date("Y-m-d").
                                   "<br /><a href='http://".$_SERVER['HTTP_HOST']."/?page=index.md&configure_address=".$people['address']."&folder=".$_GET['folder']."&mail_address=".$people['address']."&set_tranlstion=zh'>设置新闻稿</a>";
                    $this->SendMail([$people['address']], $people['language']=='zh'?$this->MailTitle:$this->MailTitleEN, $people['language']=='zh'?($content_zh.$appendix_zh):($content_en.$appendix_en), NULL, NULL, NULL, NULL);
                }
                return 1;
            }
        }
        return 0;
    }
    function DoEditSubscriber(){
        if(isset($_GET['configure_address'])){
            $this->SetSubscriberCustomizationInfo(NULL, $_GET['folder'], $_GET['mail_address'], 0, NULL);
            return 2;
        }
        if(isset($_GET['resend_email']) && $_GET['resend_email']='true'){
            if($this->SendConfirmationMail($_GET['title'], $_GET['folder'], $_GET['mail_address'], $_GET['id'], $_GET['subscribe_language'])>0){
                return 1;
            }
        }
        if(isset($_GET['unsubscribe']) && $_GET['unsubscribe']='true'){
            $this->ConfirmSubscriber($_GET['folder'],$_GET['mail_address'], NULL, 1);
            return 1;
        }
        if(isset($_GET['select_subscriber_langguage']) && $_GET['select_subscriber_langguage']='true'){
            $this->EditSubscriberLanguage($_GET['folder'],$_GET['mail_address'], $_GET['subscribe_language']);
            return 1;
        }
        return 0;
    }
    function DoNewSubscriber(){
        if(isset($_GET['confirm_id']) && isset($_GET['confirm_address']) && isset($_GET['confirm_target'])){
            if($this->ConfirmSubscriber($_GET['confirm_target'], $_GET['confirm_address'], $_GET['confirm_id'], 0)){
                return -3;
            }
        }
        if(isset($_POST['button_new_subscriber'])){
            $source = $_POST['data_subscriber_content'];
            $mail_address = strtolower(trim($source));
            $file_path = $this->PagePath;
            if(!isset($_GET['subscribe_quick'])) return 0;
            $folder = $_GET['subscribe_quick'];
            $title = $_GET['title'];
            $lang = $_GET['subscribe_language'];
            $id = $this->AddSubscriberEntry($folder, $mail_address, $lang);
            
            $this->SetSubscriberCustomizationInfo($title, $folder, $mail_address, $id, $lang);
            
            return $this->SendConfirmationMail($title, $folder, $mail_address, $id, $lang);
        }
        return 0;
    }
    function DoNewFolder(){
        if(isset($_POST['button_new_folder'])){
            if(isset($_POST['new_folder_name'])){
                $file_name = $this->GetUniqueGeneralName($_POST['new_folder_name']);
                $file_path = $this->InterlinkPath().'/'.$file_name;
                mkdir($file_path);
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }
            exit;
        }
    }
    function DoRenameFolder(){
        if(isset($_POST['button_rename_folder'])){
            if(isset($_POST['rename_folder_name']) && isset($_GET['target'])){
                $original_path = $this->InterlinkPath().'/'.$_GET['target'];
                $file_path = $this->InterlinkPath().'/'.$_POST['rename_folder_name'];
                rename($original_path,$file_path);
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
                exit;
            }
        }
    }
    function DoDeleteFolder(){
        if(isset($_GET['operation']) && $_GET['operation']=='delete_folder'){
            $target = $_GET['target'];
            if(isset($target)){
                $this->DeleteDirectory($this->InterlinkPath().'/'.$target);
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }
            exit;
        }
    }
    function DoRenameFile(){
        if(isset($_POST['button_rename_passage'])){
            if(isset($_POST['rename_passage_name']) && isset($_GET['target'])){
                $original_path = $this->InterlinkPath().'/'.$_GET['target'];
                $file_path = $this->InterlinkPath().'/'.$_POST['rename_passage_name'].'.md';
                rename($original_path,$file_path);
                $this->MarkPassageUpdate($original_path, 0);
                $this->MarkPassageUpdate($file_path, 1);
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
                exit;
            }
        }
        if(isset($_GET['set_draft'])){
            $original_path = $this->PagePath;
            if($_GET['set_draft']==0){
                $target_path = preg_replace("/DRAFT/",'',$original_path);
                rename($original_path,$target_path);
                $this->MarkPassageUpdate($original_path, 0);
                $this->MarkPassageUpdate($target_path, 1);
            }else{
                $target_path = preg_replace("/.md/",'DRAFT.md',$original_path);
                rename($original_path,$target_path);
                $this->MarkPassageUpdate($original_path, 0);
                $this->MarkPassageUpdate($target_path, 1);
            }
            header('Location:?page='.$target_path."&translation=disabled");
            exit;
        }
    }
    
    function DoDeleteFile(){
        if(isset($_GET['operation']) && $_GET['operation']=='delete_file'){
            $target = $_GET['target'];
            if(isset($target)){
                $fullpath=$this->InterlinkPath().'/'.$target;
                if(!is_dir($fullpath)) {
                    unlink($fullpath);
                }
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }
            exit;
        }
    }
    function FolderDisplayAs($path){
        $file = $path.'/la_config.md';
        if(is_readable($file) && filesize($file)!=0){
            $ConfRead = fopen($file,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($file)));
            fclose($ConfRead);
            if($this->CheckLineByNames($Config,'FolderConf','Display','Timeline')) return 'Timeline';
            else return 'Normal';
        }else return False;
    }
    function FolderNovelMode($path){
        $file = $path.'/la_config.md';
        if(is_readable($file) && filesize($file)!=0){
            $ConfRead = fopen($file,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($file)));
            fclose($ConfRead);
            if($this->CheckLineByNames($Config,'FolderConf','Layout','1')) return True;
            else return False;
        }else return False;
    }
    function FolderShowListButton($path){
        $file = $path.'/la_config.md';
        if(is_readable($file) && filesize($file)!=0){
            $ConfRead = fopen($file,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($file)));
            fclose($ConfRead);
            if($this->CheckLineByNames($Config,'FolderConf','ShowListButton','1')) return True;
            else return False;
        }else return False;
    }
    
    function SetFolderPermission($path,$visible){
        if(!isset($path) || $path == '.' || $path == '') return;
        
        $path = preg_replace("%\.\/(.*)%","$1",$path);
        
        $file = 'la_config.md';
        if(is_readable($file)){
            $ConfRead = fopen($file,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($file)));
            fclose($ConfRead);
            $Block = $this->EditBlock($Config,'HiddenFolders');
            if(!$visible){
                $this->EditGeneralLineByName($Config,'HiddenFolders', $path, "");
            }else{
                $this->RemoveGeneralLineByName($Config, $Block, $path);
            }
            $ConfWrite = fopen($file,'w');
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }else if(!$visible){
            $ConfWrite = fopen($file,'w');
            $Config = [];
            $Block = $this->EditBlock($Config,'HiddenFolders');
            $this->EditGeneralLineByName($Config,'HiddenFolders', $path, "");
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }
    }
    function SetFolderDisplay($path,$display){
        $file = $path.'/la_config.md';
        if(is_readable($file)){
            $ConfRead = fopen($file,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($file)));
            fclose($ConfRead);
            $Block = $this->GetBlock($Config,'FolderConf');
            if(!isset($Block)) $this->AddBlock($Config,'FolderConf');
            $this->EditGeneralLineByName($Config,'FolderConf','Display',$display);
            $ConfWrite = fopen($file,'w');
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }else{
            $ConfWrite = fopen($file,'w');
            $Config = [];
            $this->AddBlock($Config,'FolderConf');
            $this->EditGeneralLineByName($Config,'FolderConf','Display',$display);
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }
    }
    function SetFolderLayout($path,$layout){
        $file = $path.'/la_config.md';
        if(is_readable($file)){
            $ConfRead = fopen($file,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($file)));
            fclose($ConfRead);
            $Block = $this->GetBlock($Config,'FolderConf');
            if(!isset($Block)) $this->AddBlock($Config,'FolderConf');
            $this->EditGeneralLineByName($Config,'FolderConf','Layout',$layout);
            $ConfWrite = fopen($file,'w');
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }else{
            $ConfWrite = fopen($file,'w');
            $Config = [];
            $this->AddBlock($Config,'FolderConf');
            $this->EditGeneralLineByName($Config,'FolderConf','Layout',$layout);
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }
    }
    function SetFolderListButton($path,$enabled){
        $file = $path.'/la_config.md';
        if(is_readable($file)){
            $ConfRead = fopen($file,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($file)));
            fclose($ConfRead);
            $Block = $this->GetBlock($Config,'FolderConf');
            if(!isset($Block)) $this->AddBlock($Config,'FolderConf');
            $this->EditGeneralLineByName($Config,'FolderConf','ShowListButton',$enabled);
            $ConfWrite = fopen($file,'w');
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }else{
            $ConfWrite = fopen($file,'w');
            $Config = [];
            $this->AddBlock($Config,'FolderConf');
            $this->EditGeneralLineByName($Config,'FolderConf','ShowListButton',$enabled);
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }
    }
    
    function DoChangePermission(){
        if(isset($_GET['operation'])){
            if($_GET['operation']=='set_permission_off'){
                $this->SetFolderPermission($this->InterlinkPath(),False);
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }else if($_GET['operation']=='set_permission_on'){
                $this->SetFolderPermission($this->InterlinkPath(),True);
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }
        }
    }
    function DoChangeFolderDisplay(){
        if(isset($_GET['operation'])){
            if($_GET['operation']=='set_display_timeline'){
                $this->SetFolderDisplay($this->InterlinkPath(),'Timeline');
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }else if($_GET['operation']=='set_display_normal'){
                $this->SetFolderDisplay($this->InterlinkPath(),'Normal');
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }else if($_GET['operation']=='set_list_button_1'){
                $this->SetFolderListButton($this->InterlinkPath(),'1');
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }else if($_GET['operation']=='set_list_button_0'){
                $this->SetFolderListButton($this->InterlinkPath(),'0');
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }
        }
    }
    function DoChangeFolderLayout(){
        if(isset($_GET['operation'])){
            if($_GET['operation']=='set_layout_0'){
                $this->SetFolderLayout($this->InterlinkPath(),'0');
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }else if($_GET['operation']=='set_layout_1'){
                $this->SetFolderLayout($this->InterlinkPath(),'1');
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }else if($_GET['operation']=='set_wide_0'){
                $this->SetFolderWide($this->InterlinkPath(),'0');
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }else if($_GET['operation']=='set_wide_1'){
                $this->SetFolderWide($this->InterlinkPath(),'1');
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
            }
        }
    }
    function DoMoveFile(){
        if(isset($_GET['moving']) && isset($_GET['to'])){
            $target = $_GET['to'].'/'.pathinfo($_GET['moving'], PATHINFO_BASENAME);
            $target = $this->GetUniquePath($target);
            rename($_GET['moving'], $target);
            header('Location:?page='.$_GET['to'].'&operation=list');
        }
    }
    function DoAddRedirect(){
        if(isset($_POST['settings_button_add_redirect'])){
            if(isset($_POST['redirect_from'])&&isset($_POST['redirect_to'])){
                $this->UserConfig = fopen("la_config.md",'r');
                $ConfContent = fread($this->UserConfig,filesize("la_config.md"));
                $Conf = $this->ParseMarkdownConfig($ConfContent);
                $this->EditBlock($Conf,'Redirect');
            
                $this->EditGeneralLineByName($Conf,'Redirect','Entry','');
                $this->ForceLastGeneralLine();
                $this->EditArgumentByNamesN($Conf,'Redirect','Entry',100000,'From',$_POST['redirect_from']);
                $this->EditArgumentByNamesN($Conf,'Redirect','Entry',100000,'To',$_POST['redirect_to']);
                $this->RestoreGeneralLine();
                
                fclose($this->UserConfig);
                $this->UserConfig = fopen("la_config.md",'w');
                $this->WriteMarkdownConfig($Conf,$this->UserConfig);
                fclose($this->UserConfig);
                
                header('Location:?page='.$this->PagePath.'&operation=settings');
            }
        }
    }
    function DoApplySettings(){
        if(isset($_POST['settings_button_confirm'])){
            
            $admin_changed=false;
            
            $this->UserConfig = fopen("la_config.md",'r');
            $ConfContent = fread($this->UserConfig,filesize("la_config.md"));
            $Conf = $this->ParseMarkdownConfig($ConfContent);
            $this->EditBlock($Conf,'Website');
            $this->EditBlock($Conf,'Users');
            
            if(isset($_POST['settings_website_title'])){
                $this->EditGeneralLineByName($Conf,'Website','Title',$_POST['settings_website_title']);
            }
            if(isset($_POST['settings_website_title_en'])){
                $this->EditGeneralLineByName($Conf,'Website','TitleEN',$_POST['settings_website_title_en']);
            }
            if(isset($_POST['settings_website_display_title'])){
                $this->EditGeneralLineByName($Conf,'Website','DisplayTitle',$_POST['settings_website_display_title']);
            }
            if(isset($_POST['settings_website_display_title_en'])){
                $this->EditGeneralLineByName($Conf,'Website','DisplayTitleEN',$_POST['settings_website_display_title_en']);
            }
            if(isset($_POST['settings_footer_notes'])){
                $this->EditGeneralLineByName($Conf,'Website','Footnote',$_POST['settings_footer_notes']);
            }
            if(isset($_POST['settings_footer_notes_en'])){
                $this->EditGeneralLineByName($Conf,'Website','FootnoteEN',$_POST['settings_footer_notes_en']);
            }
            if(isset($_POST['settings_small_quote_name'])){
                $this->EditGeneralLineByName($Conf,'Website','SmallQuoteName',$_POST['settings_small_quote_name']);
            }
            if(isset($_POST['settings_tracker_file'])){
                $this->EditGeneralLineByName($Conf,'Website','TrackerFile',$_POST['settings_tracker_file']);
            }
            if(isset($_POST['settings_stats_file'])){
                $this->EditGeneralLineByName($Conf,'Website','StatsFile',$_POST['settings_stats_file']);
            }
            if(isset($_POST['settings_mail_host'])){
                $this->EditGeneralLineByName($Conf,'Website','MailHost',$_POST['settings_mail_host']);
            }
            if(isset($_POST['settings_mail_port'])){
                $this->EditGeneralLineByName($Conf,'Website','MailPort',$_POST['settings_mail_port']);
            }
            if(isset($_POST['settings_mail_user'])){
                $this->EditGeneralLineByName($Conf,'Website','MailUser',$_POST['settings_mail_user']);
            }
            if(isset($_POST['settings_mail_password'])){
                $this->EditGeneralLineByName($Conf,'Website','MailPassword',$_POST['settings_mail_password']);
            }
            if(isset($_POST['settings_mail_title'])){
                $this->EditGeneralLineByName($Conf,'Website','MailTitle',$_POST['settings_mail_title']);
            }
            if(isset($_POST['settings_mail_title_en'])){
                $this->EditGeneralLineByName($Conf,'Website','MailTitleEN',$_POST['settings_mail_title_en']);
            }
            if(isset($_POST['settings_mail_foot'])){
                $this->EditGeneralLineByName($Conf,'Website','MailFoot',$_POST['settings_mail_foot']);
            }
            if(isset($_POST['settings_mail_foot_en'])){
                $this->EditGeneralLineByName($Conf,'Website','MailFootEN',$_POST['settings_mail_foot_en']);
            }
            if(isset($_POST['settings_task_highlight_invert'])){
                $this->EditGeneralLineByName($Conf,'Website','TaskHighlightInvert',$_POST['settings_task_highlight_invert']);
            }
            if(isset($_POST['settings_admin_display']) && $_POST['settings_admin_display']!=''){
                $this->EditArgumentByNamesN($Conf,'Users',$this->UserID,0,'DisplayName',$_POST['settings_admin_display']);
            }
            if(isset($_POST['settings_admin_password']) && $_POST['settings_admin_password']!=''){
                $this->EditArgumentByNamesN($Conf,'Users',$this->UserID,0,'Password',$_POST['settings_admin_password']);
                $admin_changed=true;
            }
            if(isset($_POST['settings_admin_id']) && $_POST['settings_adDoLoginmin_id']!=''){
                $this->EditGeneralLineByNameSelf($Conf,'Users',$this->UserID,$_POST['settings_admin_id']);
                $admin_changed=true;
            }
            
            fclose($this->UserConfig);
            $this->UserConfig = fopen("la_config.md",'w');
            $this->WriteMarkdownConfig($Conf,$this->UserConfig);
            fclose($this->UserConfig);
            
            if($admin_changed){
                header('Location:?page=index.md&logout=true');
            }else{
                header('Location:?page='.$this->PagePath.'&operation=settings');
            }
            exit;
        }
    }
    function DoEditTask(){
        if(isset($_GET['operation'])){
            if($_GET['operation'] == "edit_task"){
                if( isset($_POST['task_editor_confirm'])
                    && isset($_POST['task_editor_content']) && isset($_POST['task_editor_tags'])
                    && isset($_GET['target']) && isset($_GET['id'])){
                    if($_POST['task_editor_content']=="<?php echo $this->FROM_ZH('事件描述') ?>" || $_POST['task_editor_content']=="") return;
                    if($_POST['task_editor_tags'] == "<?php echo $this->FROM_ZH('标签') ?>") $_POST['task_editor_tags']="";
                    if(intval($_GET['id'])<0) 
                        $this->EditTask($_GET['target'], $_GET['id'], $_POST['task_editor_content'], $_POST['task_editor_tags'], "T", 0, 0);
                    else
                        $this->EditTask($_GET['target'], $_GET['id'], $_POST['task_editor_content'], $_POST['task_editor_tags'], NULL, 1, 0);
                    header('Location:?page='.$this->PagePath);
                    exit;
                }
            }else if($_GET['operation'] == "set_task"){
                if(isset($_GET['state']) && isset($_GET['target']) && isset($_GET['id'])){
                    $this->EditTask($_GET['target'], $_GET['id'], NULL, NULL, $_GET['state'], 1, 0);
                    header('Location:?page='.$this->PagePath);
                    exit;
                }
            }else if($_GET['operation'] == "delete_task"){
                if(isset($_GET['target']) && isset($_GET['id'])){
                    $this->EditTask($_GET['target'], $_GET['id'], NULL, NULL, NULL, 1, 1);
                    header('Location:?page='.$this->PagePath);
                    exit;
                }
            }else if($_GET['operation'] == "task_new_index"){
                if(isset($_GET['for'])){
                    $path = $_GET['for'];
                    $f = $path.'/index.md';
                    if(is_readable($f)) return;
                    if(is_dir($path) && is_writeable($path)){
                        $fi = fopen($f,"w");
                        fwrite($fi, "本目录事件索引".PHP_EOL.PHP_EOL);
                        fwrite($fi, "<!-- EventTracker -->".PHP_EOL.PHP_EOL);
                        fwrite($fi, "GroupName = 新的事件组".PHP_EOL.PHP_EOL);
                        fwrite($fi, "<!-- end of EventTracker -->".PHP_EOL.PHP_EOL);
                        fflush($fi);
                        fclose($fi);
                        header('Location:?page='.$f);
                        exit;
                    }
                }
            }
        }
    }
    
    function rrmdir($dir) {
        foreach(glob($dir . '/*') as $file) {
            if(is_dir($file))
                rrmdir($file);
            else
                unlink($file);
        }
        rmdir($dir);
    }
    
    function ReadableTimeDifference($time_from, $time_to){
        $from = new DateTime($time_from);
        $to = new DateTime($time_to);
        return $to->getTimeStamp() - $from->getTimeStamp();
    }
    
    function IsDatedEntry($item, $days){
        $time = $this->CurrentTimeReadable();
        $days_diff = $this->ReadableTimeDifference($item['time'], $time)/3600/24;
        if($days_diff>$days) return True;
        return False;
    }
    function IsFromToday($time_from){
        $from = new DateTime($time_from);
        $day = $from->format("d");
        $today = date("d");
        return ($day == $today);
    }
    function ProcessUpdatedLink($HtmlContent){
        $level=0;
        return preg_replace_callback("/<a([\s\S]*)>([\s\S]*)<\/a>/Uu",
                                      function($matches){
                                          $str = $matches[0];
                                          if(preg_match('/href=[\'"]\?page=(.*)[\'"]/', $matches[1], $link) && ($level = $this->PathUpdatedLevel($link[1]))){
                                              if(preg_match('/class/',$matches[1])){
                                                $str = '<a'.preg_replace('/class=[\'"](.*)[\'"]/',"class='$1 recent_updated'",$matches[1]).'>'.$matches[2].'</a>';
                                              }else{
                                                  $str = '<a class="'.($level==1?'recent_updated"':'recent_updated_half"').$matches[1].'>'.$matches[2].'</a>';
                                              }
                                          }
                                          return $str;
                                      },$HtmlContent);
    }
    
    function GetWebsiteSettings(){
        $this->UserConfig = fopen("la_config.md",'r');
        $ConfContent = fread($this->UserConfig,filesize("la_config.md"));
        fclose($this->UserConfig);
        $Conf = $this->ParseMarkdownConfig($ConfContent);
        $this->Title          = $this->GetLineValueByNames($Conf,"Website","Title");
        $this->TitleEN        = $this->GetLineValueByNames($Conf,"Website","TitleEN");
        $this->StringTitle    = $this->GetLineValueByNames($Conf,"Website","DisplayTitle");
        $this->StringTitleEN  = $this->GetLineValueByNames($Conf,"Website","DisplayTitleEN");
        $this->Footnote       = $this->GetLineValueByNames($Conf,"Website","Footnote");
        $this->FootnoteEN     = $this->GetLineValueByNames($Conf,"Website","FootnoteEN");
        $this->SmallQuoteName = $this->GetLineValueByNames($Conf,"Website","SmallQuoteName");
        $this->TrackerFile    = $this->GetLineValueByNames($Conf,"Website","TrackerFile");
        $this->TaskHighlightInvert = $this->GetLineValueByNames($Conf,"Website","TaskHighlightInvert")=="True"?1:0;
        $this->StatsFile      = $this->GetLineValueByNames($Conf,"Website","StatsFile");
        
        $this->MailHost       = $this->GetLineValueByNames($Conf,"Website","MailHost");
        $this->MailPort       = $this->GetLineValueByNames($Conf,"Website","MailPort");
        $this->MailUser       = $this->GetLineValueByNames($Conf,"Website","MailUser");
        $this->MailPassword   = $this->GetLineValueByNames($Conf,"Website","MailPassword");
        $this->MailTitle      = $this->GetLineValueByNames($Conf,"Website","MailTitle");
        $this->MailTitleEN    = $this->GetLineValueByNames($Conf,"Website","MailTitleEN");
        $this->MailFoot       = $this->GetLineValueByNames($Conf,"Website","MailFoot");
        $this->MailFootEN     = $this->GetLineValueByNames($Conf,"Website","MailFootEN");
        
        if(!$this->Title) $this->Title='LA<b>MDWIKI</b>';
        
        if(!$this->TitleEN) $this->TitleEN='LA<b>MDWIKI</b>';
        if(!$this->StringTitle) $this->StringTitle='LAMDWIKI';
        if(!$this->StringTitleEN) $this->StringTitleEN='LAMDWIKI';
        if(!$this->TrackerFile) $this->TrackerFile='events.md';
        if(!$this->StatsFile) $this->StatsFile='stats.md';
        if(!$this->MailTitle) $this->MailTitle=$this->StringTitle.'新闻稿';
        if(!$this->MailTitleEN) $this->MailTitleEN=$this->StringTitleEN.' NewsLetter';
        
        $i=0;$item=null;
        while($this->GetLineByNamesN($Conf,'Redirect','Entry',$i)!==Null){
            $item['from']    = $this->GetArgumentByNamesN($Conf,'Redirect','Entry',$i,'From');
            $item['to']      = $this->GetArgumentByNamesN($Conf,'Redirect','Entry',$i,'To');
            $this->List301[] = $item;
            $i++;
        }
        $i=0;$item=null;$any_removed=False;
        while($this->GetLineByNamesN($Conf,'RecentUpdates','Entry',$i)!==Null){
            $item['path']    = $this->GetArgumentByNamesN($Conf,'RecentUpdates','Entry',$i,'Path');
            $item['time']      = $this->GetArgumentByNamesN($Conf,'RecentUpdates','Entry',$i,'Time');
            if($this->IsDatedEntry($item, 3)){
                $this->RemoveLineByNamesN($Conf,'RecentUpdates','Entry',$i);
                $any_removed = True;
            }else{
                $this->RecentUpdatedList[] = $item;
                $i++;
            }
        }
        $i=0;$item=null;
        if(($block = $this->GetBlock($Conf,"HiddenFolders"))){
            if(isset($Conf[$block]['Items'][0])) foreach($Conf[$block]['Items'] as $Line){
                $this->PrivateFolderList[] = $Line['Name'];
            }
        }
        if($any_removed){
            $ConfWrite = fopen("la_config.md",'w');
            $this->WriteMarkdownConfig($Conf, $ConfWrite);
            fclose($ConfWrite);
        }
    }
    function CountTodayUpdates(){
        $count=0;
        foreach ($this->RecentUpdatedList as $item){
            $file = pathinfo($item['path'], PATHINFO_BASENAME);
            if($file == $this->StatsFile || $file == 'la_config.md') continue; 
            if($this->IsFromToday($item['time'])) $count++;
        }
        return $count;
    }
    
    function MakeHTMLHead(){
        $append_title = NULL;
        if($this->PagePath!='./index.md' && $this->PagePath!='index.md'){
            $this->FileTitle = $this->TitleOfFile($this->ContentOfMarkdownFile($this->PagePath));
            $append_title = mb_substr($this->FileTitle,0,32);
            $append_title = preg_replace('/[#*~\s]/',"",$append_title);
        }
        ?>
        <!doctype html>
        <head>
        <meta name="viewport" content="user-scalable=no, width=device-width" />
        <meta http-equiv="Access-Control-Allow-Origin" content="*">
        <title><?php echo $this->LanguageAppendix=='zh'?$this->StringTitle:$this->StringTitleEN; ?><?php echo isset($append_title)?" | $append_title":""?></title>
        <style>
        
            html{ text-align:center; }
            body{ width:100%; text-align:left; margin:0px;
                background:url(<?php echo $this->prefer_dark?"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAAHklEQVQImWXKoREAAAyEsOy/NPVfgckBTSy8I1SFDuxlEe9gb9sYAAAAAElFTkSuQmCC":
                                                             "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAAGUlEQVQImWNgYGD4z4AGMARwSvxnYGBgAACJugP9M1YqugAAAABJRU5ErkJggg=="?>) repeat;
                background-attachment: fixed;
                font-size:16px;
                color:<?php echo $this->cblack ?>;
            }
            
            .the_body{ width:60%; min-width:900px; margin: 0 auto; }

            input    { background-color:<?php echo $this->cwhite ?>; color:<?php echo $this->cblack ?>; }
            textarea { background-color:<?php echo $this->cwhite ?>; color:<?php echo $this->cblack ?>; }

            del{ color: gray;}
            
            img{ max-width: 100%; margin: 5px auto; display: block; }
            h3 img{ float: right; margin-left: 10px; max-width:30%; clear: right;}
            h4 img{ float: left; margin-right: 10px; max-width:30%; clear: left;}
            a > img{ pointer-events: none; }
            .btn img{ pointer-events: none; }
            .gallery_left img{ float: unset; margin: 5px auto; max-width: 100%;}
            
            table { width:100%; border-collapse: collapse; color: unset; position: relative; }
            table th { position: sticky; top:80px; background-color: <?php echo $this->cwhite ?>; }
            .la_actual_table { text-align: left; }
            
            pre {border-left: 3px double <?php echo $this->cblack ?>; padding: 10px; position: relative; z text-align: left; white-space: pre-wrap; }
            
            blockquote{ border-top:1px solid <?php echo $this->cblack ?>; border-bottom:1px solid <?php echo $this->cblack ?>; text-align: center; }
            
            ::-moz-selection{ background:<?php echo $this->cblack ?>; color:<?php echo $this->cwhite ?>; text-shadow: none !important; }
            ::selection{ background:<?php echo $this->cblack ?>; color:<?php echo $this->cwhite ?>; text-shadow: none !important; }
            ::-webkit-selection{ background:<?php echo $this->cblack ?>; color:<?php echo $this->cwhite ?>; text-shadow: none !important; }
            
            #Header{ position: sticky; top:0px; display: block; z-index:10; }
            #WebsiteTitle{ border:1px solid <?php echo $this->cblack ?>; display: inline-block; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; margin-left:0px; margin-right:0px; margin-bottom:15px;
                background-color:<?php echo $this->cwhite ?>; box-shadow: 5px 5px <?php echo $this->cblack ?>;
            }	
            #HeaderQuickButtons{ border:1px solid <?php echo $this->cblack ?>; display: inline; right:0px; position: absolute; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; margin-right:0px;
                background-color:<?php echo $this->cwhite ?>; box-shadow: 5px 5px <?php echo $this->cblack ?>;
            }
            
            .wide_title{ border:1px solid <?php echo $this->cblack ?>; display: inline-block; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; margin-left:0px; margin-right:0px; margin-bottom:15px;
                background-color:<?php echo $this->cwhite ?>; box-shadow: 5px 5px <?php echo $this->cblack ?>; overflow: hidden; width: calc(100% - 22px);
            }

            
            .login_half{ float: right; right: 10px; text-align: right;}
            
            .wide_body              { margin-left: 10px; margin-right:10px; }
            
            .main_content           { padding:20px; padding-left:15px; padding-right:15px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 5px 5px <?php echo $this->cblack ?>; margin-bottom:15px; scrollbar-color: <?php echo $this->cblack ?> <?php echo $this->cwhite ?>; scrollbar-width: thin;}
            .narrow_content         { padding:5px; padding-top:10px; padding-bottom:10px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 3px 3px <?php echo $this->cblack ?>; margin-bottom:8px; max-height:350px; }
            .additional_content     { padding:5px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 3px 3px <?php echo $this->cblack ?>; margin-bottom:15px; overflow: hidden; }
            .task_content           { padding:3px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 3px 2px <?php echo $this->cblack ?>; margin-bottom:5px; overflow: hidden; }
            .inline_notes_outer     { padding:5px; border-left: 3px solid <?php echo $this->cblack ?>; border-top: 3px solid <?php echo $this->cblack ?>; padding-right: 8px; padding-bottom: 8px; margin-top: 5px; margin-bottom: 5px;}
            .rss_outer              { width: unset; display: inline-block; }
            .inline_notes_content   { padding:5px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 3px 3px <?php echo $this->cblack ?>; overflow: hidden; }
            .sidenotes_content      { position: absolute; right:10px; max-width: calc(50% - 470px); width: calc(20% - 20px); }
            .sidenotes_position     { position: absolute; width:calc(100% - 13px); min-width: 250px; right:0px; bottom: 15px; display: block; }
            .sidenotes_expander     { position: absolute; left:0px; bottom: 15px; display: none;}
            .gallery_left .sidenotes_content   { position: relative; right: unset; max-width: unset; width: unset; overflow: hidden; padding: 3px; margin-top: -25px; margin-bottom: -10px; }
            .gallery_left .sidenotes_position  { position: relative; width: unset; min-width: unset; right: unset; bottom: unset; display: none; margin-top: 10px; }
            .gallery_left .sidenotes_expander  { position: relative; left: unset; bottom: unset; display: block; float: right; width: 20px; }
            .additional_content_left{ margin-right: 15px; float: left; text-align: center; position: sticky; top:82px; margin-bottom:0px;}
            .novel_content          { max-width:600px; margin:0px auto; line-height:2; }
            .more_vertical_margin   { margin-top: 100px; margin-bottom: 100px; }
            .small_shadow           { box-shadow: 2px 2px <?php echo $this->cblack ?>; }
            .tile_content           { padding:10px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 3px 3px <?php echo $this->cblack ?>; margin-bottom:15px; max-height:350px; }
            .top_panel              { padding:10px; padding-top:15px; padding-bottom:15px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 5px 5px <?php echo $this->cblack ?>; margin-bottom:15px; overflow: hidden; }
            .full_screen_window     { top:10px; bottom:10px; left:10px; right:10px; position: fixed; z-index:40; max-height: unset;}
            .gallery_left           { height:calc(100% - 160px); position: fixed; width:350px; }
            .gallery_right          { width:calc(100% - 365px); left: 365px; position: relative;}
            .gallery_main_height    { max-height: 100%; }
            .gallery_box_when_bkg   { width:30%; max-width:300px;}
            .gallery_image          { max-width: 100%; max-height:100%; margin: auto; position: absolute; left: 0; right: 0; bottom: 0; top: 0; }
            .gallery_image_wrapper         { position: relative; display: table-cell; }
            .gallery_image_wrapper::before { content: " "; display: block; padding-top: 100%; }
            .gallery_image_inner    { position: absolute; top:0px; bottom:0px; left:0px; right:0px; overflow: hidden; }
            
            .no_padding_force       { padding: 0px; !important; }
            
            .center_container       { display: table; position: absolute; top: 0; left: 0; height: 100%; width: 100%; }
            .center_vertical        { display: table-cell; vertical-align: middle; }
            .center_box             { margin-left: auto; margin-right: auto; }
            
            .file_image_preview     { width:90%; max-width:300px; margin:5px; }
            
            .adaptive_column_container { text-align: center; display: table-cell; }
            
            .underline_when_hover:hover { text-decoration: underline; }
            
            .audio_player_box       { z-index:20; padding:10px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 5px 5px <?php echo $this->cblack ?>; bottom:15px; overflow: hidden; position: sticky; margin:15px auto; margin-top:0px; width:calc(60% - 55px); min-width:845px;}
            .bottom_sticky_menu_container { z-index:20; padding:10px; overflow: visible; position: sticky; bottom:80px; margin:15px auto; margin-bottom:0px; width:calc(60% - 33px); min-width:867px; }
            .bottom_sticky_menu_left      { z-index:20; position: absolute; padding:10px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 3px 3px <?php echo $this->cblack ?>; left:10px; bottom:10px; overflow: hidden; margin:0px;  width:50%; }
            .bottom_sticky_menu_right     { z-index:20; position: absolute; padding:10px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 3px 3px <?php echo $this->cblack ?>; right:10px; bottom:10px; overflow: hidden; margin:0px;  width:50%; }
            
            canvas                  { width:100%; height:100%; }
            .canvas_box_wrapper_wide           { position: relative;}
            .canvas_box_wrapper_wide::before   { content: " "; display: block; padding-top: 56.25%; }
            .canvas_box_wrapper_super          { position: relative;}
            .canvas_box_wrapper_super::before  { content: " "; display: block; padding-top: 41.8%; }
            .canvas_box                        { position: absolute;top: 0px; left: 0px; bottom: 0px; right: 0px; display: flex; align-items: center; overflow: hidden;}
            .canvas_box_expanded               { position: relative; height:100%; max-height:calc(100% - 250px); min-height:200px;}
            
            .block_image_normal                { position: relative; text-align: center; }
            .block_image_expanded              { position: relative; text-align: center; }
            .block_image_expanded img          { margin: 0px auto; max-height:100vh; max-width:100% }
            .block_image_normal   img          { margin: 0px auto; max-height:100vh; max-width:100% }
            
            .box_complete_background           { position: fixed; top: 0px; left: 0px; bottom: 0px; right: 0px; z-index: -1;}
            .box_hang_right                    { float: right; width:30%;}
            
            .white_bkg    { background-color:<?php echo $this->cwhite ?>; }
            
            .modal_block  { background-color:rgba(0,0,0,<?php echo $this->prefer_dark?"0.7":"0.2" ?>); position: fixed; z-index:30; top: 0px; left: 0px; bottom: 0px; right: 0px; }
            .modal_dialog { z-index:50; }
            .modal_on_mobile { z-index:0; }
            
            .btn          { border:1px solid <?php echo $this->cblack ?>; padding: 5px; color:<?php echo $this->cblack ?>; display: inline; background-color:<?php echo $this->cwhite ?>; font-size:16px; cursor: pointer; text-align: center; }
            .btn:hover    { border:3px double <?php echo $this->cblack ?>; padding: 3px; }
            .btn:active   { border:5px solid <?php echo $this->cblack ?>; border-bottom: 1px solid <?php echo $this->cblack ?>; border-right: 1px solid <?php echo $this->cblack ?>; padding: 3px; }
            .btn:disabled { border:1px solid <?php echo $this->cblack ?>; padding: 5px; cursor: not-allowed; }
            .btn_nopadding          { padding: 2px; }
            .btn_nopadding:hover    { padding: 0px; }
            .btn_nopadding:active   { padding: 0px; }
            .block        { display: block; }
            .inline_block { display: inline-block; }
            .form_btn     { float: right; margin-top:-6px; margin-bottom:-6px; margin-left:5px; }
            .form_btn_left{ margin-top:-6px; margin-bottom:-6px; margin-right:5px; }
            .preview_btn  { height:250px; overflow: hidden; }
            .full_btn     { width:100%; }
            .no_float     { float: unset; }
            
            .mobile_force_fullscreen { position: relative; }
            
            .inline_height_spacer      { display: block; height:15px; width:100%; }
            .inline_block_height_spacer{ display: block; height:10px; width:100%; }
            .block_height_spacer { display: block; height:4px; width:100%; }
            
            .halftone1  { background: url(<?php echo (!$this->prefer_dark)?"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAGElEQVQYlWNgIBH8HxyKaQ+Icg71FGEAAMIRBftlPpkVAAAAAElFTkSuQmCC":
                                                                           "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAgAAAAICAYAAADED76LAAAAKklEQVQYlWNgYGD4TwAz/P///z9+BQQUITg4FBHhBlwYaiJeSewKkN0CAH68Ua9is7GVAAAAAElFTkSuQmCC" ?>) repeat; }
            .halftone2  { background: url(<?php echo (!$this->prefer_dark)?"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAD0lEQVQImWNgIAf8J10LADM2AQA1DEeOAAAAAElFTkSuQmCC":
                                                                           "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAAGklEQVQImWNgYGD4j4YZ/v///x9VAEMFMgYAjNMS7u6EWNsAAAAASUVORK5CYII="?>) repeat; }
            .halftone3  { background: url(<?php echo (!$this->prefer_dark)?"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHUlEQVQImWNgYGD4j4YZGLAI/GfAIgAXxKYD1VwA+JoT7dVZ0wkAAAAASUVORK5CYII=":
                                                                           "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAAIUlEQVQImWNgYGD4D8P/////z4DGgQggcf4zoHAYGP4DAKLLG+XKz5dJAAAAAElFTkSuQmCC"?>) repeat; }
            .halftone4  { background: url(<?php echo (!$this->prefer_dark)?"data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAYAAABWKLW/AAAADklEQVQImWNgQID/uBkANfEC/tK2Q2IAAAAASUVORK5CYII=":
                                                                           "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAYAAABWKLW/AAAAHElEQVQImU3GsQ0AAAzDIP5/2t2iMgGhKi9Z6ABU4BHvJiLMHQAAAABJRU5ErkJggg=="?>) repeat; }
            
            .inline            { display: inline; margin:0px; }
            .inline_components { display: inline; margin: 5px; }
            .plain_block       { border: 1px solid <?php echo $this->cblack ?>; text-align: left; padding:5px; }
            .preview_block     { border-right: 1px solid <?php echo $this->cblack ?>; margin:5px; }
            .border_only       { border: 1px solid <?php echo $this->cblack ?>; }
            
            .inline_p          { }
            .inline_p p, .inline_p h1, .inline_p h2, .inline_p h3, .inline_p h4, .inline_p h5, .inline_p h6, .inline_p { display: inline; margin:0px; }
            
            .string_input      { border:3px solid <?php echo $this->cblack ?>; border-bottom: 1px solid <?php echo $this->cblack ?>; border-right: 1px solid <?php echo $this->cblack ?>; padding: 3px; margin:5px; width:150px; }
            .quick_post_string { border:3px solid <?php echo $this->cblack ?>; border-bottom: 1px solid <?php echo $this->cblack ?>; border-right: 1px solid <?php echo $this->cblack ?>; padding: 3px; margin:0px; width:100%; resize: none; overflow: hidden; height: 25px; }
            .big_string        { width: calc(100% - 10px); height: 500px; resize: vertical; border:none; }
            .big_string_height { height: 500px; }
            .title_string      { margin-top:-5px; margin-bottom:-5px; font-size:16px; text-align: right; }
            
            .no_horizon_margin { margin-left:0px; margin-right:0px; }
            
            .navigation        { border:1px solid <?php echo $this->cblack ?>; display: inline; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; right:0px; background-color:<?php echo $this->cwhite ?>; box-shadow: 5px 5px <?php echo $this->cblack ?>; <?php echo $this->cwhite ?>-space: nowrap; text-align: center;}
            .navigation p      { display: inline; }
            
            .navigation_task p { display: inline; }
            .navigation_task   { display: inline; }
            
            .tile_container    { display: table; table-layout: fixed; width: calc(100% + 30px); border-spacing:15px 7px; margin-left: -15px; margin-top: -7px; margin-bottom: 8px; }
            .tile_item         { display: table-cell; }
            .image_tile        { display: table; table-layout: fixed; width: 100%; }
            
            .footer            { padding:10px; padding-top:5px; padding-bottom:5px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 5px 5px <?php echo $this->cblack ?>; margin-left:10px; overflow: hidden; }
            .additional_options{ padding:5px; padding-top:10px; padding-bottom:10px; border:1px solid <?php echo $this->cblack ?>; background-color:<?php echo $this->cwhite ?>; box-shadow: 3px 3px <?php echo $this->cblack ?>; margin-bottom:-15px; overflow: hidden; display: inline-block; position: relative; }
            
            
            .recent_updated            { background-color: <?php echo $this->chighlight ?> !important; }
            .recent_updated_half       { background-color: <?php echo $this->chalfhighlight ?> !important; }
            
            a        { border:1px solid <?php echo $this->cblack ?>; padding: 5px; color:<?php echo $this->cblack ?>; text-decoration: none; }
            a:hover  { border:3px double <?php echo $this->cblack ?>; padding: 3px; }
            a:active { border:5px solid <?php echo $this->cblack ?>; border-bottom: 1px solid <?php echo $this->cblack ?>; border-right: 1px solid <?php echo $this->cblack ?>; padding: 3px; }
            a:disabled { border:1px solid <?php echo $this->cblack ?>; padding: 5px; cursor: not-allowed; }
            .main_content a       { padding: 0px; padding-left:3px; padding-right:3px; display: inline-block; background-color:<?php echo $this->cwhite ?>; }            
            .main_content a:hover { border:1px solid <?php echo $this->cblack ?>; text-decoration: underline; }
            .main_content a:active{ border:1px solid <?php echo $this->cblack ?>; color:<?php echo $this->cwhite ?>; background-color:<?php echo $this->cblack ?> !important; }
            .main_content .no_border:hover { border: none; }
            .main_content .no_border:active { border: none; }
            
            .preview{ margin: 10px; }
            .preview h1, .preview h2, .preview h3, .preview h4, .preview h5, .preview h6, .preview blockquote                                  { margin-top: 3px; margin-bottom: 3px; }
            .preview a {pointer-events: none;}
            .name_preview h1, .name_preview h2, .name_preview h3 .name_preview h4, .name_preview h5, .name_preview h6, .name_preview p         { font-size:16px; display: inline; }
            .preview_large h1, .preview_large h2, .preview_large h3, .preview_large h4, .preview_large h5, .preview_large h6, .preview_large p { display: block; }
            .preview_large h1{ font-size:24px; }
            .preview_large h2{ font-size:20px; }
            .preview_large h3{ font-size:18px; }
            .passage_detail{ float: right; text-align: right; margin-left:5px; width:20%; min-width:210px; }
            .small_shadow p{ display: inline; }
            
            .task_ul { position: relative;	list-style: none; margin-left: 0; padding-left: 1.2em; }
            .task_ul li:before{ position: absolute; left: 0; }
            .task_ul .active:before { content: "@"; }
            .task_ul .pending:before { content: "+"; }
            .task_ul .done:before { content: "-"; }
            .task_ul .canceled:before { content: "x"; }
            .task_p { font-size: 12px; margin:1px; }
            
            .novel_content hr { height: 5em; border: none; }
            
            .no_border { border: none; }
            .under_border { border: none; border-bottom: 1px solid <?php echo $this->cblack ?>; }
            
            .appendix { text-align: right; font-size: 12px; line-height: 1.2;}
            
            .hidden_on_desktop       { display: none; }
            .hidden_on_desktop_inline{ display: none; }
            .only_on_print           { display: none; }
            
            .no_overflow             { overflow: unset;}
            
            .theme_firebrick { background-color: firebrick; color: gold; text-shadow: 2px 2px 2px black; }
            .theme_firebrick a { background-color: firebrick; color: gold !important; border-color: gold !important;}
            .theme_cornflowerblue { background-color: cornflowerblue; color: white; text-shadow: 2px 2px 2px black; }
            .theme_cornflowerblue a { background-color: cornflowerblue; color: white !important; border-color: white !important;}
            .theme_slategray { background-color: slategray; color: white; }
            .theme_slategray a {  background-color: slategray; color: white !important; border-color: white !important;}
            .theme_darkgoldenrod { background-color: darkgoldenrod; color: moccasin; }
            .theme_darkgoldenrod a { background-color: darkgoldenrod; color: moccasin !important; border-color: moccasin !important;}
            .theme_green { background-color: green; color: palegreen; }
            .theme_green a { background-color: green; color: palegreen !important; border-color: palegreen !important;}
            .theme_indigo { background-color: indigo; color: lightcyan; }
            .theme_indigo a { background-color: indigo; color: lightcyan !important; border-color: lightcyan !important;}
            
            .theme_align_left   { text-align: left; }
            .theme_align_center { text-align: center; }
            .theme_align_right  { text-align: right; }
            
            @media screen and (max-width: 1000px) {
                
                .the_body{ left:10px; width:calc(100% - 20px); min-width: unset; }
                
                .inline_p          { }
                .inline_p p, .inline_p h1, .inline_p h2, .inline_p h3, .inline_p h4, .inline_p h5, .inline_p h6, .inline_p { font-size: 16px; }
                
                h3 img{ float: unset; max-width:100%; margin: 5px auto;}
                h4 img{ float: unset; max-width:100%; margin: 5px auto;}
                
                .navigation   { display: none; margin: 0px; margin-bottom: 15px; }
                .navigation p { display: block; margin: 0px; }
                .navigation a { display: block; margin-top: 5px; }
                
                .navigation_task   { display: none; margin: 0px; margin-bottom: 15px; }
                .navigation_task p { display: block; margin: 0px; }
                .navigation_task a { display: block; margin-top: 5px; }
                
                .hidden_on_mobile        { display: none; }
                .hidden_on_desktop       { display: block; }
                .hidden_on_desktop_inline{ display: inline; }
                
                .mobile_force_fullscreen { position: fixed; left:0px; right:0px; top:0px; bottom:0px; }
                .modal_on_mobile { z-index:50; }
                .editor_shrink { height:100px; }
                
                #HeaderQuickButtons{ top:0px; }
                
                .tile_container{ display: block; table-layout: unset; width: 100%; margin: 0px; }
                .tile_item{ display: block; }
                .image_tile        { display: block; }
                
                .sidenotes_content   { position: relative; right: unset; max-width: unset; width: unset; overflow: hidden; padding: 3px; margin-top: -25px; margin-bottom: -10px; }
                .sidenotes_position  { position: relative; width: unset; min-width: unset; right: unset; bottom: unset; display: none; margin-top: 10px; }
                .sidenotes_expander  { position: relative; left: unset; bottom: unset; display: block; float: right; width: 20px; }
                
                .gallery_left           { height: unset; position: unset; width: unset; }
                .gallery_right          { width: unset; left: unset; z-index:10; position: unset; }
                .gallery_main_height    { height: unset; }
                .gallery_multi_height::before    { display: none; }
                .gallery_multi_content  { position: unset;}
                .gallery_image_wrapper::before { content: unset; display: block; padding-top: 0px; }
                .gallery_image_wrapper  { display: block; }
                .gallery_image_inner    { position: relative; top: unset; bottom: unset; left: unset; right: unset; overflow: hidden; }
                .gallery_image          { max-width: 100%; position: unset; max-height: unset; min-width: unset; min-height: unset; object-fit: unset; }
                .gallery_box_when_bkg   { width:60%; max-width: unset;}
                
                .box_hang_right         { float: unset; width: unset;}
                
                .audio_player_box       { margin:10px auto;  width:calc(100% - 60px); min-width:unset;}
                .bottom_sticky_menu_container { margin:10px auto;  width:calc(100% - 38px); min-width:unset;}
                .bottom_sticky_menu_left      { width: 300px; max-width:calc(100% - 42px); }
                .bottom_sticky_menu_right     { width: 300px; max-width:calc(100% - 42px); }

                .adaptive_column_container { display: block; }
                
                .passage_detail         { width:60%; }
                .big_string_height      { height: calc(100% - 40px); }
                
                .novel_content          { max-width: unset;}
                .more_vertical_margin   { margin-top: 0px; margin-bottom: 0px; }
                
                .no_overflow_mobile     { overflow: unset;}
                .highlight_stripe_mobile { position: fixed; bottom:0px; width:100%; }
            }
            
            @media screen and (max-width: 350px) {
                .the_body{ left:0px; width:100%; }
                .main_content, .top_panel, .narrow_content, .additional_content, #Navigation, .navigation, #HeaderQuickButtons, #WebsiteTitle, .footer{ border: none; }
            }
            
            @media print {
                body{ width:100%; min-width: unset; line-height: 1.6}
                
                .the_body{ width:100%; min-width:unset; margin: 0 auto; }
                
                #Header                 { display: none; }
                .top_panel,
                .hidden_on_print,
                .footer                 { display: none; }
                .main_content,
                .narrow_content,
                .additional_content,
                .additional_content_left,
                .tile_content,
                .top_panel              { padding:0;  border: none; background-color:<?php echo $this->cwhite ?>; box-shadow: unset; margin:0; overflow: unset; }
                .full_screen_window     { top:10px; bottom:10px; left:10px; right:10px; position: fixed; z-index:1000; max-height: unset;}
                .gallery_left           { height: unset; width: unset; position: unset; padding:0;  border: none; background-color:<?php echo $this->cwhite ?>; box-shadow: unset; margin:0; overflow: unset; }
                .gallery_right          { height: unset; width: unset; left: unset; z-index:10; position: unset; padding:0;  border: none; background-color:<?php echo $this->cwhite ?>; box-shadow: unset; margin:0; overflow: unset; }
                .gallery_main_height    { max-height: unset }
                .no_padding_force       { padding: 0px; !important }
                
                .print_document h1{ border-left: 10px solid <?php echo $this->cblack ?>; padding-left:10px; border-bottom: 1px solid <?php echo $this->cblack ?>; margin-bottom: 5px }
                
                .print_document h2{ border-left: 5px solid <?php echo $this->cblack ?>; padding-left:5px;}
                
                .print_document h3{ border-left: 1px solid <?php echo $this->cblack ?>; padding-left:9px;}
                
                .gallery_left h1, .gallery_left h2, .gallery_left h3, .gallery_left h4, .gallery_left h5, .gallery_left h6 { display: inline; }
                
                pre{ <?php echo $this->cwhite ?>-space: pre-wrap; border: 1px dotted <?php echo $this->cblack ?>; }
                
                .print_document          { padding-left: 10px; }
                .print_document h1, .print_document h2, .print_document h3, .print_document h4, .print_document h5, .print_document h6 { margin-left: -10px; }
                .appendix h1, .appendix h2, .appendix h3, .appendix h4, .appendix h5, .appendix h6 { border: none; }
                
                .only_on_print           { display: unset; }

                .audio_player_box        { display: none; }
                
                canvas                   { display: none; }
            }
            
            @media (min-resolution: 192dpi),
            (-webkit-min-device-pixel-ratio: 2), (min--moz-device-pixel-ratio: 2),
            (-o-min-device-pixel-ratio: 2/1),
            (min-device-pixel-ratio: 2),
            (min-resolution: 2dppx) {
                
            }
            
            
            
        </style>
        <script>
        function la_auto_grow(element) {
            element.style.height = "30px";
            element.style.height = (element.scrollHeight)+"px";
        }
        function la_pad(num, n) {
            return (Array(n).join(0) + num).slice(-n);
        }
        </script>
        </head>
        <body>
        <?php
    }
    function SpetialStripeSegment($width,$color){
        ?>
                <div style='display:inline-block; height:100%; width:<?php echo $width?>; background-color:<?php echo $color?>;'></div>
        <?php
    }
    function MakeSpecialStripe(){
        $show = (isset($_GET['operation']) && ($_GET['operation'] == 'new' || $_GET['operation'] == 'edit'))  || $this->IsPathUpdated($this->PagePath);
        ?>
        <?php echo "<!-- special_stripe -->";?><div class='hidden_on_print' style='background-color:<?php echo $this->cwhite?>; height:10px; margin-top: -20px;margin-left: -15px;margin-right: -15px; margin-bottom:10px;'>
            <div style='width:600px; max-width:100%; height:100%; font-size:0px; overflow:hidden;'>
            <?php
            if($show){
                if($this->prefer_dark){
                    $this->SpetialStripeSegment('25%','#6495ed');
                    $this->SpetialStripeSegment('17%','#5a87d7');
                    $this->SpetialStripeSegment('12%','#5179c1');
                    $this->SpetialStripeSegment('10%','#486cac');
                    $this->SpetialStripeSegment('9%','#3f5e96');
                    $this->SpetialStripeSegment('8%','#365181');
                    $this->SpetialStripeSegment('7%','#2d436b');
                    $this->SpetialStripeSegment('6%','#243656');
                    $this->SpetialStripeSegment('5%','#1b2840');
                    $this->SpetialStripeSegment('4%','#121b2b');
                    $this->SpetialStripeSegment('3%','#090d15');
                }else{
                    $this->SpetialStripeSegment('25%','#ffd700');
                    $this->SpetialStripeSegment('17%','#ffda17');
                    $this->SpetialStripeSegment('12%','#ffde2e');
                    $this->SpetialStripeSegment('10%','#ffe145');
                    $this->SpetialStripeSegment('9%','#ffe55c');
                    $this->SpetialStripeSegment('8%','#ffe973');
                    $this->SpetialStripeSegment('7%','#ffec8b');
                    $this->SpetialStripeSegment('6%','#fff0a2');
                    $this->SpetialStripeSegment('5%','#fff4b9');
                    $this->SpetialStripeSegment('4%','#fff7d0');
                    $this->SpetialStripeSegment('3%','#fffbe7');
                }
            }
            ?>
            </div>
        <?php echo "<!-- special_stripe -->";?></div>
        <?php
    }
    function WideHeaderBegin(){
        ?>
        <div class='wide_title'>
        <?php
    }
    function WideHeaderEnd(){
        ?>
        </div>
        <?php
    }
    function PageHeaderBegin(){
        ?>
        <div id='Header' class='the_body '>
        <?php
    }
    function PageHeaderEnd(){
        ?>
        </div>
        <?php
    }
    function TaskNavigationBegin(){
        ?>
        <div class="navigation_task" id="task_navigation_container" style="display:none;text-align:center">
            &nbsp;&nbsp;
            <div class='hidden_on_desktop inline_block_height_spacer' ></div>
            <p><a href="?page=index.md"><b>&#8962;&nbsp;<?php echo $this->FROM_ZH('首页') ?></b></a></p>
            <div class='hidden_on_desktop block_height_spacer' ></div>
        <?php
    }
    function TaskNavigationEnd(){
        ?>
        </div>
        <?php
    }
    function MakeTitleButton(){
        $use_title=$this->LanguageAppendix=='zh'?$this->Title:$this->TitleEN;
        ob_start();
        ?>
        <?php if(!$this->IsTaskManager){ ?>
            <div id='WebsiteTitle'>
                <a class='hidden_on_mobile' href="?page=index.md"><?php echo $use_title?></a>
                <a class='hidden_on_desktop_inline' id='HomeButton' ><?php echo $use_title;?>...</a>
                <?php if($this->Trackable){ ?><a class='hidden_on_mobile' href="?page=<?php echo $this->TrackerFile; ?>"><?php echo $this->FROM_ZH('跟踪') ?></a> <?php } ?>
            </div>
        <?php }else{ ?>
            <a id="task_home_button" onClick="la_ToggleNavigationInTaskMode(); if(document.getElementById('task_view_buttons').style.display=='block'){la_toggle_login_task_mobile();}hide_login_uis();"><?php echo $this->Title;?></a>
            <script>
            function la_ToggleNavigationInTaskMode(){
                c = document.getElementById("task_navigation_container");
                disp = c.style.display=='none'?'inline':'none';
                c.style.display=disp;
                
                h = document.getElementById("task_master_header_desktop");
                h.style.display=disp=='none'?'inline':'none';
                
                header = document.getElementById("Header");
                footer = document.getElementById("task_manager_footer");
                if(disp=="inline"){
                    header.style.zIndex=100;
                    footer.style.zIndex=10;
                    la_show_modal_blocker();
                }else{
                    header.style.zIndex="";
                    footer.style.zIndex="";
                    la_hide_modal_blocker();
                }
            }
            </script>
        <?php } ?>
        <?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
    function MakeMainContentBegin($use_stripe){
        $layout = $this->GetAdditionalLayout();
        $this->AdditionalLayout = $layout;
        $novel_mode = $this->FolderNovelMode($this->InterlinkPath());
        
        if(!$this->MainContentAlreadyBegun){
            ?>
            <?php echo "<!-- main_begin -->";?><div class='the_body'>
            <?php
        }
        
        if($layout == 'Gallery' && (!isset($_GET['operation'])||($_GET['operation']!='edit'&&$_GET['operation']!='new'&&$_GET['operation']!='settings'))){
        ?>
            <div class='gallery_left'>
            <div class='main_content gallery_main_height' style="overflow:auto; position:relative;">
            <?php if($use_stripe) echo $this->MakeSpecialStripe(); ?>
        <?php
        }else{
        ?>
            <div class='main_content <?php echo $novel_mode?"":"print_document" ?>' style='<?php echo $this->BackgroundSemi?"background-color:".$this->csemiwhite.";":""?>'>
            <?php if($use_stripe) echo $this->MakeSpecialStripe(); ?>
            <div class='<?php echo ($novel_mode && !$this->GetEditMode())?"novel_content more_vertical_margin":""?>' style='position:relative'>
        <?php
        }
    }
    function MakeMainContentEnd(){
        ?>
            </div>
            </div>
            <?php echo "<!-- main_end -->"; ?></div>
        <?php
    }
    function InsertReplacementSymbols($MarkdownContent){
        $replacement = preg_replace_callback("/(`|```)([^`]*)(?1)/U",
                    function($matches){
                        $rep = preg_replace('/\-\>/','-@>',$matches[0]);
                        return preg_replace('/\<\-/','<@-',$rep);
                    },
                    $MarkdownContent);
        $replacement = preg_replace("/\-\>/","🡲",$replacement);
        $replacement = preg_replace("/\<\-/","🡰",$replacement);
        $replacement = preg_replace_callback("/(`|```)([^`]*)(?1)/U",
                    function($matches){
                        $rep = preg_replace('/\-@\>/','->',$matches[0]);
                        return preg_replace('/\<@\-/','<-',$rep);
                    },
                    $replacement);
        return $replacement;
    }
    function InsertBlockTheme($HTMLContent){
        $Content = $HTMLContent;
        
        $Safe = preg_replace_callback("/(`|```)([^`]*)(?1)/U",
                    function($matches){
                        return preg_replace('/\[theme/','[@theme',$matches[0]);
                    },
                    $Content);
        
        $Content = preg_replace_callback("/<!-- main_begin -->[\s]*<div ([\s]*class=[\"\'][^\"\']*the_body[\s\S]*<div.*class=[\"\'][^\"\']*main_content)([\s\S]*)<!-- main_end -->/U",
            function($matches){
                if(preg_match("/<p>[\s]*\[theme ([\s\S]*)\][\s]*<\/p>/U", $matches[2], $args)){
                    preg_match("/name:[\s]*([\S]*)/", $args[1], $matched_name);
                    preg_match("/wide/", $args[1], $matched_wide);
                    preg_match("/no_padding/", $args[1], $matched_padding);
                    preg_match("/align:[\s]*([\S]*)/", $args[1], $matched_align);
                    
                    $before = '<div '.(isset($matched_wide[0])?'style="width:calc(100% - 20px); padding:5px;" ':'')
                               .$matches[1]
                               .(isset($matched_padding[0])?" no_padding_force":"")
                               .(isset($matched_name[1])?" theme_".$matched_name[1]:"")
                               .(isset($matched_align[1])?" theme_align_".$matched_align[1]:"");
                    
                    $remaining = preg_replace("/<p>[\s]*\[theme ([\s\S]*)\][\s]*<\/p>/","",$matches[2]);
                    $remaining = preg_replace("/<!-- special_stripe --><div[\s\S]*<!-- special_stripe --><\/div>/","",$remaining);
                    return $before.$remaining;
                }
                return '<div '.$matches[1].$matches[2];
            },$Safe);
            
        $Content = preg_replace_callback("/(`|```)([^`]*)(?1)/U",
                    function($matches){
                        return preg_replace('/\[@theme/','[theme',$matches[0]);
                    },
                    $Content);
        return $Content;
    }
    function GetRssFeed($url){
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_TIMEOUT,20);
        $output = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if($httpcode!=200) return "<!-- Error --!>本网站所在服务器无法连接到目标地址。";
        $fp = file_get_contents($url, false, stream_context_create(array('http' => array('timeout' => '20'))),0, 30000);
        if ($fp) {
                        
            $xmlReader = new XMLReader();
            $xmlReader->XML($fp);

            $isParserActive = false;
            $simpleNodeTypes = array ("title", "description", "media:title", "link");
            $i=0;
            
            while ($xmlReader->read ())
            {
                $nodeType = $xmlReader->nodeType;
                if ($nodeType != XMLReader::ELEMENT && $nodeType != XMLReader::END_ELEMENT)
                {
                    continue;
                }
                else if ($xmlReader->name == "item")
                {
                    if (($nodeType == XMLReader::END_ELEMENT) && $isParserActive)
                    {
                        $i++;
                    }
                    $isParserActive = ($nodeType != XMLReader::END_ELEMENT);
                }

                if (!$isParserActive || $nodeType == XMLReader::END_ELEMENT)
                {
                    continue;
                }

                $name = $xmlReader->name;

                if (in_array ($name, $simpleNodeTypes))
                {
                    // Skip to the text node
                    $xmlReader->read ();
                    $items[$i][$name] = $xmlReader->value;
                }
                else if ($name == "media:thumbnail")
                {
                    $items[$i]['media:thumbnail'] = array (
                        "url" => $xmlReader->getAttribute("url"),
                        "width" => $xmlReader->getAttribute("width"),
                        "height" => $xmlReader->getAttribute("height")
                    );
                }
                if($i>=1) break;
            }
            $count = 0;
            $html = "";
            foreach($items as$item) {
                $html .= '<h2>'.htmlspecialchars($item['title']).'</h2> <a href="'.htmlspecialchars($item['link']).'">立即收听</a><div class="inline_block_height_spacer"></div>';
            }
            return $html;
        } else {
            return "<!-- Timeout --!>请求超时。";
        }
    }
    function RespondToRssRequest(){
        if(!isset($_GET['rss_helper'])) return;
        $url = $_GET['rss_helper'];
        
        
        return $this->GetRssFeed($url);
    }
    function InsertRSSList($HTMLContent){
        $Content = $HTMLContent;
        global $rss_i;
        $rss_i=0;
        $Safe = preg_replace_callback("/(`|```)([^`]*)(?1)/U",
                    function($matches){
                        return preg_replace('/\[rss/','[@rss',$matches[0]);
                    },
                    $Content);
                    
        $Content = preg_replace_callback("/<p>[\s]*\[rss.*href=[\'\"]([\s\S]*)[\'\"].*\].*<\/p>/U",
            function($matches){
                global $rss_i;
                $url = $matches[1];
                $insert = "<div id='rss_display_".$rss_i."'>".
                          '<div class="inline_notes_outer halftone4 rss_outer"> <div class="inline_notes_content"><b>最新剧集</b>'.
                          '<div id="rss_inner_'.$rss_i.'"></div>'.
                          "<span style='font-size:12px;'>RSS订阅地址 ".$url.'</span>'.
                          '</div></div>'.
                          "</div><script>"."
                          content".$rss_i." = document.createElement('div');
                          content".$rss_i.".innerHTML='正在等待服务器返回数据……';
                          document.querySelector('#rss_inner_".$rss_i."').appendChild(content".$rss_i.");
                          fetch('index.php?rss_helper=".$url."').then((res) => {
                              res.text().then((xmlTxt) => {
                                  content".$rss_i.".innerHTML = xmlTxt;
                              }
                          )})".
                          "</script>";
                $rss_i = $rss_i+1;
                return $insert;
            },
            $Safe);
            
        $Content = preg_replace_callback("/(`|```)([^`]*)(?1)/U",
                    function($matches){
                        return preg_replace('/\[@rss/','[rss',$matches[0]);
                    },
                    $Content);
        return $Content;
    }
    
    function MakeNotifications(){
        $path = $this->InterlinkPath();
        $file = $path.'/notifications.md';
        
        if(file_exists($file)){
            $f = fopen($file,'r');
            $size=filesize($file);
            $content = fread($f,$size);
            fclose($f);
        }else return;
        
        if($content == ''){
            unlink($file);
            return;
        }
        
        ?>
            <?php echo "<!-- main_begin -->";?><div class='the_body'>
            <div class='main_content' style='padding-top:0px; padding-bottom:0px;'>
        <?php
        echo $this->HTMLFromMarkdown($this->InsertAdaptiveContents($content));
        ?>
            </div>
            <?php echo "<!-- main_end -->";?></div>
        <?php
    }
    function AddTableInteractions($html){
        $new_html = preg_replace("/<table>/","<table class='la_actual_table'>",$html);
        return $new_html;
    }
    
    function RemoveBlankAfterInserts($html){
        return preg_replace('/<div.*class=[\'\"]the_body[\'\"].*>\s*<div.*class=[\'\"]main_content[\'\"].*>\s*<div>\s*<\/div>\s*<\/div>\s*<\/div>/U',
                            "",
                            $html);
    }
    
    function InsertAdaptiveContents($markdown){
        $op1 = preg_replace_callback("/(`|```)([^`]*)(?1)/U",
                            function($matches){
                                return preg_replace('/\[adaptive\]/','[@adaptive]',$matches[0]);
                            },
                            $markdown);
        $res = preg_replace_callback('/\[adaptive\]([\s\S]*)\[\/adaptive\]/U',
                                     function($matches){
                                         return "<table style='table-layout: fixed;'> <tr>".
                                                preg_replace_callback('/\[column\]([\s\S]*)\[\/column\]/U',
                                                                      function($matches){
                                                                          return "<td class='adaptive_column_container'><div>".
                                                                                 $this->HTMLFromMarkdown($matches[1]).
                                                                                 "</div></td>";
                                                                      },
                                                                      $matches[1]).
                                                "</tr> </table>";
                                     },
                                     $op1);
        return preg_replace_callback("/(`|```)([^`]*)(?1)/U",
                            function($matches){
                                return preg_replace('/\[@adaptive\]/','[adaptive]',$matches[0]);
                            },
                            $res);
    }
    
    function Make3DContentActual($sc,$hooked,$id){
        $expanded  =       (isset($sc['expand'])&&$sc['expand']!=0);
        $no_padding =      (isset($sc['padding'])&&$sc['padding']==0);
        $inline =          (isset($sc['mode'])&&$sc['mode']=='Inline'&&$hooked);
        $hang = $inline && (isset($sc['hang'])&&$sc['hang']=='1');
        $hook = $hooked;
        $lock_center =     (isset($sc['lock_center'])&&$sc['lock_center']=='1');
        $is_background =   (isset($sc['mode'])&&$sc['mode']=='Background');
        
        if(!$is_background){
        if(!$inline){
        if($hooked) echo '</div></div>';
        ?>
        </div>
        <div class='the_body' style="<?php echo $expanded?'width:calc(100% - 20px);':''?>">
            <div class='main_content' style="<?php echo $no_padding?'padding:0px;':''?> <?php echo $this->BackgroundSemi?"background-color:".$this->csemiwhite.";":""?>">
                 <div>
        <?php } 
        if($hang){
            ?>
            <div class='additional_content box_hang_right' style='padding:0px;'>
            <?php
        }
        }// not background
        ?>
                
                <div class="<?php echo $is_background?'box_complete_background':($expanded?'canvas_box_expanded':'canvas_box_wrapper_wide')?>">
                    <div class='canvas_box'>
                        <canvas id="<?php echo $id ?>">HTML5 Canvas</canvas>
                    </div>
                </div>
                <script src="three.min.js"></script>
                <script src="GLTFLoader.js"></script>
                <script src="Controls.js"></script>
                <script>
                    var scene<?php echo $id?> = new THREE.Scene();
                    var clock<?php echo $id?> = new THREE.Clock();
                    var mixer<?php echo $id?> = null;
			        var camera<?php echo $id?> = null;
			        var document_camera<?php echo $id?> = null;
                    
                    var canvasElm<?php echo $id?> = document.getElementById("<?php echo $id ?>");
                    
                    var renderer<?php echo $id?> = new THREE.WebGLRenderer( { canvas: canvasElm<?php echo $id?>, antialias: true } ); 
                    renderer<?php echo $id?>.setSize(canvasElm<?php echo $id?>.clientWidth, canvasElm<?php echo $id?>.clientHeight);
                    
                    canvasElm<?php echo $id?>.oncontextmenu = () => false;
                    
                    var solid_mat<?php echo $id?> = new THREE.MeshBasicMaterial({color:<?php echo $this->prefer_dark?"0x000000":"0xffffff"?>, polygonOffset: true,
                                                                    polygonOffsetFactor: 1,
                                                                    polygonOffsetUnits: 1});
			        //var line_mat<?php echo $id?> = 
			        
			        scene<?php echo $id?>.background = new THREE.Color( <?php echo $this->prefer_dark?"0x000000":"0xffffff"?> );
			        
			        var directionalLight = new THREE.DirectionalLight(0xffffff,2);
                    directionalLight.position.set(1, 0, 1).normalize();
                    scene<?php echo $id?>.add(directionalLight);
			        
			        function loadScene<?php echo $id?>() {              
                        var loader = new THREE.GLTFLoader();
                        loader.load("<?php echo $this->InterlinkPath().'/'.$sc['file']?>",
                            function (gltf) {
                                var model = gltf.scene;
                                scene<?php echo $id?>.add(model);
                                scene<?php echo $id?>.traverse( function ( child ) {
                                    if ( child.isMesh ) {
                                        //child.doubleSided = true;
                                        var line_mat = child.material.la_line;
                                        if(!line_mat){
                                            line_mat = new THREE.LineBasicMaterial( { color: <?php echo $this->prefer_dark?"0xffffff":"0x000000"?>, linewidth: 1+child.material.roughness, linecap: 'round', linejoin:  'round'} );
                                            child.material.la_line = line_mat;
                                        }
                                        var edges = new THREE.EdgesGeometry(child.geometry, thresholdAngle=5);
                                        var lines = new THREE.LineSegments( edges, line_mat );
                                        //child.material.color.setRGB(1,1,1);
                                        child.material=solid_mat<?php echo $id?>;
                                        // color roughness emissive metalness  ---> available from blender export
                                        
                                        child.add(lines);
                                    }
                                    if(child.isCamera){
                                        document_camera<?php echo $id?> = scene<?php echo $id?>.getObjectByName(child.name);
                                        camera<?php echo $id?> = new THREE.PerspectiveCamera( child.fov, canvasElm<?php echo $id?>.clientWidth / canvasElm<?php echo $id?>.clientHeight, 0.1, 1000 );
                                        camera<?php echo $id?>.position.set(child.position.x,child.position.y,child.position.z);
                                        camera<?php echo $id?>.rotation.set(child.rotation.x,child.rotation.y,child.rotation.z);
                                    }
						        });
						        
						        if(gltf.animations.length){
						            mixer<?php echo $id?> = new THREE.AnimationMixer(model);
						            for(var i=0; i<gltf.animations.length; i++){
                                        mixer<?php echo $id?>.clipAction(gltf.animations[i]).play();
						            }
                                }
                                if(!camera<?php echo $id?>){
                                    camera<?php echo $id?> = new THREE.PerspectiveCamera( 75, canvasElm<?php echo $id?>.clientWidth / canvasElm<?php echo $id?>.clientHeight, 0.1, 100 );
                                    camera<?php echo $id?>.position.z=5;
                                }
                               
                                animate<?php echo $id?>();
                                
                                var center<?php echo $id?> = new THREE.Vector3(0,0,0);
                                
                                var radius = camera<?php echo $id?>.position.distanceTo(center<?php echo $id?>); 
                                
                                var po = camera<?php echo $id?>.position;
                                camera<?php echo $id?>.translateZ(-radius);
                                center<?php echo $id?>.set(po.x,po.y,po.z);
                                camera<?php echo $id?>.translateZ(radius);
                                
                                var mat=camera<?php echo $id?>.matrix.elements;
                                original_roll<?php echo $id?> =-Math.atan2(-mat[2],mat[6]);
	                            
	                            camera<?php echo $id?>.up=new THREE.Vector4(0,0,1,0);
	                            camera<?php echo $id?>.lookAt(center<?php echo $id?>);
                                camera<?php echo $id?>.rotateZ(original_roll<?php echo $id?>);
                                
	                            function drag<?php echo $id?>(deltaX, deltaY) {
		                            var radPerPixel = (Math.PI / canvasElm<?php echo $id?>.clientWidth * 2),
		                                deltaPhi = radPerPixel * deltaX,
		                                deltaTheta = radPerPixel * deltaY,
		                                pos = camera<?php echo $id?>.position.sub(center<?php echo $id?>),
		                                radius = pos.length(),
		                                theta = Math.acos(pos.z / radius),
		                                phi = Math.atan2(pos.y, pos.x);

		                            // Subtract deltaTheta and deltaPhi
		                            theta = Math.min(Math.max(theta - deltaTheta, 0), Math.PI);
		                            phi -= deltaPhi;

		                            // Turn back into Cartesian coordinates
		                            pos.x = radius * Math.sin(theta) * Math.cos(phi);
		                            pos.y = radius * Math.sin(theta) * Math.sin(phi);
		                            pos.z = radius * Math.cos(theta);
                                    
		                            camera<?php echo $id?>.position.add(center<?php echo $id?>);
		                            camera<?php echo $id?>.lookAt(center<?php echo $id?>);
		                            camera<?php echo $id?>.rotateZ(original_roll<?php echo $id?>);
	                            }
		                        
		                        <?php if(!$lock_center){?>
	                            function move<?php echo $id?>(deltaX, deltaY) {
			                        if ( camera<?php echo $id?>.isPerspectiveCamera ) {
				                        var position = camera<?php echo $id?>.position;
				                        var pos = camera<?php echo $id?>.position.sub(center<?php echo $id?>);
		                                var targetDistance = pos.length();
		                                camera<?php echo $id?>.position.add(center<?php echo $id?>);
                                        var v = new THREE.Vector3();

				                        targetDistance *= Math.tan( ( camera<?php echo $id?>.fov / 2 ) * Math.PI / 180.0 );
                                        
                                        v.setFromMatrixColumn( camera<?php echo $id?>.matrix, 0 );
                                        v.multiplyScalar( - 2 * deltaX * targetDistance / canvasElm<?php echo $id?>.clientHeight);
				                        camera<?php echo $id?>.position.add(v);
				                        center<?php echo $id?>.add( v );
				                        
				                        v.setFromMatrixColumn( camera<?php echo $id?>.matrix, 1 );
                                        v.multiplyScalar( 2 * deltaY * targetDistance / canvasElm<?php echo $id?>.clientHeight);
                                        camera<?php echo $id?>.position.add(v);
                                        center<?php echo $id?>.add( v );
			                        } else if ( camera<?php echo $id?>.isOrthographicCamera ) {
			                            var v = new THREE.Vector3();
			                            v.setFromMatrixColumn( camera<?php echo $id?>.matrix, 0 );
                                        v.multiplyScalar(deltaX * ( camera<?php echo $id?>.right - camera<?php echo $id?>.left ) / camera<?php echo $id?>.zoom / canvasElm<?php echo $id?>.clientWidth);
                                        camera<?php echo $id?>.position.add(v);
				                        center<?php echo $id?>.add( v );
				                        
				                        v.setFromMatrixColumn( camera<?php echo $id?>.matrix, 1 );
                                        v.multiplyScalar(deltaY * ( camera<?php echo $id?>.top - camera<?php echo $id?>.bottom ) / camera<?php echo $id?>.zoom / canvasElm<?php echo $id?>.clientHeight);
                                        camera<?php echo $id?>.position.add(v);
                                        center<?php echo $id?>.add( v );
			                        }
	                            }
	                            <?php } ?>
	                            
	                            function zoom<?php echo $id?>(deltaX, deltaY) {
                                    camera<?php echo $id?>.position.sub(center<?php echo $id?>).multiplyScalar((-deltaX+deltaY)/2*0.01+1).add(center<?php echo $id?>);
	                            }

	                            Controls.addMouseHandler(renderer<?php echo $id?>.domElement, drag<?php echo $id?>, <?php echo $lock_center?'null':('move'.$id)?>,zoom<?php echo $id?>);
                            });
                            window.addEventListener("resize", function(){
			                    canvasElm<?php echo $id?>.style.width='100%';
			                    canvasElm<?php echo $id?>.style.height='100%';
                                camera<?php echo $id?>.aspect = canvasElm<?php echo $id?>.clientWidth / canvasElm<?php echo $id?>.clientHeight;
                                camera<?php echo $id?>.updateProjectionMatrix();
                                renderer<?php echo $id?>.setSize(canvasElm<?php echo $id?>.clientWidth, canvasElm<?php echo $id?>.clientHeight);
                                animate<?php echo $id?>();
			                }, false);
                    }
                    
			        
			        
			        function animate<?php echo $id?>() {
			            requestAnimationFrame( animate<?php echo $id?> );
                        if (mixer<?php echo $id?> != null) {
                            var delta = clock<?php echo $id?>.getDelta();
                            
                            mixer<?php echo $id?>.update(delta);
                            
                        };
                        
                        renderer<?php echo $id?>.render( scene<?php echo $id?>, camera<?php echo $id?> );
			        };
			        
                    loadScene<?php echo $id?>();
			        
                </script>
                
        <?php
        if(!$is_background){
            if(!$inline){
                echo $this->MakeMainContentEnd();
                ?>
                <?php if($hooked || !$this->AfterPassage3D) {?>
                    <?php echo "<!-- main_begin -->";?><div class='the_body'>
                    <?php 
                    $this->MainContentAlreadyBegun=True;
                }
                if($hooked) echo '<div class="main_content" style="'.($this->BackgroundSemi?"background-color:".$this->csemiwhite.";":"").'"><div>';
            } 
            if($hang){
                ?>
                </div>
                <?php
            }
        }//not background
        ?>
            
        <?php
    }
    
    function Make3DContent(){
        if(!isset($this->SceneList[0])) return null;
        $i=0;
        
        ob_start();
        foreach ($this->SceneList as $sc){
            if ((isset($sc['mode']) && ($sc['mode']=='Inline' && isset($sc['hook'])))||
                (isset($sc['hook']))){
                $i++;
                continue;
            }
            if($sc['mode']=='Background'){
                $this->BackgroundSemi = True;
            }
            $this->Make3DContentActual($sc,False,$i);
            $i++;
        }
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
    function InsertMagicSeparator($Content){
        $Inserts = "";
        $layout = $this->GetAdditionalLayout();
        if($layout!="Gallery"){
            ob_start();
            $this->MakeMainContentEnd();
            $this->MakeMainContentBegin(0);
            $Inserts = ob_get_contents();
            ob_end_clean();
        }
        return preg_replace('/(<p>\s*={3,}\s*<\/p>)/U',$Inserts,$Content);
    }
    function Insert3DContent($Content){
        if(!isset($this->SceneList[0])) return $Content;
        $i=0;
        foreach ($this->SceneList as $sc){
            if (!isset($sc['hook'])){
                $i++;
                continue;
            }

            ob_start();
            $this->Make3DContentActual($sc,True,$i);
            $Inserts = ob_get_contents();
            ob_end_clean();
                
            $split = preg_split('/(<h[0-6]>'.$sc['hook'].'<\/h[0-6]>)/U',$Content,3,PREG_SPLIT_DELIM_CAPTURE);
            if(count($split)>2){    
                if(isset($sc['hook_before'])&&$sc['hook_before']!=0) $Content = $split[0].$Inserts.$split[1].$split[2];
                else $Content = $split[0].$split[1].$Inserts.$split[2];
            }else{
                $Content.=$Inserts;
            }
            $i++;
        }
        return $Content;
    }
    
    function Make2DTile($file,$note,$max,$align,$i){
    ?>
        <td class='adaptive_column_container' style='padding:0px; text-align:<?php echo $align; ?>; position:relative;'>
            <img id='BlockImage<?php echo $i.'_'.$this->unique_item_count; ?>' src='<?php echo $this->InterlinkPath().'/'.$file?>' style="<?php echo $max?'max-height:unset;width:100%;':'max-width:100%;' ?>display:inline-block;" >
            <?php if($note!=''){ ?>
                <div style='position:absolute; left:0; right:0; top:0; bottom:0; text-align:center;'>
                    <div class='plain_block inline_p narrow_content' style='position:absolute; bottom:10px; z-index:1;transform: translate(-50%, -10px);'><?php echo $this->HTMLFromMarkdown($note); ?></div>
                </div>
            <?php } ?>
        </td>
    <?php
    }
    
    function Make2DContentActual($sc,$hooked){
        $expanded  =       (isset($sc['expand'])&&$sc['expand']!=0);
        $no_padding =      (isset($sc['padding'])&&$sc['padding']==0);
        $inline =          (isset($sc['mode'])&&$sc['mode']=='Inline'&&$hooked);
        $hook = $hooked;
        $is_background =   (isset($sc['mode'])&&$sc['mode']=='Background');
        $click_zoom   =    (isset($sc['click_zoom'])&&$sc['click_zoom']!=0);
        $max_out      =    (isset($sc['max_out'])&&$sc['max_out']!=0);
        $note         =    isset($sc['note'])?$sc['note']:'';
        $note2        =    isset($sc['note2'])?$sc['note2']:'';
        $note3        =    isset($sc['note3'])?$sc['note3']:'';
        $note4        =    isset($sc['note4'])?$sc['note4']:'';
        $note5        =    isset($sc['note5'])?$sc['note5']:'';
        $same_width   =    (isset($sc['same_width'])&&$sc['same_width']!=0);
        $file_count = (isset($sc['file'])?1:0)+
                      (isset($sc['file2'])?1:0)+
                      (isset($sc['file3'])?1:0)+
                      (isset($sc['file4'])?1:0)+
                      (isset($sc['file5'])?1:0);
        
        if(!$is_background){
            if(!$inline){
                if($hooked) echo '</div></div>';
                ?>
                </div>
                <div class='the_body' style="<?php echo $expanded?'width:calc(100% - 20px);':''?>">
                    <div class='main_content' style="<?php echo $no_padding?'padding:0px;':''?> <?php echo $this->BackgroundSemi?"background-color:rgba(255,255,255,0.95);":""?>">
                         <div>
            <?php } 
        }// not background
        ?>
                
                <div class="<?php echo $is_background?'box_complete_background':($expanded?'block_image_expanded':'block_image_normal')?>">
                    <?php if ($file_count>1){?>
   
                        <table style='margin:0px auto; <?php echo $same_width? "table-layout: fixed;":""; ?>'>
                        <?php if (isset($sc['file']))  { $this->Make2DTile($sc['file'], $note, $max_out,(($same_width||$file_count>2)?'center':'right'),1); } ?>
                        <?php if (isset($sc['file2'])) { $this->Make2DTile($sc['file2'],$note2,$max_out,(($same_width||$file_count>2)?'center':'left'),2); } ?>
                        <?php if (isset($sc['file3'])) { $this->Make2DTile($sc['file3'],$note3,$max_out,(($same_width||$file_count>2)?'center':'left'),3); } ?>
                        <?php if (isset($sc['file4'])) { $this->Make2DTile($sc['file4'],$note4,$max_out,(($same_width||$file_count>2)?'center':'left'),4); } ?>
                        <?php if (isset($sc['file5'])) { $this->Make2DTile($sc['file5'],$note5,$max_out,(($same_width||$file_count>2)?'center':'left'),5); } ?>
                        </table>
                        
                    <?php }else{ ?>
                        <?php if($sc['TYPE']=='IMAGE'){?>
                            <img id='BlockImage1_<?php echo $this->unique_item_count; ?>' src='<?php echo $this->InterlinkPath().'/'.$sc['file']?>' style="<?php echo $max_out?'max-height:unset;width:100%;':'max-width:100%;' ?>">
                        <?php }else{ //video ?>
                            <video <?php echo $is_background?' autoplay="autoplay" ':' controls ' ?> 
                                    id='BlockImage1_<?php echo $this->unique_item_count; ?>' src='<?php echo $this->InterlinkPath().'/'.$sc['file']?>' style="<?php echo $max_out?'max-height:unset;width:100%;':'max-width:100%;' ?>">
                        <?php } ?>
                        <?php if($note!=''){ ?>
                            <div style='position:absolute; left:0; right:0; top:0; bottom:0; text-align:center;'>
                                <div class='plain_block inline_p narrow_content' style='position:absolute; bottom:10px; z-index:1;transform: translate(-50%, -10px);'><?php echo $this->HTMLFromMarkdown($note); ?></div>
                            </div>
                        <?php } ?>
                    <?php }?>
                    <div id='BlockImageCover_<?php echo $this->unique_item_count; ?>' style='position:absolute;top:0px;left:0px;right:0px;left:0px;height:100%;'>
                    </div>
                </div>
                
                <?php if ($click_zoom && !$max_out){ ?>
                <script>
                    image1=document.getElementById('BlockImage1_<?php echo $this->unique_item_count; ?>');
                    image2=document.getElementById('BlockImage2_<?php echo $this->unique_item_count; ?>');
                    document.getElementById('BlockImageCover_<?php echo $this->unique_item_count; ?>').addEventListener("click",function(){
                        if(image1) image1.style.maxHeight = image1.style.maxHeight=='100vh' ? 'unset' : '100vh'; 
                        if(image2) image2.style.maxHeight = image2.style.maxHeight=='100vh' ? 'unset' : '100vh'; 
                    });
                </script>
                <?php } ?>
        <?php
        $this->unique_item_count+=1;
        if(!$is_background){
            if(!$inline){
                echo $this->MakeMainContentEnd();
                ?>
                <?php if($hooked || !$this->AfterPassage2D) {?>
                    <?php echo "<!-- main_begin -->";?><div class='the_body'>
                    <?php 
                    $this->MainContentAlreadyBegun=True;
                } ?>
                <?php 
                if($hooked) echo '<div class="main_content" style="'.($this->BackgroundSemi?"background-color:rgba(255,255,255,0.95);":"").'"><div>';
            } 
        }//not background
        ?>
            
        <?php
    }
    
    function Make2DContent(){
        if(!isset($this->BlockImageList[0])) return null;
        $i=0;
        ob_start();
        foreach ($this->BlockImageList as $sc){
            if ((isset($sc['mode']) && ($sc['mode']=='Inline' && isset($sc['hook'])))||
                (isset($sc['hook']))){
                $i++;
                continue;
            }
            if($sc['mode']=='Background'){
                $this->BackgroundSemi = True; 
            }
            $this->Make2DContentActual($sc,False);
            $i++;
        }
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
    function Insert2DContent($Content){
        if(!isset($this->BlockImageList[0])) return $Content;
        $i=0;
        foreach ($this->BlockImageList as $sc){
        
            if (!isset($sc['hook'])){
                $i++;
                continue;
            }

            ob_start();
            $this->Make2DContentActual($sc,True);
            $Inserts = ob_get_contents();
            ob_end_clean();
                
            $split = preg_split('/(<h[0-6]>'.$sc['hook'].'<\/h[0-6]>)/U',$Content,3,PREG_SPLIT_DELIM_CAPTURE);
            if(count($split)>2){    
                if(isset($sc['hook_before'])&&$sc['hook_before']!=0) $Content = $split[0].$Inserts.$split[1].$split[2];
                else $Content = $split[0].$split[1].$Inserts.$split[2];
            }else{
                $Content.=$Inserts;
            }
            $i++;
        }
        return $Content;
    }
    function HandleInsertsBeforePassage($Content2D,$Content3D){
        $content="";
        if(!$this->AfterPassage2D){
            $content.=$Content2D;
        }
        if(!$this->AfterPassage3D){
            $content.=$Content3D;
        }
        return $content;
    }
    function HandleInsertsAfterPassage($Content2D,$Content3D){
        $content="";
        if($this->AfterPassage2D){
            $content.=$Content2D;
        }
        if($this->AfterPassage3D){
            $content.=$Content3D;
        }
        return $content;
    }
    
    function GetSmallQuoteName(){
        return $this->SmallQuoteName;
    }
    
    function TryExtractTaskManager($override,$check_only){
        $actual = $override?$override:$this->PagePath;
        
        if(!is_file($actual) || !is_readable($actual)) return False;
        
        $f = fopen($actual,'r');
        if(($size=filesize($actual))==0) return False;
        $ConfContent = fread($f,$size);
        $Conf = $this->ParseMarkdownConfig($ConfContent);
        fclose($f);
        $b = $this->GetBlock($Conf,"EventTracker");
        $list=[];
        if($b){
            if($check_only) return True;
            $this->IsTaskManager = 1;
            $i=0;
            while($this->GetLineByNamesN($Conf,"EventTracker",'Entry',$i)!==Null){
                $item['target'] = $this->GetArgumentByNamesN($Conf,"EventTracker",'Entry',$i,'Target');
                $item['past_count'] = $this->GetArgumentByNamesN($Conf,"EventTracker",'Entry',$i,'PastCount'); if(!$item['past_count']) $item['past_count'] = 3;
                $list[] = $item;
                $i++;
            }
            $this->TaskManagerEntries = $list;
            $this->ReadTaskFolderDescription(NULL,$actual,$this->TaskManagerTitle, $unused, $unused);
            return True;
        }else{
            return False;
        }
    }
    function IsTaskManager(){
        return $this->IsTaskManager;
    }
    function IsStatsDisplay(){
        return $this->IsStatsDisplay;
    }

    function SortTaskList(&$unfinished_items, &$finished_items, &$active_items, $return_new_combined, $oldest_first, $use_end_time){
        $time_entry = $use_end_time?"time_end":"time_begin";
        $callback = function ($a,$b) use($time_entry) {
            return intval($this->TaskTimeDifferences($a[$time_entry], $b[$time_entry]))<=0?-1:1;
        };
        if(isset($unfinished_items)) { usort($unfinished_items,$callback); if($oldest_first) $unfinished_items=array_reverse($unfinished_items); }
        if(isset($finished_items)) { usort($finished_items,$callback); if($oldest_first) $finished_items=array_reverse($finished_items); }
        if(isset($active_items)) { usort($active_items,$callback); if($oldest_first) $active_items=array_reverse($active_items); }
    }
    function ReadTaskFolderDescription($folder, $override_file, &$group_name, &$old_task_days, &$ancient_task_days){
        $f = $override_file?$override_file:$folder.'/index.md';
        if(is_readable($f))
            $fi = fopen($f,"r");
        if(isset($fi)){
            $content = fread($fi,filesize($f));
            fclose($fi);
            $Conf = $this->ParseMarkdownConfig($content);
            $b = $this->GetBlock($Conf,"EventTracker");
            $list=[];
            if($b){
                $name = $this->GetLineValueByNames($Conf,"EventTracker","GroupName");
                if(isset($name)) $group_name = $name;
                $old_d = $this->GetLineValueByNames($Conf,"EventTracker","OldDays");
                $old_task_days = $old_d?$old_d:10000;
                $ancient_d = $this->GetLineValueByNames($Conf,"EventTracker","AncientDays");
                $ancient_task_days = $ancient_d?$ancient_d:10000;
                return;
            }
        }
        $group_name = pathinfo($folder,PATHINFO_BASENAME);
    }
    function MakeTaskList(){
        $i=0;
        $unfinished_items=[];
        $finished_items=[];
        $active_items=  [];
        $groups=[];
        
        if(!$this->IsTaskManager) return;
        
        if($this->TaskManagerEntries==Null){
            $item['target'] = $this->InterlinkPath();
            $item['past_count'] = 1000;
            $this->TaskManagerEntries[] = $item;
            $this->TaskManagerSelf = 1;
        }
        foreach ($this->TaskManagerEntries as $item){
            $target = $item['target'];
            $pc = $item['past_count'];
            $folder_title = NULL;
            ?>
            <div class='the_body'>

                    <div style='text-align:right;'>
                        
                    </div>

                <?php
                    $this->FileNameList=[];
                    $path = $target;
                    if(is_readable($path) && is_dir($path)){
                        $current_dir = opendir($path);
                        while(($file = readdir($current_dir)) !== false) {
                            $sub_dir = $path . '/' . $file;
                            if($file == '.' || $file == '..' || $file=='index.md') {
                                continue;
                            } else if(!is_dir($sub_dir)){
                                $ext=pathinfo($file,PATHINFO_EXTENSION);
                                if($ext=='md')
                                    $this->FileNameList[] = $file;
                            }
                        }
                        if($this->FileNameList)     sort($this->FileNameList);
                        
                        $this->ReadTaskItems($path, $this->FileNameList, $pc, date('Y'), date('m'), date('d'), $unfinished_items, $finished_items, $active_items, $have_delayed);
                        
                        $this->ReadTaskFolderDescription($path, NULL,$folder_title, $old_count, $ancient_count);
                        $folder_item['title'] = $folder_title;
                        $folder_item['path'] = $path;
                        $folder_item['past_count']=$pc;
                        $folder_item['old_count']=$old_count;
                        $folder_item['ancient_count']=$ancient_count;
                        $groups[] = $folder_item;
                    }else{
                        $folder_item['title'] = '无法读取';
                        $folder_item['path'] = $path;
                        $folder_item['past_count']=1;
                        $folder_item['old_count']=100000;
                        $folder_item['ancient_count']=100000;
                        $groups[] = $folder_item;
                    }
                ?>
            </div>
            <?php
            $i++;
        }?>
        <div class='the_body'>
        <?php
            $this->SortTaskList($unfinished_items, $finished_items, $active_items, 0, 0, 0);
            $this->MakeTaskGroupAdditional(NULL, 30,$unfinished_items, $finished_items, $active_items, $have_delayed);
            $this->TaskManagerGroups = $groups;
        ?>
        </div>
        <?php
    }
    
    function InsertSideNotes($html){
        global $sn_i;
        $sn_i=0;
        $new = preg_replace_callback('/<p>(!!)([\s\S]*)<\/p>/Uu',
                                     function($matches){
                                        return '<div class="inline_notes_outer halftone4"> <div class="inline_notes_content">'.
                                               $matches[2].
                                               '</div> </div>';
                                     },$html);
        $new = preg_replace_callback('/<p>(!&gt;)([\s\S]*)<\/p>/Uu',
                                     function($matches){
                                        global $sn_i;
                                        $ret = '<div class="sidenotes_content"> <div id="sn_content_'.$sn_i.'"class="inline_notes_content sidenotes_position" onclick="sn_hide_'.$sn_i.'()">'.
                                               $matches[2].
                                               '</div> <div id="sn_expand_'.$sn_i.'"class="inline_notes_content sidenotes_expander" onclick="sn_show_'.$sn_i.'()">...</div></div>'.
                                               '<script>
                                               function sn_hide_'.$sn_i.'(){
                                                 c = document.getElementById("sn_content_'.$sn_i.'"); 
                                                 e = document.getElementById("sn_expand_'.$sn_i.'"); 
                                                 c.style.display = "none";
                                                 e.style.display = "block";
                                               }
                                               function sn_show_'.$sn_i.'(){
                                                 c = document.getElementById("sn_content_'.$sn_i.'"); 
                                                 e = document.getElementById("sn_expand_'.$sn_i.'"); 
                                                 c.style.display = "block";
                                                 e.style.display = "none";
                                               }
                                               </script>';
                                        $sn_i++;
                                        return $ret;
                                     },$new);
        return $new;
    }
    
    function MakeSettings(){
        $Title='LAMDWIKI';
        $Footnote='';
        ?>
            <div class='btn' onclick='location.href="?page=<?php echo $this->PagePath;?>"'><?php echo $this->FROM_ZH("退出"); ?></div>
            <form method="post" id='settings_form' style='display:none;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=settings';?>"></form>
            <h1><?php echo $this->FROM_ZH("设置中心"); ?></h1>
            <a id='ButtonWebsiteSettings' style='font-weight:bold'><?php echo $this->FROM_ZH("网站信息"); ?></a>
            <a id='Button301Settings'><?php echo $this->FROM_ZH("链接跳转项目"); ?></a>
            <a id='ButtonMailSettings'><?php echo $this->FROM_ZH("站点邮件"); ?></a>
            <a id='ButtonAdminSettings'><?php echo $this->FROM_ZH("管理员"); ?></a>
            <div class='inline_height_spacer'></div>
            <div id='TabWebsiteSettings'>
            
                <b><?php echo $this->FROM_ZH("网站标题"); ?></b><br />
                <div id="wrap_settings_website_title"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_website_title' name='settings_website_title' form='settings_form' value='<?php echo $this->Title ?>' />
                中文</div>
                <div id="wrap_settings_website_title_en"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_website_title_en' name='settings_website_title_en' form='settings_form' value='<?php echo $this->TitleEN ?>' />
                English</div>
                
                <br /><b><?php echo $this->FROM_ZH("标签显示标题"); ?></b><br />
                <div id="wrap_settings_website_display_title"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_website_display_title' name='settings_website_display_title' form='settings_form' value='<?php echo $this->StringTitle ?>' />
                中文</div>
                <div id="wrap_settings_website_display_title_en"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_website_display_title_en' name='settings_website_display_title_en' form='settings_form' value='<?php echo $this->StringTitleEN ?>' />
                English</div>
                
                <br /><b><?php echo $this->FROM_ZH("页脚附加文字"); ?></b><br />
                <div id="wrap_settings_footer_notes"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_footer_notes' name='settings_footer_notes' form='settings_form' value='<?php echo $this->Footnote ?>' />
                中文</div>
                <div id="wrap_settings_footer_notes_en"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_footer_notes_en' name='settings_footer_notes_en' form='settings_form' value='<?php echo $this->FootnoteEN ?>' />
                English</div>
                <br />
                
                <div id="wrap_settings_small_quote_name"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_small_quote_name' name='settings_small_quote_name' form='settings_form' value='<?php echo $this->SmallQuoteName ?>' />
                <?php echo $this->FROM_ZH("“我说”名片抬头文字"); ?></div>
                
                <br />
                <div id="wrap_settings_tracker_file"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_tracker_file' name='settings_tracker_file' form='settings_form' value='<?php echo $this->TrackerFile ?>' />
                <?php echo $this->FROM_ZH("站点事件跟踪器"); ?></div>
                
                <div id="wrap_settings_tracker_invert">
                <a id="ButtonTaskNormal"><?php echo $this->TaskHighlightInvert?"":"<b><u>" ?><?php echo $this->FROM_ZH("正常"); ?><?php echo $this->TaskHighlightInvert?"":"</u></b>" ?></a>
                <a id="ButtonTaskInvert"><?php echo $this->TaskHighlightInvert?"<b><u>":"" ?><?php echo $this->FROM_ZH("反转"); ?><?php echo $this->TaskHighlightInvert?"</u></b>":"" ?></a>
                <input style='display:none;' class='string_input no_horizon_margin' type='text' id='settings_task_highlight_invert' name='settings_task_highlight_invert' form='settings_form' value='<?php echo $this->TaskHighlightInvert?"True":"" ?>' />
                <?php echo $this->FROM_ZH("事件高亮显示"); ?>
                
                <br />
                <div id="wrap_settings_stats_file"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_stats_file' name='settings_stats_file' form='settings_form' value='<?php echo $this->StatsFile ?>' />
                <?php echo $this->FROM_ZH("统计信息文件"); ?></div>
                
                </div>
            </div>

            <div id='Tab301Settings' style='display:none'>
                <?php echo $this->FROM_ZH("自动重定向的链接"); ?>
                <?php if(isset($this->List301)) foreach($this->List301 as $item){ ?>
                    <div>
                        <div style='float:right;width:50%'>>>>&nbsp;<?php echo $item['to']; ?></div>
                        <?php echo $item['from']; ?>
                    </div>
                <?php } ?>
                <a href='?page=la_config.md&operation=edit'>编辑la_config.md</a>&nbsp;以详细配置。
            </div>
            
            <div id='TabMailSettings' style='display:none'>
                <b><?php echo $this->FROM_ZH("站点邮件设置"); ?></b>
                
                <div id="wrap_settings_mail_host"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_mail_host' name='settings_mail_host' form='settings_form' value='<?php echo $this->MailHost ?>' />
                <?php echo $this->FROM_ZH("SMTP发信主机"); ?></div>
                <div id="wrap_settings_mail_port"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_mail_port' name='settings_mail_port' form='settings_form' value='<?php echo $this->MailPort ?>' />
                <?php echo $this->FROM_ZH("端口"); ?></div>
                <div id="wrap_settings_mail_user"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_mail_user' name='settings_mail_user' form='settings_form' value='<?php echo $this->MailUser ?>' />
                <?php echo $this->FROM_ZH("发信邮箱"); ?></div>
                <div id="wrap_settings_mail_password"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_mail_password' name='settings_mail_password' form='settings_form' value='<?php echo $this->MailPassword ?>' />
                <?php echo $this->FROM_ZH("发信密码"); ?></div>
                
                <br /><b><?php echo $this->FROM_ZH("邮件标题"); ?></b>
                <div id="wrap_settings_mail_title"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_mail_title' name='settings_mail_title' form='settings_form' value='<?php echo $this->MailTitle ?>' />
                <?php echo $this->FROM_ZH("中文"); ?></div>
                <div id="wrap_settings_mail_title_en"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_mail_title_en' name='settings_mail_title_en' form='settings_form' value='<?php echo $this->MailTitleEN ?>' />
                <?php echo $this->FROM_ZH("English"); ?></div>
                
                <br /><b><?php echo $this->FROM_ZH("脚注"); ?></b>
                <div id="wrap_settings_mail_foot"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_mail_foot' name='settings_mail_foot' form='settings_form' value='<?php echo $this->MailFoot ?>' />
                <?php echo $this->FROM_ZH("中文"); ?></div>
                <div id="wrap_settings_mail_foot_en"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_mail_foot_en' name='settings_mail_foot_en' form='settings_form' value='<?php echo $this->MailFootEN ?>' />
                <?php echo $this->FROM_ZH("English"); ?></div>
                
            </div>
            
            <div id='TabAdminSettings' style='display:none'>
                <div id="wrap_settings_admin_display"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_admin_display' name='settings_admin_display' form='settings_form' value='<?php echo $this->UserDisplayName ?>' />
                <?php echo $this->FROM_ZH("修改账户昵称"); ?></div>
                <br />
                <div id="wrap_settings_admin_id"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_admin_id' name='settings_admin_id' form='settings_form' />
                <?php echo $this->FROM_ZH("重设管理账户名"); ?></div>
                <div id="wrap_settings_admin_password"><input onInput="la_mark_div_highlight('wrap_'+this.id);" class='string_input no_horizon_margin' type='text' id='settings_admin_password' name='settings_admin_password' form='settings_form' />
                <?php echo $this->FROM_ZH("重设管理密码"); ?></div>
                
            </div>
            
            <hr />
            <div class='inline_block_height_spacer'></div>
            <input class='btn form_btn' type='submit' value='<?php echo $this->FROM_ZH("保存所有更改"); ?>' name="settings_button_confirm" form='settings_form' />
            <div style="clear:both;"></div>
            <script>
                var btn_website = document.getElementById("ButtonWebsiteSettings");
                var btn_301 = document.getElementById("Button301Settings");
                var btn_mail = document.getElementById("ButtonMailSettings");
                var btn_admin = document.getElementById("ButtonAdminSettings");
                var btn_task_normal = document.getElementById("ButtonTaskNormal");
                var btn_task_invert = document.getElementById("ButtonTaskInvert");
                var div_website = document.getElementById("TabWebsiteSettings");
                var div_301 = document.getElementById("Tab301Settings");
                var div_mail = document.getElementById("TabMailSettings");
                var div_admin = document.getElementById("TabAdminSettings");
                var field_task_invert = document.getElementById("settings_task_highlight_invert");
                btn_website.addEventListener("click", function() {
                    div_website.style.cssText = 'display:block';
                    div_301.style.cssText = 'display:none';
                    div_mail.style.cssText = 'display:none';
                    div_admin.style.cssText = 'display:none';
                    btn_website.style.cssText = 'font-weight:bold;';
                    btn_301.style.cssText = '';
                    btn_mail.style.cssText = '';
                    btn_admin.style.cssText = '';
                }); 
                btn_301.addEventListener("click", function() {
                    div_website.style.cssText = 'display:none';
                    div_301.style.cssText = 'display:block';
                    div_mail.style.cssText = 'display:none';
                    div_admin.style.cssText = 'display:none';
                    btn_website.style.cssText = '';
                    btn_301.style.cssText = 'font-weight:bold;';
                    btn_mail.style.cssText = '';
                    btn_admin.style.cssText = '';
                });
                btn_admin.addEventListener("click", function() {
                    div_website.style.cssText = 'display:none';
                    div_301.style.cssText = 'display:none';
                    div_mail.style.cssText = 'display:none';
                    div_admin.style.cssText = 'display:block';
                    btn_website.style.cssText = '';
                    btn_301.style.cssText = '';
                    btn_mail.style.cssText = '';
                    btn_admin.style.cssText = 'font-weight:bold;';
                });
                btn_mail.addEventListener("click", function() {
                    div_website.style.cssText = 'display:none';
                    div_301.style.cssText = 'display:none';
                    div_mail.style.cssText = 'display:block';
                    div_admin.style.cssText = 'display:none';
                    btn_website.style.cssText = '';
                    btn_301.style.cssText = '';
                    btn_mail.style.cssText = 'font-weight:bold;';
                    btn_admin.style.cssText = '';
                });
                btn_task_normal.addEventListener("click", function() {
                    
                    field_task_invert.value="False";
                    btn_task_normal.innerHTML = "<b><u><?php echo $this->FROM_ZH('正常'); ?></u></b>";
                    btn_task_invert.innerHTML = "<?php echo $this->FROM_ZH('反转'); ?>";
                    la_mark_div_highlight('wrap_settings_tracker_invert');
                });
                btn_task_invert.addEventListener("click", function() {
                    field_task_invert.value="True";
                    btn_task_normal.innerHTML = "<?php echo $this->FROM_ZH('正常'); ?>";
                    btn_task_invert.innerHTML = "<b><u><?php echo $this->FROM_ZH('反转'); ?></u></b>";
                    la_mark_div_highlight('wrap_settings_tracker_invert');
                });
            </script>
        <?php
    }
    function MakeWebsiteStatsContent(){
        $this->DoUpdateStatsFile();
        
        $f = file_get_contents($this->StatsFile);
        $update_history = [];
        $max_count = 0;
        preg_match_all("/([0-9]{4}-[0-9]{2}-[0-9]{2}):[\s]*(.*)\R\R/Uu", $f, $matches, PREG_SET_ORDER);
        if(isset($matches[0])) foreach($matches as $match){
            $item['date'] = $match[1];
            if(preg_match("/\[[\s]*([0-9]*)[\s]*Updates[\s]*\]/",$match[2],$upd)){
                $item['updates'] = $upd[1];
            }else{
                $item['updates'] = 0;
            }
            if($item['updates'] > $max_count){
                
                $max_count = $item['updates'];
            }
            $update_history[] = $item;
        }else{
            return;
        }
        $update_history = array_reverse($update_history);
        ?>
            <h1><?php echo $this->FROM_ZH("状态"); ?></h1>
            <p><?php echo $this->FROM_ZH("有记录以来的每日更新文章数量"); ?></p>
            <table><tbody>
            <tr>
            <td style="white-space:nowrap;"><?php echo $this->FROM_ZH("日期"); ?></td>
            <td style="white-space:nowrap;"><?php echo $this->FROM_ZH("更新数"); ?></td>
            <td style="text-align:right; width:100%;" ><?php echo $max_count; ?></td>
            </tr>
            <?php foreach($update_history as $day){ ?>
                <tr>
                <td><?php echo $day['date'] ?></td>
                <td style="text-align:right;"><?php echo $day['updates'] ?></td>
                <td>
                    <?php $width = floatval(intval($day['updates']))/$max_count*100; ?>
                    <div style="display:inline-block; background-color:<?php echo $this->cblack; ?>; width:<?php echo $width?>%;">&nbsp;</div>
                </td>
                </tr>
            <?php } ?>
            </tbody></table>
        <?php
    }
    function MakeLoginDiv(){
        ob_start();
        ?> 
    
        <?php if(!$this->IsTaskManager){?><div id='LoginPanel' class='top_panel' style='display:none;'>
        <?php }else{ ?><div id="task_manager_login" style="display:none;"><?php } ?>
            
            <?php if ($this->IsLoggedIn()) { ?>
                <?php if(!$this->IsTaskManager){ ?>
                    <a href='?page=<?php echo $this->PagePath;?>&operation=settings'><?php echo $this->FROM_ZH("网站设置")?></a>
                    <?php echo $this->FROM_ZH("查看为")?>
                    <a href='?page=<?php echo $this->PagePath;?>&set_translation=en'>English</a>
                    <a href='?page=<?php echo $this->PagePath;?>&set_translation=zh'>中文</a>
                    <div class="inline_height_spacer hidden_on_desktop"></div>
                    <a href='?page=<?php echo $this->PagePath;?>&theme=white'><?php echo $this->FROM_ZH("明亮")?></a>
                    <a href='?page=<?php echo $this->PagePath;?>&theme=black'><?php echo $this->FROM_ZH("夜间")?></a>
                <?php } ?>
            <?php } ?>
            
        
            <div class='login_half'>
        
                <?php
                if(!$this->IsLoggedIn()){
                    ?>
                    <?php if(!$this->IsTaskManager){ ?>
                        <div id='language_dialog'>
                            <h3 class = "inline_components" >Language/语言</h3>
                            <?php if (isset($_GET['static_generator'])){
                                $StaticLangEN = $this->ChooseLanguageAppendix($this->PagePath,'en');
                                $StaticLangZH = $this->ChooseLanguageAppendix($this->PagePath,'zh');
                                ?>
                                <a href='?page=<?php echo $StaticLangEN; ?>'>English</a>
                                <a href='?page=<?php echo $StaticLangZH; ?>'>中文</a>
                                <?php
                            }else{ ?>
                                <a href='?page=<?php echo $this->PagePath;?>&set_translation=en'>English</a>
                                <a href='?page=<?php echo $this->PagePath;?>&set_translation=zh'>中文</a>
                            <?php } ?>
                            <div class="inline_height_spacer"></div>
                            
                            <?php if($this->prefer_dark){ ?>
                                <p class = "inline_components" ><?php echo $this->FROM_ZH('在夜间模式') ?></p>
                                <a href='?page=<?php echo $this->PagePath;?>&theme=white'><?php echo $this->FROM_ZH('调成明亮') ?></a>
                            <?php }else{ ?>
                                <a href='?page=<?php echo $this->PagePath;?>&theme=black'><?php echo $this->FROM_ZH('进入夜间模式') ?></a>
                            <?php }?>
                        </div>
                    <?php } ?>
                    <?php 
                    if(!isset($_GET['static_generator'])){
                    ?>
                    <div id="login_again_dialog" style="display:none;">
                        <?php if($this->IsTaskManager){ ?>
                            <div class="inline_height_spacer"></div>
                        <?php } ?>
                        <form method = "post" action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath;?>" style='margin-bottom:10px;'>
                            <div class = "inline_components"><?php echo $this->FROM_ZH('用户名') ?>:</div>
                            <input class='string_input' type="text" id="username" name="username" style='margin-right:0px;'
                            value="<?php if(!empty($user_username)) {echo $user_username;} ?>" />
                            <br />
                            <div class='inline_components'><?php echo $this->FROM_ZH('密码') ?>:</div>
                            <input class='string_input' type="password" id="password" name="password" style='margin-right:0px;margin-bottom:15px;'/>
                            <br />
                            <input class='btn form_btn' style="float:right" type="submit" value="<?php echo $this->FROM_ZH('登录') ?>" name="button_login"/>
                        </form>
                    </div>
                    <?php
                    }else{
                        ?>
                        <div class='inline_block_height_spacer'></div>
                        <p>使用LaMDWiki静态生成器生成。</p>
                        <?php 
                    }
                }else{
                    if($this->IsTaskManager){ ?>
                        <div id="login_again_dialog" style="display:none;">
                        <div class="inline_height_spacer"></div>
                    <?php }
                    echo '<p class = "inline_components">'.$this->UserDisplayName.'</p>';
                    ?>
                    <input class='btn form_btn' type="button" name="logout" value="<?php echo $this->FROM_ZH('登出') ?>" onclick="location.href='<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath;?>&logout=True'" />
                    <?php if($this->IsTaskManager){ ?>
                        </div>
                    <?php } ?>
                    <?php
                }
                ?>
            </div> 
        </div>
        <?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
    
    function MakeBackButton(){
        $path = $this->InterlinkPath();
        
        if(preg_match('/index.*\.md$/',$this->PagePath)){
            if($path=='.') return;
            $upper = $this->GetInterlinkPath('..');
        }else{
            $upper = $path;
        }
        
        ?>
            <a href='?page=<?php echo $upper; ?>'><?php echo $this->FROM_ZH('上级') ?></a>
        <?php
    }
    
    function MakeHeaderQuickButtons(){
        $path = $this->InterlinkPath();
        $disp = $this->FolderDisplayAs($path)=='Timeline'?1:0;
        ?>
        <?php if(!$this->IsTaskManager){?><div id='HeaderQuickButtons'>
        <?php
            if(!$this->IsLoggedIn()){
                if($this->FolderShowListButton($path)){
                ?>  
                    <?php if (isset($_GET['static_generator'])){?>
                        <a class='btn' href="_la_list.html"><?php echo $this->FROM_ZH('列表') ?></a>
                    <?php }else{ ?>
                        <?php if (isset($_GET['operation']) && isset($_SERVER['HTTP_REFERER'])){ ?>
                            <a class='btn' href="<?php echo $_SERVER['HTTP_REFERER']; ?>"><?php echo $this->FROM_ZH('返回') ?></a>
                        <?php }else{ ?>
                            <a class='btn' href="?page=<?php echo $path ?><?php echo $disp?('&operation=timeline&folder='.$path):'&operation=tile' ?>"><?php echo $this->FROM_ZH('列表') ?></a>
                        <?php }?>
                    <?php } ?>
                    
                <?php
                }
                if(!isset($_GET['operation'])){
                    echo $this->MakeBackButton();
                }?>
                <div id="login_again_button" class='btn' style='display:none' onClick="la_toggle_login_again();"><?php echo $this->FROM_ZH('登录') ?></div>
                <div id='LoginToggle' class='btn' onClick="la_toggle_login_button()"><b>中En</b></div>
            <?php
            }else{
                if(!isset($_GET['static_generator'])){
                ?>
                <?php if(!isset($_GET['operation'])){
                    echo $this->MakeBackButton();
                }?>
                <div id='LoginToggle' class='btn'><?php echo $this->UserDisplayName ?></div>
                <?php if($this->FolderShowListButton($path)){ ?>
                    <?php if (isset($_GET['operation']) && isset($_SERVER['HTTP_REFERER'])){ ?>
                        <a class='btn' href="<?php echo $_SERVER['HTTP_REFERER']; ?>"><?php echo $this->FROM_ZH('返回') ?></a>
                    <?php }else{ ?>
                        <a class='btn' href="?page=<?php echo $path ?><?php echo $disp?('&operation=timeline&folder='.$path):'&operation=tile' ?>"><?php echo $this->FROM_ZH('列表') ?></a>
                    <?php }?>
                    
                <?php } ?>
                <a href="?page=<?php echo $this->PagePath?>&operation=list"><?php echo $this->FROM_ZH("管理"); ?></a> 
                <a href="?page=<?php echo $this->PagePath?>&operation=new"><?php echo $this->FROM_ZH("写文"); ?></a>
                <?php
                }
            }
            ?>
        </div>
        <?php }else{ //task manager header ?>
            <div style='float:right'>
                <div class="inline hidden_on_mobile">
                    <a>正常</a>
                    <a>总表</a>
                    <a>日历</a>
                    <?php if($this->prefer_dark){ ?>
                        <a href='?page=<?php echo $this->PagePath;?>&theme=white'><?php echo $this->FROM_ZH('调成明亮') ?></a>
                    <?php }else{ ?>
                        <a href='?page=<?php echo $this->PagePath;?>&theme=black'><?php echo $this->FROM_ZH('进入夜间') ?></a>
                    <?php }?>
                </div>                
                <span class="hidden_on_desktop_inline"><div id="login_again_button" class='btn' style='display:none' onClick="la_toggle_login_task_desktop();"><?php echo $this->IsLoggedIn()?$this->UserDisplayName:$this->FROM_ZH('登录') ?></div></span>
                <span class="hidden_on_desktop_inline"><div class='btn' onClick="la_toggle_login_task_mobile()">查看</div></span>
                <span class="hidden_on_mobile"><div class='btn' onClick="la_toggle_login_task_desktop()"><?php echo $this->IsLoggedIn()?$this->UserDisplayName:$this->FROM_ZH('登录')?></div></span>
            </div>
            <span class="hidden_on_desktop_inline">
                <div id="task_view_buttons" style="display:block;text-align:right;display:none;">
                    <div class="inline_height_spacer"></div>
                    <a>正常</a>
                    <a>总表</a>
                    <a>日历</a>
                    <?php if($this->prefer_dark){ ?>
                        <a href='?page=<?php echo $this->PagePath;?>&theme=white'><?php echo $this->FROM_ZH('调成明亮') ?></a>
                    <?php }else{ ?>
                        <a href='?page=<?php echo $this->PagePath;?>&theme=black'><?php echo $this->FROM_ZH('进入夜间') ?></a>
                    <?php }?>
                </div>
            </span>
        <?php }
        ?>
        <script>
        function la_toggle_login_again(){
            dialog = document.getElementById("login_again_dialog");
            lang = document.getElementById("language_dialog");
            dialog.style.display = dialog.style.display=="none"?"block":"none";
            lang.style.display = lang.style.display=="none"?"block":"none";
        }
        function la_toggle_login_button(){
            btn = document.getElementById("login_again_button");
            if(btn) btn.style.display = btn.style.display=="none"?"unset":"none";
        }
        function hide_login_uis(){
            again = document.getElementById("login_again_dialog");
            dialog = document.getElementById("task_manager_login");
            if(again)again.style.display = "none";
            dialog.style.display = "none";
        }
        function la_toggle_login_task_desktop(){
            again = document.getElementById("login_again_dialog");
            dialog = document.getElementById("task_manager_login");
            disp = (again?again:dialog).style.display=="none"?"block":"none";
            dialog.style.display = disp;
            header = document.getElementById("Header");
            footer = document.getElementById("task_manager_footer");
            if(disp=="block"){
                header.style.zIndex=100;
                footer.style.zIndex=10;
                la_show_modal_blocker();
            }else{
                header.style.zIndex="";
                footer.style.zIndex="";
                la_hide_modal_blocker();
            }
            if(again)again.style.display = disp;
            c = document.getElementById("task_navigation_container");
            c.style.display='none';
            h = document.getElementById("task_master_header_desktop");
            h.style.display='inline';
        }
        function la_toggle_login_task_mobile(){
            vb = document.getElementById("task_view_buttons");
            disp = vb.style.display=="none"?"block":"none";
            dialog = document.getElementById("task_manager_login");
            dialog.style.display = disp;
            vb.style.display = disp;
            
            btn = document.getElementById("login_again_button");
            if(btn) btn.style.display = disp=="block"?"unset":"none";
            
            mh = document.getElementById("task_master_header");
            mh.style.display = disp=="block"?"none":"unset";
            
            c = document.getElementById("task_navigation_container");
            c.style.display='none';
        }
        </script>
        <?php
    }
    function MakeNavigationBegin(){
        ?>
        <div class="navigation" id='Navigation'>
            <div class="hidden_on_desktop" >
                <table style="table-layout:fixed; text-align:center;"><tr>
                    <td><a href="?page=index.md" style='margin:0px;'><b>&#8962;&nbsp;<?php echo $this->FROM_ZH('首页') ?></b></a></td>
                    <?php if($this->Trackable){ ?><td><a href="?page=<?php echo $this->TrackerFile ?>" style='margin:0px;'><?php echo $this->FROM_ZH('跟踪') ?></a><?php } ?>
                </tr></table>
            </div>
            <div class='hidden_on_desktop block_height_spacer' ></div>
        <?php
    }
    function MakeNavigationEnd(){
        ?>
        </div>
        <?php
    }
    function IsNewsletterFolder($folder){
        if(is_readable($folder.'/subscribers.md')) return 1;
        return 0;
    }
    function ReadSubscribers($folder){
        $name = $folder.'/subscribers.md';
        $f = file_get_contents($name);
        
        preg_match_all("/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2}):[\s]*\[(.*)\][\s]*\[(.*)\][\s]*([^\s]*)\R\R/Uu", $f, $matches, PREG_SET_ORDER);
        
        $this->MailSubscribers = NULL;
        
        foreach ($matches as $match){
            if($match[7]!='CONFIRMED')
                continue;
            $item = NULL;
            $item['address']=$match[9];
            $item['language']=$match[8];
            $this->MailSubscribers[] = $item;
        }
        if($this->MailSubscribers==NULL) return 0;
        return count($this->MailSubscribers);
    }
    function EditSubscriberLanguage($folder,$mail_address,$language){
        $name = $folder.'/subscribers.md';
        $f = file_get_contents($name);
        $confirmed = 0;
        
        preg_match_all("/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2}):[\s]*\[(.*)\][\s]*\[(.*)\][\s]*([^\s]*)\R\R/Uu", $f, $matches, PREG_SET_ORDER); 
        
        $fi = fopen($name,'w');
        foreach($matches as $match){
            if($match[9]==$mail_address){
                fwrite($fi,$this->CurrentTimeReadable().': ['.$match[7].'] ['.$language.'] '.$mail_address.PHP_EOL.PHP_EOL);
                $confirmed = 1;
                continue;
            }
            fwrite($fi, $match[0]);
        }
        return $confirmed;
    }
    function ConfirmSubscriber($folder,$mail_address,$id,$remove_entry){
        $name = $folder.'/subscribers.md';
        $f = file_get_contents($name);
        $confirmed = 0;
        
        preg_match_all("/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2}):[\s]*\[(.*)\][\s]*\[(.*)\][\s]*([^\s]*)\R\R/Uu", $f, $matches, PREG_SET_ORDER); 
        
        $fi = fopen($name,'w');
        foreach($matches as $match){
            if($match[9]==$mail_address && (!isset($id) || ($id==$match[7]))){
                if($remove_entry){
                    continue;
                }else{
                    fwrite($fi,$this->CurrentTimeReadable().': [CONFIRMED] ['.$match[8].'] '.$mail_address.PHP_EOL.PHP_EOL);
                }
                $confirmed = 1;
                continue;
            }
            fwrite($fi, $match[0]);
        }
        return $confirmed;
    }
    function MakePassageEditButtons(){
        ob_start();
        $path = $this->InterlinkPath();
        $this->GetFileNameDateFormat($this->PagePath,$y,$m,$d,$is_draft);
        ?>
        <div class='hidden_on_print' style='text-align:right; z-index:1; position: absolute; right:0px; margin-top: -15px;'>
            <div id='passage_edit_normal'>
                <?php if ($is_draft){ ?>
                    <a href="?page=<?php echo $this->PagePath ?>&set_draft=0&translation=disabled">设为公开</a>
                <?php }else{ ?> 
                    <a href="?page=<?php echo $this->PagePath ?>&set_draft=1&translation=disabled">设为草稿</a>
                <?php } ?>
                <?php if ($this->IsPathUpdated($this->PagePath)){ ?>
                    <a href="?page=<?php echo $this->PagePath ?>&mark_update=0&translation=disabled">标记旧文</a>
                <?php }else{ ?> 
                    <a href="?page=<?php echo $this->PagePath ?>&mark_update=1&translation=disabled">标记更新</a>
                <?php } ?>
                <a href="?page=<?php echo $this->PagePath ?>&operation=additional">附加</a>
                &nbsp;
                <a href="?page=<?php echo $this->PagePath;?>&operation=edit"><b>编辑</b></a>
            </div>
            <?php if ($this->IsNewsletterFolder($this->InterlinkPath())){
                $SubCount = $this->ReadSubscribers($this->InterlinkPath());
                if($SubCount){ ?>
                    <div id='passage_edit_sender' style='display:none;'>
                        全部发送需要一些时间
                        <a href="?page=<?php echo $this->PagePath;?>&send_newsletter=run&folder=<?php echo $this->InterlinkPath(); ?>"><b>立即发送</b></a>
                    </div>
                    <div class="block_height_spacer"></div>
                    <a onclick="toggle_newsletter_sender();">为 <?php echo $SubCount; ?> 个订阅者发送这篇新闻</a>
                    <script>
                        function toggle_newsletter_sender(){
                            normal = document.getElementById("passage_edit_normal");
                            sender = document.getElementById("passage_edit_sender");
                            disp = sender.style.display;
                            sender.style.display = disp=="block"?"none":"block";
                            normal.style.display = disp=="block"?"block":"none";
                        }
                    </script>
                <?php } ?>
            <?php } ?>
        </div>
        <?php
        $contents = ob_get_contents();
        ob_end_clean();
        return $contents;
    }
    function MakeEditorHeader(){
        ?>
        <div class='the_body'>
        <div id = "EditorHeader" class="top_panel">
            <a id='EditorToggleMore' class='btn'><?php echo $this->FROM_ZH('更多') ?></a>
            &nbsp;
            <div id='EditorToggleH1' class='btn'>H1</div>
            <div id='EditorToggleH2' class='btn'>H2</div>
            <div id='EditorToggleH3' class='btn'>H3</div>
            <div id='EditorToggleH4' class='btn'>H4</div>
            &nbsp;
            
            <div id='EditorSpacer1' class='inline_height_spacer hidden_on_desktop' style='display:none;'></div>
            <div id='EditorMoreBtns' class='inline hidden_on_mobile'>
                <div id='EditorToggleBold' class='btn'><b>粗</b></div>
                <div id='EditorToggleItatic' class='btn'><i>斜</i></div>
                <div id='EditorToggleUnderline' class='btn'><u>线</u></div>
                <div id='EditorToggleStrike' class='btn'><s>删</s></div>
                <div id='EditorToggleQuote' class='btn'><b>“</b></div>
                <div id='EditorToggleSuper' class='btn'>A<sup>TM</sup></div>
                <div id='EditorToggleSub' class='btn'>B<sub>AE</sub></div>
                &nbsp;
                <div id='EditorAddLink' class='btn'>链</div>
            </div>
            <div class='inline hidden_on_mobile'>
            &nbsp;
                <a id='EditorCancel' class='btn' style='display:none;' href='?page=<?php echo $this->PagePath?>'><?php echo $this->FROM_ZH('放弃修改') ?></a>
            </div>
            
            <div class='hidden_on_desktop' >
                <div id='EditorSpacer2' class='inline_height_spacer' style='display:none;'></div>
                <a id='EditorCancelMobile' class='btn' style='display:none;' href='?page=<?php echo $this->PagePath?>'><?php echo $this->FROM_ZH('放弃') ?></a>
            </div>            
            
            <div class='inline_height_spacer hidden_on_desktop'></div>
            
            <div style='text-align:right; float:right; right:0px;'>
                <form method = "post" style='display:inline;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.
                            (isset($_GET['return_to'])?'&return_to='.$_GET['return_to']:'').(isset($_GET['delete_on_empty'])?'&delete_on_empty='.$_GET['delete_on_empty']:'');?>" id='form_passage'>
                    <div id='EditorMoreOptions' class='hidden_on_desktop' style='display:none;'>
                        <div>
                        <?php echo '放在 '.$this->InterlinkPath(); ?>
                        <div class='inline_block_height_spacer'></div>
                        </div>
                    </div>
                    <span class='hidden_on_mobile'>
                    <?php
                    
                    if($this->IsEditing){
                        echo '放在 '.$this->PagePath;?></span><?php
                    }else{
                        echo '放在 '.$this->InterlinkPath().'/';
                        ?>
                        </span>
                        <a id='EditorTodayButton'><b><?php echo $this->FROM_ZH("今天"); ?></b></a>
                        <input class='string_input title_string' type="text" id="EditorFileName" name="editor_file_name" value='<?php echo $this->GetUniqueName(isset($_GET['title'])?$_GET['title']:'Untitled');?>'/>
                        .md
                        <?php
                    }
                    ?>
                    
                    &nbsp;
                    <input class='btn form_btn' type="submit" value="<?php echo $this->FROM_ZH('完成') ?>" name="button_new_passage" form='form_passage' onClick='destroy_unload_dialog()' />
                </form>
            </div>
            
        </div>
        </div>
        <?php
    }
    function MakeEditorBody($text){
        if($text===NULL){
            $text=$this->FROM_ZH("在这里编写您的文章。");
        }
        ?>
        <script>
        function editor_hide_hint(){
            e = document.getElementById("data_passage_content");
            if(e.innerHTML == "<?php echo $this->FROM_ZH("在这里编写您的文章。"); ?>"){
                e.innerHTML = "";
            }
        }
        </script>
        <div>
            <div id="editor_fullscreen_container" class="mobile_force_fullscreen modal_on_mobile white_bkg">
                
                <textarea onInput="la_mark_div_highlight('passage_edited_stripe');passage_edited_note.innerHTML='<b><?php echo $this->FROM_ZH('已编辑'); ?></b>&nbsp;';"
                    class='string_input big_string big_string_height' form='form_passage' id='data_passage_content' name='data_passage_content'
                    onFocus="editor_hide_hint();" ><?php echo $text;?></textarea>
                <div class="hidden_on_desktop"><a class="white_bkg modal_on_mobile" style="position:fixed; right:10px; top:10px; text-align:center;" onClick="editor_toggle_fullscreen_mobile()">切换全屏</a></div>
                <div id="passage_edited_stripe" class="highlight_stripe_mobile">
                    &nbsp;<span id='data_passage_character_count'></span>
                    <div style="float:right" id="passage_edited_note"></div>
                </div>
            </div>
            
            <script>
                function editor_toggle_fullscreen_mobile(){
                    c = document.getElementById("editor_fullscreen_container");
                    e = document.getElementById("data_passage_content");
                    b = document.getElementById("editor_fullscreen_button");
                    s = document.getElementById("passage_edited_stripe");
                    shown = c.className != "";
                    c.className = shown?"":"mobile_force_fullscreen modal_on_mobile white_bkg";
                    e.style.height = "";
                    e.className = shown?"editor_shrink string_input big_string":"string_input big_string big_string_height";
                    s.className = shown?"":"highlight_stripe_mobile";
                }
                window.onbeforeunload = function() { 
                    return "没写完就想跑？";
                }
                function destroy_unload_dialog(){
                    window.onbeforeunload = null;
                }

                var text_area = document.getElementById("data_passage_content");
                var count = document.getElementById("data_passage_character_count");
                var btn_h1 = document.getElementById("EditorToggleH1");
                var btn_h2 = document.getElementById("EditorToggleH2");
                var btn_h3 = document.getElementById("EditorToggleH3");
                var btn_h4 = document.getElementById("EditorToggleH4");
                var btn_b = document.getElementById("EditorToggleBold");
                var btn_i = document.getElementById("EditorToggleItatic");
                var btn_u = document.getElementById("EditorToggleUnderline");
                var btn_s = document.getElementById("EditorToggleStrike");
                var btn_q = document.getElementById("EditorToggleQuote");
                var btn_sup = document.getElementById("EditorToggleSuper");
                var btn_sub = document.getElementById("EditorToggleSub");
                var btn_link = document.getElementById("EditorAddLink");
                var btn_more = document.getElementById("EditorToggleMore");
                var div_more = document.getElementById("EditorMoreOptions");
                var btn_cancel = document.getElementById("EditorCancel");
                var btn_cancel_mobile = document.getElementById("EditorCancelMobile");
                var div_more_btns = document.getElementById("EditorMoreBtns");
                var sp1 = document.getElementById("EditorSpacer1");
                var sp2 = document.getElementById("EditorSpacer2");
                var btn_today = document.getElementById("EditorTodayButton");
                var editor_file_name = document.getElementById("EditorFileName");
                
                if(text_area.innerHTML == "<?php echo $this->FROM_ZH("在这里编写您的文章。"); ?>"){
                    count.innerHTML="空文件";
                }else{
                    count.innerHTML='<b>'+text_area.value.replace(/\w+/g, "a").replace(/[\ \r\n,.;:"'~?!，。：；‘’“”～？！、\/#+-=_@#$%^\*&()|<>\[\]\{\}\`（）—…]/g, "").length+" <?php echo $this->FROM_ZH('个字').'</b>, '.$this->FROM_ZH('长度')?> "+text_area.value.length;
                }   
                text_area.addEventListener("input",function(){
                    if(this.innerHTML == "<?php echo $this->FROM_ZH("在这里编写您的文章。"); ?>"){
                        count.innerHTML="空文件";
                    }else{
                        count.innerHTML='<b>'+this.value.replace(/\w+/g, "a").replace(/[\ \r\n,.;:"'~?!，。：；‘’“”～？！、\/#+-=_@#$%^\*&()|<>\[\]\{\}\`（）—…]/g, "").length+" <?php echo $this->FROM_ZH('个字').'</b>, '.$this->FROM_ZH('长度')?> "+text_area.value.length;
                    }
                });

                function selectionStart(){
                    return text_area.selectionStart;
                }
                function selectionEnd(){
                    return text_area.selectionEnd;
                }
                function getContent(){
                    return text_area.value;
                }
                function insertStr(source,start,newStr){
                    return source.slice(0, start) + newStr + source.slice(start)
                }
                function deleteStr(str,x,count){
                    return str.substring(0,x) + str.substring(x+count,str.length);
                }
                function strBeginWith(str,sub,start){
                    return str.substring(start,start+sub.length)==sub;
                }
                function getLineBegin(content,select){
                    var line=0,i=0;
                    while(i<content.length){
                        if (i==select) return line;
                        if (content[i]=='\n')line = i+1; 
                        i++;
                    }
                    return line;
                }
                function toggleHeadingMarks(content,line_begin,change_to){
                    if(strBeginWith(content,'# ',line_begin)){text_area.value = deleteStr(content,line_begin,2);if(change_to==1)return;}
                    if(strBeginWith(content,'## ',line_begin)){text_area.value = deleteStr(content,line_begin,3);if(change_to==2)return;}
                    if(strBeginWith(content,'### ',line_begin)){text_area.value = deleteStr(content,line_begin,4);if(change_to==3)return;}
                    if(strBeginWith(content,'#### ',line_begin)){text_area.value = deleteStr(content,line_begin,5);if(change_to==4)return;}
                    if(strBeginWith(content,'##### ',line_begin)){text_area.value = deleteStr(content,line_begin,6);if(change_to==5)return;}
                    if(strBeginWith(content,'###### ',line_begin)){text_area.value = deleteStr(content,line_begin,7);if(change_to==6)return;}
                    var addstr=''
                    for (var i=0;i<change_to;i++){
                        addstr+='#';
                    }
                    text_area.value = insertStr(text_area.value,line_begin,addstr+' ');
                }
                function toggleQuote(content,line_begin){
                    if(strBeginWith(content,'> ',line_begin)){text_area.value = deleteStr(content,line_begin,2);return;}
                    text_area.value = insertStr(text_area.value,line_begin,'> ');
                }
                function toggleBrackets(content,begin,end,l,r){
                    var l_pos=-1;
                    var r_pos=-1;
                    var check_lr = begin!=end;
                    for(var i=begin-l.length;i>=0;i--){             
                        if((!check_lr) || (check_lr && !strBeginWith(content,r,i))){
                            if(strBeginWith(content,l,i)){
                                l_pos=i;
                                for (var j=end;j<content.length;j++){
                                    if((!check_lr) || (check_lr && !strBeginWith(content,l,j))){
                                        if(strBeginWith(content,r,j)){
                                            r_pos=j;
                                            break;
                                        }
                                    }else break;
                                }
                            }
                        }else break;
                    }
                    if (l_pos>=0||r_pos>=0){
                        text_area.value = deleteStr(content,l_pos,l.length);
                        text_area.value = deleteStr(text_area.value,r_pos-l.length,r.length);
                        text_area.setSelectionRange(begin-l.length, end-l.length);
                        text_area.focus();
                        return;
                    }
                    if (l_pos<0&&r_pos<0){
                        text_area.value = insertStr(content,begin,l);
                        text_area.value = insertStr(text_area.value,end+l.length,r);
                        text_area.setSelectionRange(begin+l.length, end+l.length);
                        text_area.focus();
                    }
                }
                function addLink(content,begin,end){
                    text_area.value = insertStr(content,begin,'[');
                    text_area.value = insertStr(text_area.value,end+1,']()');
                    var new_pos = begin+(end-begin)+3;
                    text_area.setSelectionRange(new_pos, new_pos);
                    text_area.focus();
                }
                btn_h1.addEventListener("click", function() {
                    var content = getContent();
                    var select = selectionStart();
                    var line_begin = getLineBegin(content,select);
                    toggleHeadingMarks(content,line_begin,1);
                    text_area.setSelectionRange(select, select);
                    text_area.focus();
                });
                btn_h2.addEventListener("click", function() {
                    var content = getContent();
                    var select = selectionStart();
                    var line_begin = getLineBegin(content,select);
                    toggleHeadingMarks(content,line_begin,2);
                    text_area.setSelectionRange(select, select);
                    text_area.focus();
                });
                btn_h3.addEventListener("click", function() {
                    var content = getContent();
                    var select = selectionStart();
                    var line_begin = getLineBegin(content,select);
                    toggleHeadingMarks(content,line_begin,3);
                    text_area.setSelectionRange(select, select);
                    text_area.focus();
                });
                btn_h4.addEventListener("click", function() {
                    var content = getContent();
                    var select = selectionStart();
                    var line_begin = getLineBegin(content,select);
                    toggleHeadingMarks(content,line_begin,4);
                    text_area.setSelectionRange(select, select);
                    text_area.focus();
                });
                btn_b.addEventListener("click", function() {
                    var content = getContent();
                    var begin = selectionStart();
                    var end = selectionEnd();
                    toggleBrackets(content,begin,end,"<b>","</b>");
                });
                btn_i.addEventListener("click", function() {
                    var content = getContent();
                    var begin = selectionStart();
                    var end = selectionEnd();
                    toggleBrackets(content,begin,end,"<i>","</i>");
                });
                btn_u.addEventListener("click", function() {
                    var content = getContent();
                    var begin = selectionStart();
                    var end = selectionEnd();
                    toggleBrackets(content,begin,end,"<u>","</u>");
                });
                btn_s.addEventListener("click", function() {
                    var content = getContent();
                    var begin = selectionStart();
                    var end = selectionEnd();
                    toggleBrackets(content,begin,end,"~~","~~");
                });
                btn_q.addEventListener("click", function() {
                    var content = getContent();
                    var select = selectionStart();
                    var line_begin = getLineBegin(content,select);
                    toggleQuote(content,line_begin);
                    text_area.setSelectionRange(select, select);
                });
                btn_sup.addEventListener("click", function() {
                    var content = getContent();
                    var begin = selectionStart();
                    var end = selectionEnd();
                    toggleBrackets(content,begin,end,"<sup>","</sup>");
                });
                btn_sub.addEventListener("click", function() {
                    var content = getContent();
                    var begin = selectionStart();
                    var end = selectionEnd();
                    toggleBrackets(content,begin,end,"<sub>","</sub>");
                });
                btn_link.addEventListener("click", function() {
                    var content = getContent();
                    var begin = selectionStart();
                    var end = selectionEnd();
                    addLink(content,begin,end);
                });
                btn_more.addEventListener("click", function() {
                    var disp = div_more.style.display;
                    div_more.style.display = disp=='none'?'block':'none';
                    btn_cancel.style.display = disp=='none'?'inline':'none';
                    btn_cancel_mobile.style.display = disp=='none'?'inline':'none';
                    div_more_btns.style.cssText = disp=='none'?'display:inline':'';
                    sp1.style.cssText = disp=='none'?'':'display:none';
                    sp2.style.cssText = disp=='none'?'':'display:none';
                });
                Date.prototype.format = function(fmt) { 
                     var o = { 
                        "M+" : this.getMonth()+1,
                        "d+" : this.getDate(),
                        "h+" : this.getHours(),
                        "m+" : this.getMinutes(),
                        "s+" : this.getSeconds(),
                        "q+" : Math.floor((this.getMonth()+3)/3),
                        "S"  : this.getMilliseconds()
                    }; 
                    if(/(y+)/.test(fmt)) {
                            fmt=fmt.replace(RegExp.$1, (this.getFullYear()+"").substr(4 - RegExp.$1.length)); 
                    }
                     for(var k in o) {
                        if(new RegExp("("+ k +")").test(fmt)){
                             fmt = fmt.replace(RegExp.$1, (RegExp.$1.length==1) ? (o[k]) : (("00"+ o[k]).substr((""+ o[k]).length)));
                         }
                     }
                    return fmt; 
                }
                btn_today.addEventListener("click", function() {
                    name = editor_file_name.value;
                    var re= /^[0-9]{8}_/;
                    if(name.match(re)!=null){
                        editor_file_name.value = name.replace(re,"");
                        btn_today.innerHTML="<b><?php echo $this->FROM_ZH("今天"); ?></b>";
                    }else{
                        var date = new Date().format("yyyyMMdd_");
                        editor_file_name.value = date+name;
                        btn_today.innerHTML="<del><?php echo $this->FROM_ZH("今天"); ?></del>";
                    }
                });
            </script>
        </div>
        <?php
    }
    function FolderIsPublic($full_path){
        if(!isset($this->PrivateFolderList[0])) return true;
        foreach($this->PrivateFolderList as $private){
            if(preg_match('%'.$private.'%', $full_path)) return false;
        }
        return true;
    }
    function MakeFolderHeader(){
        $additional_mode = (isset($_GET['action']) && $_GET['action']=='view');
        $move_mode = isset($_GET['moving'])||$additional_mode;
        $moving = isset($_GET['moving'])?$_GET['moving']:'';
        
        $path = $this->InterlinkPath();
        $upper='.';
        if($path!='.')$upper = $this->GetInterlinkPath('..');
        $permission = $this->FolderIsPublic($path);
        $display_as = $this->FolderDisplayAs($path);
        $novel_mode = $this->FolderNovelMode($path);
        $show_list  = $this->FolderShowListButton($path);
        ?>
        <div class='top_panel'>
        
            <a href="?page=<?php echo $upper.($additional_mode?'&operation='.$_GET["operation"].'&action=view&for='.$_GET['for']:'&operation=list'.($move_mode?'&moving='.$moving:''));?>" class='btn'><b><?php echo $this->FROM_ZH('上级') ?></b></a>
            
            <div style="float:right;text-align:right;margin-left:5px;">
                <?php if(!$move_mode){ ?>
                    <div class='btn' id='folder_permission'><?php echo $this->FROM_ZH('选项') ?></div>
                    &nbsp;
                    <a class='btn' id='folder_upload'><?php echo $this->FROM_ZH('上传') ?></a> 
                    <a class='btn' id='folder_new_folder'><?php echo $this->FROM_ZH('新文件夹') ?></a>
                    <div id='new_folder_dialog' style='display:none'>
                        <div class='inline_height_spacer'></div>
                        <form method = "post" style='display:inline;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=list';?>" id="form_new_folder">
                            <div><?php echo $this->FROM_ZH('新文件夹名') ?></div>
                            <div class='inline_block_height_spacer'></div>
                            <input class="string_input title_string" type="text" id="NewFolderName" name="new_folder_name" value="NewFolder" form="form_new_folder">
                            <input class="btn form_btn" type="submit" value="确定" name="button_new_folder" form="form_new_folder" id='folder_new_folder_confirm'>
                        </form>
                    </div>
                    <div id='upload_dialog' style='display:none'>
                        <div class='inline_height_spacer'></div>
                        <form method = "post" enctype="multipart/form-data" style='display:inline;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=list';?>" id="form_upload">
                            <label for="upload_file_name">
                                <div id='file_name_display' class='btn'><?php echo $this->FROM_ZH('选择要上传的文件') ?></div>
                                <input type="file" id="upload_file_name" name="upload_file_name" form="form_upload" style="display:none;" />
                            </label>
                            <input class="btn form_btn" type="submit" value="确定" name="button_upload" form="form_upload" id='upload_confirm'>
                        </form>
                    </div>
                    <div id='permission_dialog' style='display:none'>
                        <div class='inline_height_spacer'></div>
                        <?php $navigation_file = $path.'/navigation'.($this->LanguageAppendix=='en'?'_en':'').'.md'; ?>
                        <?php $notifications_file = $path.'/notifications'.($this->LanguageAppendix=='en'?'_en':'').'.md'; ?>
                        <a href='?page=<?php echo $navigation_file.(!file_exists($navigation_file)?'&operation=new&title=navigation'.($this->LanguageAppendix=='en'?'_en':''):'&operation=edit').'&return_to='.$this->PagePath.'&delete_on_empty=true';?>'>编辑导航栏</a>
                        <a href='?page=<?php echo $notifications_file.(!file_exists($notifications_file)?'&operation=new&title=notifications'.($this->LanguageAppendix=='en'?'_en':''):'&operation=edit').'&return_to='.$this->PagePath.'&delete_on_empty=true';?>'>和通知</a>
                        <div class='inline_height_spacer'></div>
                        <?php if($permission){ ?>
                            文件夹对外公开 &nbsp;<a class='btn' href='?page=<?php echo $path?>&operation=set_permission_off'>设为不公开</a>
                        <?php }else{ ?>
                            文件夹不公开 &nbsp;<a class='btn' href='?page=<?php echo $path?>&operation=set_permission_on'>设为公开</a>
                        <?php }?>
                        <div class='inline_height_spacer'></div>
                        <?php if($display_as=='Timeline'){ ?>
                            文件显示为时间线 &nbsp;<a class='btn' href='?page=<?php echo $path?>&operation=set_display_normal'>设为瓷砖</a>
                        <?php }else{ ?>
                            文件显示为瓷砖 &nbsp;<a class='btn' href='?page=<?php echo $path?>&operation=set_display_timeline'>设为时间线</a>
                        <?php }?>
                        <div class='inline_height_spacer'></div>
                        <?php if($novel_mode){ ?>
                            内容显示为小说样式 &nbsp;<a class='btn' href='?page=<?php echo $path?>&operation=set_layout_0'>设为节约纸张</a>
                        <?php }else{ ?>
                            内容显示为节约纸张 &nbsp;<a class='btn' href='?page=<?php echo $path?>&operation=set_layout_1'>设为小说样式</a>
                        <?php }?>
                        <div class='inline_height_spacer'></div>
                        <?php if($show_list){ ?>
                            显示了文章列表按钮 &nbsp;<a class='btn' href='?page=<?php echo $path?>&operation=set_list_button_0'>关闭</a>
                        <?php }else{ ?>
                            没有显示文章列表按钮 &nbsp;<a class='btn' href='?page=<?php echo $path?>&operation=set_list_button_1'>打开</a>
                        <?php }?>
                        <?php
                        //<a class='btn' id='StaticGeneratorButton'>文件夹生成为静态页面</a>
                        //<div id='StaticGeneratorDialog' style='display:none'>
                        //    <div class='inline_height_spacer'></div>
                        //    该操作将花费一段时间。继续吗？<a href='?page=<?php echo $path&static_generation=run'>执行</a>
                        //</div>
                        ?>
                    </div>
                    <script>
                        var new_folder = document.getElementById("folder_new_folder");
                        var new_folder_confirm = document.getElementById("folder_new_folder_confirm");
                        var new_folder_dialog = document.getElementById("new_folder_dialog");
                        var upload = document.getElementById("folder_upload");
                        var upload_confirm = document.getElementById("upload_confirm");
                        var upload_dialog = document.getElementById("upload_dialog");
                        var permission = document.getElementById("folder_permission");
                        var permission_dialog = document.getElementById("permission_dialog");
                        var static_gen_btn = document.getElementById("StaticGeneratorButton");
                        var static_gen_dialog = document.getElementById("StaticGeneratorDialog");
                        var file_name_disp = document.getElementById("file_name_display");
                        var upload_file_name = document.getElementById("upload_file_name");
                        
                        upload_file_name.addEventListener("change", function() {
                            file_name_disp.innerHTML = this.value.split('\\').pop();
                        }); 
                        
                        new_folder.addEventListener("click", function() {
                            var disp = new_folder_dialog.style.display;
                            upload_dialog.style.cssText = 'display:none';
                            new_folder_dialog.style.cssText = 'display:none';
                            new_folder_dialog.style.cssText = disp=='none'?'display:block':'display:none';
                        }); 
                        upload.addEventListener("click", function() {
                            var disp = upload_dialog.style.display;
                            new_folder_dialog.style.cssText = 'display:none';
                            permission_dialog.style.cssText = 'display:none';
                            upload_dialog.style.cssText = disp=='none'?'display:block':'display:none';
                        });
                        permission.addEventListener("click", function() {
                            var disp = permission_dialog.style.display;
                            upload_dialog.style.cssText = 'display:none';
                            new_folder_dialog.style.cssText = 'display:none';
                            permission_dialog.style.cssText = disp=='none'?'display:block':'display:none';
                            header = document.getElementById("Header");
                            if(disp=='none'){
                                header.style.zIndex=50;
                                la_show_modal_blocker();
                            }else{
                                header.style.zIndex="";
                                la_hide_modal_blocker();
                            }
                        });
                        static_gen_btn.addEventListener("click", function() {
                            var disp = static_gen_dialog.style.display;
                            static_gen_dialog.style.cssText = disp=='none'?'display:block':'display:none';
                        });
                    </script>   
                <?php }else if(!$additional_mode){ ?>
                    <a class='btn' href='?page=<?php echo $moving ?>&operation=list'><?php echo $this->FROM_ZH('取消') ?></a>
                    <a class='btn' href='?page=<?php echo $path ?>&moving=<?php echo $moving ?>&to=<?php echo $path ?>'><?php echo $this->FROM_ZH('到这里') ?></a>
                <?php }else{ ?>
                    <a class='btn' href='?page=<?php echo $_GET["for"] ?><?php echo $_GET['operation']!='task'?'&operation='.$_GET['operation']:""?>'><?php echo $this->FROM_ZH('取消') ?></a>
                    <a class='btn' href='?page=<?php echo $path ?>&operation=<?php echo $_GET['operation']?>&action=add&for=<?php echo $_GET["for"] ?>&target=<?php echo $path ?>'><?php echo $this->FROM_ZH('选这个') ?></a>
                <?php } ?>
            </div>

            <?php 
            
            echo '<b>'.$path.'</b>';
            ?>
            
        </div>
        <?php
    }
    
    function GetAdditionalDisplayData($for=Null){
        if($for){
            $Conf = pathinfo($for,PATHINFO_DIRNAME).'/'.'la_config.md';
            $file_name = pathinfo($for,PATHINFO_BASENAME);
            $arr=Null;
        }else{
            $path = $this->InterlinkPath();
            $file_name = pathinfo($this->PagePath,PATHINFO_BASENAME);
            $Conf = $path.'/'.'la_config.md';
            $arr = Null;
        }
        if(is_readable($Conf)){
            $ConfRead = fopen($Conf,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($Conf)));
            fclose($ConfRead);
            $i=0;
            while($this->GetLineByNamesN($Config,$file_name,'Additional',$i)!==Null){
                $arr[$i]['path']       = $this->GetArgumentByNamesN($Config,$file_name,'Additional',$i,'Path');
                $arr[$i]['style']      = $this->GetArgumentByNamesN($Config,$file_name,'Additional',$i,'Style');
                $arr[$i]['count']      = $this->GetArgumentByNamesN($Config,$file_name,'Additional',$i,'Count');
                $arr[$i]['column']     = $this->GetArgumentByNamesN($Config,$file_name,'Additional',$i,'ColumnCount');
                $arr[$i]['quick_post'] = $this->GetArgumentByNamesN($Config,$file_name,'Additional',$i,'QuickPost');
                $arr[$i]['title']      = $this->GetArgumentByNamesN($Config,$file_name,'Additional',$i,'Title');
                $arr[$i]['complete']   = $this->GetArgumentByNamesN($Config,$file_name,'Additional',$i,'Complete');
                $arr[$i]['more']       = $this->GetArgumentByNamesN($Config,$file_name,'Additional',$i,'More');
                if($arr[$i]['count']===Null) $arr[$i]['count'] = $arr[$i]['style']==5?7:4;
                $i++;
            }       
        }
        return $arr;
    }
    function GetAdditionalLayout(){
        $path = $this->InterlinkPath();
        $file_name = pathinfo($this->PagePath,PATHINFO_BASENAME);
        $Conf = $path.'/'.'la_config.md';
        
        if(is_readable($Conf)){
            $ConfRead = fopen($Conf,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($Conf)));
            fclose($ConfRead);
            $i=0;
            return $this->GetLineValueByNames($Config,$file_name,'Layout');    
        }
        return Null;
    }
    
    function AddAdditionalDisplayData($for,$target_path){
        $path = pathinfo($for,PATHINFO_DIRNAME);
        $file_name = pathinfo($for,PATHINFO_BASENAME);
        $Conf = $path.'/'.'la_config.md';
        $Config = Null;

        if(is_readable($Conf)){
            $ConfRead = fopen($Conf,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($Conf)));
            fclose($ConfRead);
        }
            
        $i=0;
        while($this->GetLineByNamesN($Config,$file_name,'Additional',$i)!==Null){
            $i++;
        }
        $this->EditBlock($Config,$file_name);
        $this->EditGeneralLineNByName($Config,$file_name,'Additional',$i,'');
        $this->EditArgumentByNamesN($Config,$file_name,'Additional',$i,'Path',$target_path);
        $ConfWrite = fopen($Conf,'w');
        $this->WriteMarkdownConfig($Config, $ConfWrite);
        fclose($ConfWrite);
    }
    
    function DeleteAdditionalDisplayData($for,$target_path){
        $path = pathinfo($for,PATHINFO_DIRNAME);
        $file_name = pathinfo($for,PATHINFO_BASENAME);
        $Conf = $path.'/'.'la_config.md';
        $Config = Null;

        if(is_readable($Conf)){
            $ConfRead = fopen($Conf,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($Conf)));
            fclose($ConfRead);
        }else return;
            
        $i=0;
        $a=Null;
        while(($a = $this->GetArgumentByNamesN($Config,$file_name,'Additional',$i,'Path'))!=$target_path && $a!==Null){
            $i++;
        }
        
        if($a!==Null){
            $this->RemoveLineByNamesN($Config,$file_name,'Additional',$i);
            $ConfWrite = fopen($Conf,'w');
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }

    }
    
    function SetAdditionalDisplay($for,$target,$style,$count,$quick,$title,$complete,$more,$column){
        $path = pathinfo($for,PATHINFO_DIRNAME);
        $file_name = pathinfo($for,PATHINFO_BASENAME);
        $Conf = $path.'/'.'la_config.md';
        $Config = Null;
        
        if(is_readable($Conf)){
            $ConfRead = fopen($Conf,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($Conf)));
            fclose($ConfRead);
        }else return;
        
        $i=0;
        $a=Null;
        while(($a = $this->GetArgumentByNamesN($Config,$file_name,'Additional',$i,'Path'))!=$target && $a!==Null){
            $i++;
        }
        
        if($a!==Null){
            if($style!==Null)    $this->EditArgumentByNamesN($Config,$file_name,'Additional',$i,'Style',$style);
            if($count!==Null)    $this->EditArgumentByNamesN($Config,$file_name,'Additional',$i,'Count',$count);
            if($column!==Null)   $this->EditArgumentByNamesN($Config,$file_name,'Additional',$i,'ColumnCount',$column);
            if($quick!==Null)    $this->EditArgumentByNamesN($Config,$file_name,'Additional',$i,'QuickPost',$quick);
            if($title!==Null)    $this->EditArgumentByNamesN($Config,$file_name,'Additional',$i,'Title',$title);
            if($complete!==Null) $this->EditArgumentByNamesN($Config,$file_name,'Additional',$i,'Complete',$complete);
            if($more!==Null)     $this->EditArgumentByNamesN($Config,$file_name,'Additional',$i,'More',$more);
            $ConfWrite = fopen($Conf,'w');
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }
    }
    
    function SetAdditionalLayout($for,$layout){
        $path = pathinfo($for,PATHINFO_DIRNAME);
        $file_name = pathinfo($for,PATHINFO_BASENAME);
        $Conf = $path.'/'.'la_config.md';
        $Config = Null;
        
        if(is_readable($Conf)){
            $ConfRead = fopen($Conf,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($Conf)));
            fclose($ConfRead);
        }
        
        $this->EditBlock($Config,$file_name);
        $this->EditGeneralLineByName($Config,$file_name,'Layout',$layout);
        $ConfWrite = fopen($Conf,'w');
        $this->WriteMarkdownConfig($Config, $ConfWrite);
        fclose($ConfWrite);
    }
    
    function DoAdditionalConfig(){
        if(isset($_GET['operation']) && $_GET['operation']=='additional'){
            if(isset($_GET['action']) && $_GET['action']=='add'){
                $for = $_GET['for'];
                $target_path = $_GET['target'];
                $this->AddAdditionalDisplayData($for,$target_path);
                header('Location:?page='.$for.'&operation=additional');
            }else if(isset($_GET['action']) && $_GET['action']=='delete'){
                $for = $_GET['for'];
                $target_path = $_GET['target'];
                $this->DeleteAdditionalDisplayData($for,$target_path);
                header('Location:?page='.$for.'&operation=additional');
            }
        }else if (isset($_GET['operation']) && $_GET['operation']=='set_additional_style'){
            if(isset($_GET['target']) && isset($_GET['for'])){
                if($s = isset($_GET['style'])){
                    $this->SetAdditionalDisplay($_GET['for'],$_GET['target'],$_GET['style'],Null,Null,Null,Null,Null,Null);
                }
            }
            header('Location:?page='.$_GET['for'].'&operation=additional');
        }else if (isset($_GET['operation']) && $_GET['operation']=='set_additional_count'){
            if(isset($_GET['target']) && isset($_GET['for']) && isset($_POST['display_count']) && $_POST['display_count']!=''){
                $this->SetAdditionalDisplay($_GET['for'],$_GET['target'],Null,$_POST['display_count'],Null,Null,Null,Null,Null);
            }
            header('Location:?page='.$_GET['for'].'&operation=additional');
        }else if (isset($_GET['operation']) && $_GET['operation']=='set_item_count'){
            if(isset($_GET['target']) && isset($_GET['for']) && isset($_GET['count']) && $_GET['count']!=''){
                $this->SetAdditionalDisplay($_GET['for'],$_GET['target'],Null,$_GET['count'],Null,Null,Null,Null,Null);
            }
            header('Location:?page='.$_GET['for'].'&operation=additional');
        }else if (isset($_GET['operation']) && $_GET['operation']=='set_additional_column_count'){
            if(isset($_GET['target']) && isset($_GET['for']) && isset($_GET['column_count']) && $_GET['column_count']!=''){
                $this->SetAdditionalDisplay($_GET['for'],$_GET['target'],Null,Null,Null,Null,Null,Null,$_GET['column_count']);
            }
            header('Location:?page='.$_GET['for'].'&operation=additional');
        }else if (isset($_GET['operation']) && $_GET['operation']=='set_additional_title'){
            if(isset($_GET['target']) && isset($_GET['for']) && isset($_POST['display_title'])){
                $this->SetAdditionalDisplay($_GET['for'],$_GET['target'],Null,Null,Null,$_POST['display_title'],Null,Null,Null);
            }
            header('Location:?page='.$_GET['for'].'&operation=additional');
        }else if (isset($_GET['operation']) && $_GET['operation']=='set_additional_more_title'){
            if(isset($_GET['target']) && isset($_GET['for']) && isset($_POST['display_more_title'])){
                $this->SetAdditionalDisplay($_GET['for'],$_GET['target'],Null,Null,Null,Null,Null,$_POST['display_more_title'],Null);
            }
            header('Location:?page='.$_GET['for'].'&operation=additional');
        }else if (isset($_GET['operation']) && $_GET['operation']=='set_additional_quick_post'){
            if(isset($_GET['target']) && isset($_GET['for'])){
                $this->SetAdditionalDisplay($_GET['for'],$_GET['target'],Null,Null,$_GET['quick'],Null,Null,Null,Null);
            }
            header('Location:?page='.$_GET['for'].'&operation=additional');
        }else if (isset($_GET['operation']) && $_GET['operation']=='set_additional_complete'){
            if(isset($_GET['target']) && isset($_GET['for'])){
                $this->SetAdditionalDisplay($_GET['for'],$_GET['target'],Null,Null,Null,Null,$_GET['complete'],Null,Null);
            }
            header('Location:?page='.$_GET['for'].'&operation=additional');
        }else if (isset($_GET['operation']) && $_GET['operation']=='set_additional_layout'){
            if(isset($_GET['for'])&&isset($_GET['layout'])){
                $this->SetAdditionalLayout($_GET['for'],$_GET['layout']);
            }
            header('Location:?page='.$_GET['for'].'&operation=additional');
        }
    }
    
    function AddTaskMangerEntry($for,$target_path){
        $Conf = $for;
        $Config = Null;

        if(is_readable($Conf)){
            $ConfRead = fopen($Conf,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($Conf)));
            fclose($ConfRead);
        }else return;
            
        $i=0;
        while($this->GetLineByNamesN($Config,"EventTracker",'Entry',$i)!==Null){
            $i++;
        }
        $this->EditBlock($Config,"EventTracker");
        $this->EditGeneralLineNByName($Config,"EventTracker",'Entry',$i,'');
        $this->EditArgumentByNamesN($Config,"EventTracker",'Entry',$i,'Target',$target_path);
        $ConfWrite = fopen($Conf,'w');
        $this->WriteMarkdownConfig($Config, $ConfWrite);
        fclose($ConfWrite);
    }
    
    function DeleteTaskMangerEntry($for,$target_path){
        $Conf = $for;
        $Config = Null;

        if(is_readable($Conf)){
            $ConfRead = fopen($Conf,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($Conf)));
            fclose($ConfRead);
        }else return;
            
        $i=0;
        $a=Null;
        while(($a = $this->GetArgumentByNamesN($Config,"EventTracker",'Entry',$i,'Target'))!=$target_path && $a!==Null){
            $i++;
        }
        
        if($a!==Null){
            $this->RemoveLineByNamesN($Config,"EventTracker",'Entry',$i);
            $ConfWrite = fopen($Conf,'w');
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }

    }
    
    function SetTaskDisplay($for,$target,$count){
        $path = pathinfo($for,PATHINFO_DIRNAME);
        $file_name = pathinfo($for,PATHINFO_BASENAME);
        $Conf = $for;
        $Config = Null;
        
        if(is_readable($Conf)){
            $ConfRead = fopen($Conf,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($Conf)));
            fclose($ConfRead);
        }else return;
        
        $i=0;
        $a=Null;
        while(($a = $this->GetArgumentByNamesN($Config,"EventTracker",'Entry',$i,'Target'))!=$target && $a!==Null){
            $i++;
        }
        
        if($a!==Null){
            if($count!==Null)    $this->EditArgumentByNamesN($Config,"EventTracker",'Entry',$i,'PastCount',$count);
            $ConfWrite = fopen($Conf,'w');
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }
    }
    
    function DoTaskManagerConfig(){
        if(isset($_GET['operation']) && $_GET['operation']=='task'){
            if(isset($_GET['action']) && $_GET['action']=='add'){
                $for = $_GET['for'];
                $target_path = $_GET['target'];
                $this->AddTaskMangerEntry($for,$target_path);
                header('Location:?page='.$for);
            }else if(isset($_GET['action']) && $_GET['action']=='delete'){
                $for = $_GET['for'];
                $target_path = $_GET['target'];
                $this->DeleteTaskMangerEntry($for,$target_path);
                header('Location:?page='.$for);
            }
        }else if (isset($_GET['operation']) && $_GET['operation']=='set_task_past_count'){
            if(isset($_GET['target']) && isset($_GET['for'])){
                if($s = isset($_GET['count'])){
                    $this->SetTaskDisplay($_GET['for'],$_GET['target'],$_GET['count']);
                }
            }
            header('Location:?page='.$_GET['for']);
        }
    }
    
    function GetFileNameDateFormat($file,&$year,&$month,&$day,&$is_draft){
        if(preg_match("/(\d{4})(\d{2})(\d{2})/",$file,$matches,PREG_OFFSET_CAPTURE)){
            $year =  $matches[1][0];
            $month = $matches[2][0];
            $day =   $matches[3][0];
        }else{
            $year='的某一天';
            $month='';
            $day ='过去';
        }
        if(preg_match("/DRAFT/",$file,$matches,PREG_OFFSET_CAPTURE)){
            $is_draft = True;
        }else{
            $is_draft = False;
        }
    }
    
    function SendMail($to_list, $subject, $content, $override_host, $override_port, $override_user, $override_password){
        $host = isset($override_host)?$override_host:$this->MailHost;
        $port = isset($override_port)?$override_port:$this->MailPort;
        $user = isset($override_user)?$override_user:$this->MailUser;
        $pass = isset($override_pass)?$override_pass:$this->MailPassword;
        
        if(!isset($host) || $host == "") return 0;
        
        $mail = new Email($host, $port);
        $mail->setLogin($user, $pass);
        foreach ($to_list as $to){
            $mail->addTo($to);
        }
        $mail->setFrom($user);
        $mail->setSubject($subject);
        $mail->setHtmlMessage($content);

        if($mail->send(10)){
            $this->MailSendResults[] = $mail->getSendResult();
            return 1;
        } else {
            $this->MailSendResults[] = $mail->getSendResult();
            return 0;
        }
    }
    
    function MakeAdditionalHeader(){
        $path = $this->InterlinkPath();
        $additional_disp = $this->GetAdditionalDisplayData();
        $this->Additional = $additional_disp;
        $layout = $this->GetAdditionalLayout();
        ?>
        <div class='top_panel'>
            <a class='btn' href='?page=<?php echo $path?>&operation=list'>列表</a>
            <a class='btn' href='?page=<?php echo $this->PagePath?>'>退出</a>
            <div style="float:right;text-align:right;margin-left:5px;">
                <?php if ((!$layout || $layout=='Normal') && isset($additional_disp)){ ?>
                    <a class='btn' href='?page=<?php echo $this->PagePath?>&operation=set_additional_layout&layout=Gallery&for=<?php echo $this->PagePath?>'>设为两栏</a>
                <?php }else if($layout && $layout=='Gallery'){ ?>
                    <a class='btn' href='?page=<?php echo $this->PagePath?>&operation=set_additional_layout&layout=Normal&for=<?php echo $this->PagePath?>'>设为一栏</a>
                <?php } ?>
                <div class='btn' id='additional_display_button'>附加内容</div>
                <div id='additional_display_dialog' style='display:none'>
                    <div class='inline_height_spacer'></div>
                    在正文下方附加显示选择的内容：
                    <div class='inline_height_spacer'></div>
                    <?php if($additional_disp!=Null) foreach ($additional_disp as $item){?>
                        <div>
                            来自 <?php echo $item['path']?> 的新帖子&nbsp;
                            <a class='btn' href='?page=<?php echo $this->PagePath?>&operation=additional&action=delete&for=<?php echo $this->PagePath?>&target=<?php echo $item['path']?>'>删</a>
                        </div>
                        
                        <div class='inline_height_spacer'></div>
                    <?php }?>
                    <a class='btn' href='?page=<?php echo $this->PagePath?>&operation=additional&action=view&for=<?php echo $this->PagePath?>'>添加文件夹</a>
                </div>
            </div>
            
            <script>
                var additional = document.getElementById("additional_display_button");
                var additional_dialog = document.getElementById("additional_display_dialog");
                additional.addEventListener("click", function() {
                    var disp = additional_dialog.style.display;
                    additional_dialog.style.cssText = disp=='none'?'display:block':'display:none';
                });
            </script>
        </div>
        <?php
    }
    function FilterFileListForSeason(&$FileNameList, $filter_season){
        if($filter_season == NULL) return;
        
        $new_list = $FileNameList;
        $FileNameList = NULL;
        
        foreach($new_list as $item){
            if(preg_match('/^\d{4}(\d{4})/',$item,$matches)){
                if($filter_season == 1){
                    if($matches[1]>=320 && $matches[1]<=621){
                        $FileNameList[] = $item;
                    }
                }else if($filter_season == 2){
                    if($matches[1]>=621 && $matches[1]<=923){
                        $FileNameList[] = $item;
                    }
                }else if($filter_season == 3){
                    if($matches[1]>=923 && $matches[1]<=1222){
                        $FileNameList[] = $item;
                    }
                }else if($filter_season == 4){
                    if($matches[1]>=1222 || $matches[1]<=320){
                        $FileNameList[] = $item;
                    }
                }
            }
        }
    }
    function GetAdditionalContent($page,$filter_season,&$prev,&$next,&$max){
        $ret=Null;
        if($page<0) $page=0;
        $prev=$page-1;
        if(!$page) $prev=Null;
        $i=0;
        $skip=$page*10;
        $to = $skip+10;
        
        $this->FilterFileListForSeason($this->FileNameList, $filter_season);
        if(!isset($this->FileNameList)){
            $prev=0;
            $next=0;
            $max =0;
            return;
        }
        
        while($i<$skip){
            $i++;
        }
        while(isset($this->FileNameList[$i])){
            $ret[]=$this->FileNameList[$i];
            $i++;
            if($i>=$to) break;
        }
        if(isset($this->FileNameList[$i])) $next=$page+1;
        else $next=Null;
        $max = ceil(count($this->FileNameList)/10);
        return $ret;
    }
    
    function GetSmallQuoteFiles($path,$file_count){
        $file_list = Null;
        
        $current_dir = opendir($path);
        
        while(($file = readdir($current_dir)) !== false) {
            $sub_dir = $path . DIRECTORY_SEPARATOR . $file;
            if($file == '.' || $file == '..') {
                continue;
            } else if(is_dir($sub_dir)) {
                continue;
            } else {
                $name=pathinfo($file,PATHINFO_BASENAME);
                if(preg_match("/([0-9]{4})-([0-9]{2})\.md$/",$name)){
                    $file_list[] = $name;
                }
            }
        }
        if(isset($file_list[0])){
            sort($file_list);
            $file_list = array_reverse($file_list);
            return array_slice($file_list, 0, $file_count);
        }
        return null;
    }
    function ReadLatestSmallQouote($folder,$random){
        $files = $this->GetSmallQuoteFiles($folder,$random?10000:1);
        $line=null;
        if(!$files) return null;
        $name=$folder.'/'.$files[$random?random_int(0,count($files)-1):0];
        
        if((file_exists($name) && is_readable($name))){
            $f = file_get_contents($name);
            if(preg_match_all("/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2}): (.*)\R\R/Uu", $f, $matches, PREG_SET_ORDER)){
                $match = $random?$matches[random_int(0,count($matches)-1)]:end($matches);
                $line['year']=$match[1]; $line['month']=$match[2]; $line['day']=$match[3];
                $line['hour']=$match[4]; $line['minute']=$match[5]; $line['second']=$match[6];
                $line['content']=$match[7];
                
                return $line;
            }
            return null;
        }
        return null;
    }
    function ReadSpecificSmallQuote($folder, $id){
        if(!$id || !$folder)return null;
        
        if(!preg_match('/([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})/',$id,$match)) return null;
        
        $name=$folder.'/'.$match[1].'-'.$match[2].'.md';
        
        if((file_exists($name) && is_readable($name))){
            $f = file_get_contents($name);
            if(preg_match_all("/".$match[1].'-'.$match[2].'-'.$match[3].' '.$match[4].':'.$match[5].':'.$match[6].": (.*)\R\R/U", $f, $matches, PREG_SET_ORDER)){
                $m =$matches[0];
                $line['year']=$match[1]; $line['month']=$match[2]; $line['day']=$match[3];
                $line['hour']=$match[4]; $line['minute']=$match[5]; $line['second']=$match[6];
                $line['content']=$m[1];
                return $line;
            }
            return null;
        }
        return null;
    }
    function AddSmallQuoteEntry($folder,$content){
        $name = $folder.'/'.date('Y-m').'.md';
        $f=null;
        $matches=null;
        if(file_exists($name) && is_readable($name)){
            $f = file_get_contents($name);
        }else{
            $fi = fopen($name,'w');
            fclose($fi);
            $f='';
        }
        
        $content = preg_replace('/\n/U','  ',$content);
        
        preg_match_all("/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2}): (.*)\R\R/Uu", $f, $matches, PREG_SET_ORDER); 
        
        $fi = fopen($name,'w');
        foreach($matches as $match){
            fwrite($fi, $match[0]);
        }
        
        fwrite($fi,$this->CurrentTimeReadable().': '.$content.PHP_EOL.PHP_EOL);
        
        fclose($fi);
    }
    function AddSubscriberEntry($folder,$content,$lang){
        $name = $folder.'/subscribers.md';
        $f=null;
        $matches=null;
        $found = 0;
        $unique_id = NULL;
        if(file_exists($name) && is_readable($name)){
            $f = file_get_contents($name);
        }else{
            $fi = fopen($name,'w');
            fclose($fi);
            $f='';
        }
        
        $content = preg_replace('/\n/U','  ',$content);
        
        preg_match_all("/([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2}):[\s]*\[(.*)\][\s]*\[(.*)\][\s]*([^\s]*)\R\R/Uu", $f, $matches, PREG_SET_ORDER); 
        
        $fi = fopen($name,'w');
        foreach($matches as $match){
            if(!$found && $match[9]==$content){
                $found = 1;
                $this->SubscriberIDExisting = $match[7];
            }
            fwrite($fi, $match[0]);
        }
        if(!$found){
            $unique_id = uniqid("LA_");
            fwrite($fi,$this->CurrentTimeReadable().': ['.$unique_id.'] ['.$lang.'] '.$content.PHP_EOL.PHP_EOL);
        }
        fclose($fi);
        return $unique_id;
    }
    function MakeCenterContainerBegin(){
    ?>
        <div class='center_container'>
        <div class='center_vertical'>
        <div class='center_box'>
    <?php
    }
    function MakeCenterContainerEnd(){
    ?>
        </div>
        </div>
        </div> 
    <?php
    }
    function MakeSmallQuotePanel($folder,$id,$prefix){
        if(isset($id)){
            $quote = $this->ReadSpecificSmallQuote($folder,$id);
        }else{
            if(isset($_GET['random'])){
                $quote = $this->ReadLatestSmallQouote($folder,1);
            }else{
                $quote = $this->ReadLatestSmallQouote($folder,0);
            }
        }
        
        if(!$quote){
            $quote['month']=13;
            $quote['day']=32;
            $quote['content']='这个文件夹还没有说过什么话';
        }
        ?>
        <div class='the_body'>
        <div class='main_content' style='overflow:auto;'>
            <?php if(!isset($id)){ ?>
                <div style='float:right;'>
                    <a href='?page=<?php echo $this->PagePath; ?>&small_quote_only=<?php echo $folder?>&random=true'><b>随机 &#11118;</b></a>
                </div>
            <?php } ?>
            <?php if(isset($prefix) && $prefix!=''){?>
                <b><?php echo $prefix ?>: </b>
            <?php } ?>
            <p>
                <?php echo $quote['content']; ?>
            </p>
            <?php echo $quote['year'].'-'.$quote['month'].'-'.$quote['day'].' '.$quote['hour'].':'.$quote['minute'] ?>
            <?php if(!isset($id)){ ?>
                <div style='float:right;'>
                    <a href='?page=<?php echo $this->PagePath; ?>'>&#10060;&#xfe0e;&nbsp;退出</a>&nbsp;
                    <a href='?page=index.md&small_quote=<?php echo $quote['year'].$quote['month'].$quote['day'].$quote['hour'].$quote['minute'].$quote['second']; ?>&quote_folder=<?php echo $folder; ?>' target="_blank">&#128279;&#xfe0e;&nbsp;链接</a>
                </div>
            <?php }else{ ?>
                <div style='float:right;'>
                    <a href='?page=<?php echo $this->PagePath; ?>'>&#128279;&#xfe0e;&nbsp;引用自&nbsp;<b><?php echo $this->StringTitle; ?></b></a>&nbsp;
                </div>
            <?php } ?>
        </div>
        </div>
        <?php
    }
    function MakeSmallQuoteAdditional($folder,$prefix,$more,$show_quick_post){
        $quote = $this->ReadLatestSmallQouote($folder,0);
        if(!$quote){
            $quote['month']=13;
            $quote['day']=32;
            $quote['content']='这个文件夹还没有说过什么话';
        }
        ?>
        <div class='main_content' style='overflow:auto;'>
            <div style='float:right;'>
                <a href='?page=<?php echo $this->PagePath; ?>&small_quote_only=<?php echo $folder?>'><?php echo $more?></a>
            </div>
            <?php echo $quote['month'].'<b>'.$quote['day'].'</b>'; ?>
            <?php if(isset($prefix) && $prefix!=''){?>
            <b><?php echo $prefix ?>: </b>
            <?php } ?>
            <p>
            <?php echo $quote['content']; ?>
            </p>
            <?php if($show_quick_post && $this->IsLoggedIn()){?>
                <form method = "post" style='display:none;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&quote_quick='.$folder;?>" id='form_passage'></form>
                <textarea type='text' class='quick_post_string under_border' form='form_passage' id='data_small_quote_content' name='data_small_quote_content'
                          onfocus="if (value =='<?php echo $this->FROM_ZH("小声哔哔…"); ?>'){value =''} la_enter_block_editing(this);"onblur="if (value ==''){value='<?php echo $this->FROM_ZH("小声哔哔…"); ?>';la_auto_grow(this);} la_exit_block_editing(this);"
                          oninput="la_auto_grow(this)"><?php echo $this->FROM_ZH("小声哔哔…"); ?></textarea>
                <div class='block_height_spacer'></div>

                <div style='float:right;'>
                    <input class='btn' type="submit" value="<?php echo $this->FROM_ZH("大声宣扬"); ?>" name="button_new_quote" form='form_passage' />
                </div>
                <script>la_auto_grow(document.getElementById("data_small_quote_content"));</script>
            <?php } ?>
        </div>
        <?php
    }
    function InitTaskFileMeta($fi){
        fwrite($fi, "# ".date("Y-m").PHP_EOL.PHP_EOL);
        fwrite($fi, "00000 - 00000".PHP_EOL.PHP_EOL);
        fwrite($fi, "Total:0 Done:0 Pending:0 Canceled:0 Active:0".PHP_EOL.PHP_EOL);
    }
    function RefreshTaskFileMeta($file_content){
        $count_finished=0;
        $count_pending=0;
        $count_canceled=0;
        $count_active=0;
        $count_total=0;
        $min_id="99999"; $max_id="00000";
        if(preg_match("/#[\s]*[0-9]{4}-[0-9]{2}/",$file_content,$title)){
            if(preg_match("(\*\*[TDCA][0-9]{5}[\s\S]*)",$file_content,$list)){
                if(preg_match_all("/\*\*([TDCA])([0-9]{5})\*\*[\s]*\[(.*)\][\s]*\[(.*)\][\s]*(.*)$$/m",$list[0],$ma3,PREG_SET_ORDER)){
                    foreach ($ma3 as $m){
                        $count_total++;
                        if($m[1] == 'T') $count_pending++;
                        else if($m[1] == 'D') $count_finished++;
                        else if($m[1] == 'C') $count_canceled++;
                        else if($m[1] == 'A') $count_active++;
                        if($m[2]<$min_id) $min_id = $m[2];
                        if($m[2]>$max_id) $max_id = $m[2];
                    }
                }
                return $title[0].PHP_EOL.PHP_EOL.$min_id.' - '.$max_id.PHP_EOL.PHP_EOL.
                    "Total:$count_total Done:$count_finished Pending:$count_pending Canceled:$count_canceled Active:$count_active".PHP_EOL.PHP_EOL.
                    $list[0];
            }
            return $title[0].PHP_EOL.PHP_EOL.'00000 - 00000'.PHP_EOL.PHP_EOL.
                "Total:$count_total Done:$count_finished Pending:$count_pending Canceled:$count_canceled Active:$count_active".PHP_EOL.PHP_EOL;
        }
        return $file_content;           
    }
    function GetTaskFile($folder,$id,&$latest_id){
        $file_list=[];
        $path = $folder;
        $current_dir = opendir($path);
        $current_file = 'T'.date('Y-m').'.md';
        while(($file = readdir($current_dir)) !== false) {
            $sub_dir = $path . '/' . $file;
            if($file == '.' || $file == '..' || $file=='index.md') {
                continue;
            } else if(!is_dir($sub_dir)){
                if(preg_match("/T[0-9]{4}-[0-9]{2}.md/",$file))
                    $file_list[] = $file;
            }
        }
        if(isset($file_list[0])) sort($file_list);
        else{
            $fi = fopen($path.'/'.$current_file, "w");
            $this->InitTaskFileMeta($fi);
            fflush($fi);
            fclose($fi);
            return $current_file;
        }
        $file_list = array_reverse($file_list);
        
        foreach($file_list as $f){
            $fi = fopen($folder.'/'.$f, "r");
            if(($size=filesize($folder.'/'.$f))==0)
                continue;

            $content = fread($fi,$size);
            fclose($fi);
            if(preg_match("/([0-9]{5})[\s]*-[\s]*([0-9]{5})/",$content,$match)){
                $latest_id = $match[2];
                if($latest_id < $id){
                    if($current_file!=$f){
                        $fii = fopen($path.'/'.$current_file, "w");
                        $this->InitTaskFileMeta($fii);
                        fflush($fii);
                        fclose($fii);
                        return $current_file;
                    }else{
                        return $f;
                    }
                }
                if($id < $match[1])
                    continue;
                if($id <= $match[2])
                    return $f;
            }
        }
        return NULL;
    }
    function CurrentTimeReadable(){
        return date("Y-m-d H:i:s");
    }
    function EditTask($folder, $id, $new_content, $tags, $state_change, $existing, $delete){
        $latest_id;
        $time_begin=Null; $time_end=Null;
        if (!isset($state_change)) $state_change = "";
        if($existing){
            $f = $this->GetTaskFile($folder, $id, $latest_id);
            if(!$f) return;
            $fi = fopen($folder.'/'.$f, "r");
            if(($size=filesize($folder.'/'.$f))==0) return;
            $content = fread($fi,$size);
            fclose($fi);
            
            $modified = preg_replace_callback("/\*\*([TDCA])(".$id.")\*\*[\s]*\[(.*)\][\s]*\[(.*)\][\s]*(.*)([\s]*)/m",
                function($m) use ($state_change, $tags, $new_content, $delete) {
                    
                    if($delete) return "";
                    
                    if(preg_match_all("/([0-9]{4})-([0-9]{2})-([0-9]{2})[\s]*([0-9]{2}):([0-9]{2}):([0-9]{2})/U",$m[3],$ma_time,PREG_SET_ORDER)){
                        $time_begin = $ma_time[0];
                    }

                    return "**".($state_change?$state_change:$m[1]).$m[2]."**"." [ ".$time_begin[0]." ".$this->CurrentTimeReadable()." ] [ ".(isset($tags)?trim($tags):trim($m[4]))." ] ".(isset($new_content)?trim($new_content):trim($m[5])).PHP_EOL.PHP_EOL;
                
                },$content);
            $fi = fopen($folder.'/'.$f, "w");
            $refreshed = $this->RefreshTaskFileMeta($modified);
            fwrite($fi,$refreshed);
            fclose($fi);
        }else{
            $f = $this->GetTaskFile($folder, 99999, $latest_id);
            if(!$f) return;
            if(($size=filesize($folder.'/'.$f))==0) return;
            $fi = fopen($folder.'/'.$f, "r");
            $content = fread($fi,$size);
            $cur_time = $this->CurrentTimeReadable();
            $content = $this->RefreshTaskFileMeta($content."**T".str_pad(($latest_id+1),5,"0",STR_PAD_LEFT)."** [ $cur_time ] [ $tags ] $new_content".PHP_EOL.PHP_EOL);
            $fi = fopen($folder.'/'.$f, "w");
            fwrite($fi,$content);
            fclose($fi);
        }
        
    }
    
    // returns positive when to > from
    function DayDifferences($y_from, $m_from, $d_from, $y_to, $m_to, $d_to){
        $from = new DateTime($y_from.'-'.$m_from.'-'.$d_from);
        $to = new DateTime($y_to.'-'.$m_to.'-'.$d_to);
        return ($to->getTimeStamp() - $from->getTimeStamp())/3600/24;
    }
    function TaskTimeDifferences($time_from,$time_to){
        $from = new DateTime($time_from['Y'].'-'.$time_from['M'].'-'.$time_from['D'].' '.$time_from['h'].':'.$time_from['m'].':'.$time_from['s']);
        $to = new DateTime($time_to['Y'].'-'.$time_to['M'].'-'.$time_to['D'].' '.$time_to['h'].':'.$time_to['m'].':'.$time_to['s']);
        return $to->getTimeStamp() - $from->getTimeStamp();
    }
    function ReadTaskItems($folder, $file_list, $done_day_lim, $today_y, $today_m, $today_d, &$unfinished_items, &$finished_items, &$active_items, &$have_delayed){
        $group_name=Null;
        $this->ReadTaskFolderDescription($folder, NULL ,$group_name, $old_count, $ancient_count);
        
        foreach($file_list as $f){
            if(!$f) continue;
            if(!preg_match("/T[0-9]{4}-[0-9]{2}.md/",$f)) continue;
            $fi = fopen($folder.'/'.$f, "r");
            if(($size=filesize($folder.'/'.$f))==0) continue;
            $content = fread($fi,filesize($folder.'/'.$f));
            fclose($fi);
            if(preg_match("/# ([0-9]{4})-([0-9]{2})([\s\S]*)/m",$content,$ma)){
                // no need to process range here.
                if(preg_match("/Total:([0-9]*)[\s]*Done:([0-9]*)[\s]*Pending:([0-9]*)[\s]*Canceled:([0-9]*)[\s]*Active:([0-9]*)([\s\S]*)/m",$ma[3],$ma2)){

                    if($ma2[3] == 0 && $ma2[5] == 0 && $this->DayDifferences($today_y, $today_m, $today_d, $ma[1], $ma[2], 28) < -$done_day_lim) continue;
                    
                    if(preg_match_all("/\*\*([TDCA])([0-9]{5})\*\*[\s]*\[(.*)\][\s]*\[(.*)\][\s]*(.*)/m",$ma2[6],$ma3,PREG_SET_ORDER)){
                        
                        if(isset($ma3)) foreach($ma3 as $m){
                            $item = Null;
                            if(preg_match_all("/([0-9]{4})-([0-9]{2})-([0-9]{2})[\s]*([0-9]{2}):([0-9]{2}):([0-9]{2})/U",$m[3],$ma_time,PREG_SET_ORDER)){
                                
                                if(($m[1]=='D'||$m[1]=='C') && $this->DayDifferences($today_y, $today_m, $today_d, $ma_time[1][1], $ma_time[1][2], $ma_time[1][3]) < -$done_day_lim) continue;
                                
                                $item['time_begin']['Y'] = $ma_time[0][1]; $item['time_begin']['M'] = $ma_time[0][2]; $item['time_begin']['D'] = $ma_time[0][3];
                                $item['time_begin']['h'] = $ma_time[0][4]; $item['time_begin']['m'] = $ma_time[0][5]; $item['time_begin']['s'] = $ma_time[0][6];
                                if(isset($ma_time[1])){
                                    $item['time_end']['Y'] = $ma_time[1][1]; $item['time_end']['M'] = $ma_time[1][2]; $item['time_end']['D'] = $ma_time[1][3];
                                    $item['time_end']['h'] = $ma_time[1][4]; $item['time_end']['m'] = $ma_time[1][5]; $item['time_end']['s'] = $ma_time[1][6];
                                }
                            }
                            
                            $item['group_name'] = $group_name;
                            $item['folder'] = $folder;
                            $item['status'] = $m[1];
                            $item['id'] = $m[2];
                            preg_match_all("/[\S]+/",$m[4],$item['tags'],PREG_SET_ORDER);
                            $item['content'] = $m[5];
                            
                            $item['delay_level'] = $this->TaskIsDelayed($item, $ancient_count)?2:($this->TaskIsDelayed($item, $old_count)?1:0);
                            if($item['delay_level']) $have_delayed = 1;
                            
                            if($m[1]=='D'||$m[1]=='C') $finished_items[] = $item;
                            else if($m[1]=='A') $active_items[] = $item;
                            else $unfinished_items[] = $item;
                        }
                    }
                }
            }
        }
    }
    function TaskIsDelayed($it, $days){
        if($this->DayDifferences($it['time_begin']['Y'],$it['time_begin']['M'],$it['time_begin']['D'],date("Y"),date("m"),date("d"))>$days){
            return true;
        }
        return false;
    }
    function MakeTaskListItem($i, $it, $show_group_name, $use_highlight){
        ?>
        <?php if ($it['status']=='D'){ ?>
            <li class="done">
        <?php }else if ($it['status']=='C'){ ?>
            <li class="canceled">
        <?php }else if ($it['status']=='A'){ ?>
            <li class="active">
        <?php }else{ ?>
            <li class="pending">
        <?php } ?>
            <div id = 'task_item_wrapper_<?php echo $i; ?>' <?php echo $use_highlight?
                ($it['delay_level']==2?"style='background-color: ".($this->TaskHighlightInvert?$this->chalfhighlight:$this->chighlight).";'":
                ($it['delay_level']==1?"style='background-color: ".($this->TaskHighlightInvert?$this->chighlight:$this->chalfhighlight).";'":"")):"" ?> >
                <div id='task_item_<?php echo $i; ?>'>
                    <div class='underline_when_hover'>
                        <?php echo $show_group_name?"<b>".$it['group_name']."</b> ":""; ?>
                        <?php if ($it['status']=='D'){ ?>
                            <del><span id='task_item_content_<?php echo $i; ?>'><?php echo $it['content']; ?></span></del>
                        <?php }else if ($it['status']=='C'){ ?>
                            <i><del><span id='task_item_content_<?php echo $i; ?>'><?php echo $it['content']; ?></span></del></i>
                        <?php }else{ ?>
                            <span id='task_item_content_<?php echo $i; ?>'><?php echo $it['content']; ?></span>
                        <?php } ?>
                    </div>
                </div>
                <div id='task_detail_<?php echo $i; ?>' style="display: none;">
                    <p class="task_p">
                        <?php if ($it['status']=='D'){ ?>
                            <?php echo $it['id']; ?>&nbsp;
                        <?php }else if ($it['status']=='C'){ ?>
                            <del><?php echo $it['id']; ?></del>&nbsp;
                        <?php }else{ ?>
                            <b><?php echo $it['id']; ?></b>&nbsp;
                        <?php } ?>
                        <span id='task_item_tags_<?php echo $i; ?>'><?php if(isset($it['tags'])) foreach($it['tags'] as $tag){
                                echo $tag[0]." ";
                            } ?></span>
                        <br />
                        <?php echo $it['time_begin']['Y'].'-'.$it['time_begin']['M'].'-'.$it['time_begin']['D'].' '.$it['time_begin']['h'].':'.$it['time_begin']['m'].':'.$it['time_begin']['s']; ?>
                        <?php if(($it['status']=='C' || $it['status']=='D') && isset($it['time_end'])){ ?>
                            &nbsp;~&nbsp;
                            <?php echo $it['time_end']['Y'].'-'.$it['time_end']['M'].'-'.$it['time_end']['D'].' '.$it['time_end']['h'].':'.$it['time_end']['m'].':'.$it['time_end']['s']; ?>
                        <?php } ?>
                    </p>
                    <?php if ($this->IsLoggedIn()){ ?>
                        <?php if ($it['status']!='C'){ ?>
                            <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=C"><?php echo $this->FROM_ZH('丢弃') ?></a>
                        <?php }else{ ?>
                            <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=D"><?php echo $this->FROM_ZH('完成') ?></a>
                        <?php } ?>
                        <a id="task_delete_button_<?php echo $i; ?>"><?php echo $this->FROM_ZH('删除') ?></a>
                        <div id="task_save_buttons_<?php echo $i; ?>" style="float:right;">
                            <a onclick="la_showTaskEditor('<?php echo $it['folder']; ?>','<?php echo $it['id']; ?>','<?php echo $i; ?>');"><?php echo $this->FROM_ZH('修改') ?></a>
                            <?php if ($it['status']=='T'){ ?>
                                <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=A">
                                    <b>&nbsp;<?php echo $this->FROM_ZH('进行') ?>&nbsp;</b>
                                </a>
                                <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=D">
                                    &nbsp;<?php echo $this->FROM_ZH('完成') ?>&nbsp;
                                </a>
                            <?php }else{ ?>
                                <?php if($it['status']=='A'){?>
                                    <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=T">
                                        &nbsp;<?php echo $this->FROM_ZH('暂缓') ?>&nbsp;
                                    </a>
                                    <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=D">
                                        <b>&nbsp;<?php echo $this->FROM_ZH('完成') ?>&nbsp;</b>
                                    </a>
                                <?php }else{ ?>
                                    <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=T">
                                        &nbsp;<?php echo $this->FROM_ZH('放回队列') ?>&nbsp;
                                    </a>
                                <?php } ?>
                            <?php } ?>
                        </div>
                        <div id="task_delete_prompt_<?php echo $i; ?>" style="display:none;float:right;">
                            <?php echo $this->FROM_ZH('删除条目') ?> #<?php echo $it['id']; ?>
                            <a href="?page=<?php echo $this->PagePath; ?>&operation=delete_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>"><?php echo $this->FROM_ZH('确认') ?></a>
                        </div>
                    <?php }?>
                </div>
            </div>
        </li>
        <?php
    }
    function MakeTaskGroupAdditional($folder, $done_limit, $override_unfinished_items, $override_finished_items, $override_active_items, $show_delay_marks){
        $override = (isset($override_unfinished_items)||isset($override_finished_items)||isset($override_active_items));
        $have_delayed = 0;
        if(!$override){
            $task_files = $this->FileNameList;
            $this->ReadTaskItems($folder, $task_files, $done_limit, date('Y'), date('m'), date('d'), $unfinished_items, $finished_items, $active_items, $have_delayed);
            $this->ReadTaskFolderDescription($folder, NULL,$folder_title,$unused,$unused);
        }else{
            $unfinished_items = $override_unfinished_items;
            $finished_items = $override_finished_items;
            $active_items = $override_active_items;
            $have_delayed = $show_delay_marks;
        }
        if($this->TaskManagerSelf){
            $folder_title = $this->TaskManagerTitle;
            $folder = $this->InterlinkPath();
        }
        ?>
        <div class='main_content'>
            <?php if(isset($have_delayed)&&$have_delayed){ ?>
                <div style="float:right; position:relative; z-index:5;" >
                    <table style="text-align:center;table-style:fixed;"><tr>
                    <tl style="background-color:<?php echo $this->cwhite?>;">&nbsp;&nbsp;<?php echo $this->FROM_ZH("正常"); ?>&nbsp;&nbsp;</tl>
                    <tl style="background-color:<?php echo $this->TaskHighlightInvert?$this->chighlight:$this->chalfhighlight?>;" >&nbsp;&nbsp;<?php echo $this->FROM_ZH("较早"); ?>&nbsp;&nbsp;</tl>
                    <tl style="background-color:<?php echo $this->TaskHighlightInvert?$this->chalfhighlight:$this->chighlight?>;" >&nbsp;&nbsp;<?php echo $this->FROM_ZH("很早"); ?>&nbsp;&nbsp;</tl>
                    </tr></table>
                </div>
            <?php } ?>
            <?php if(!$override){ ?>
            <div>
                <b><?php echo $this->FROM_ZH('正在跟踪') ?>：<?php echo $folder_title;?></b>
            </div>
            <?php } ?>
            <ul class="task_ul"><?php
            $show_group_name = $override && (!$this->TaskManagerSelf);
            if(isset($active_items)) foreach($active_items as $it){
                $this->MakeTaskListItem($this->GLOBAL_TASK_I,$it,$show_group_name, 1);
                $this->GLOBAL_TASK_I++;
            }?>
            </ul>
            <ul class="task_ul"><?php
            if(isset($unfinished_items)) foreach($unfinished_items as $it){
                $this->MakeTaskListItem($this->GLOBAL_TASK_I,$it,$show_group_name, 1);
                $this->GLOBAL_TASK_I++;
            }?>
            </ul>
            <ul class="task_ul">
            <?php
            if(isset($finished_items)) foreach($finished_items as $it){
                $this->MakeTaskListItem($this->GLOBAL_TASK_I,$it,$show_group_name, 0);
                $this->GLOBAL_TASK_I++;
            }?>
            </ul>
            <?php if($this->IsLoggedIn() && !$override){ ?>
                <div class='additional_content' style="position:sticky; bottom:15px; margin-bottom:0px;">
                    <a class="no_border" style="display:block;text-align:center;"onClick="la_showTaskEditor('<?php echo $folder;?>',-1,-1);">
                    +
                    <?php if ($this->UseLanguage() == 'zh'){ ?>
                        在 <?php echo $folder_title;?> 中新增事件
                    <?php } else if ($this->UseLanguage() == 'en'){ ?>
                        Create a new event in <?php echo $folder_title;?>
                    <?php } ?>
                    </a>
                </div>
            <?php } ?>
        </div>
        <?php
    }
    function MakeTaskEditor(){
        ?>
        <?php if($this->IsLoggedIn()){ ?>
            <div class='audio_player_box modal_dialog' style='display:none;' id='task_editor_box'>
                <p class='task_p'>
                    <b><span id="task_editing_id"></span></b>&nbsp;<?php echo $this->FROM_ZH('在事件组') ?>&nbsp;<span id="task_editing_path"></span>
                </p>
                <div>
                <form method = "post" style='display:inline;' 
                action=""
                id="form_task_editor">
                    <textarea class="quick_post_string no_border" type="text" id="task_editor_content" name="task_editor_content" form="form_task_editor"
                        onfocus="if (value =='<?php echo $this->FROM_ZH('事件描述') ?>'){value ='';}"onblur="if (value ==''){value='<?php echo $this->FROM_ZH('事件描述') ?>';la_auto_grow(this);}" oninput="la_auto_grow(this);"><?php echo $this->FROM_ZH('事件描述') ?></textarea>
                    <textarea class="quick_post_string no_border" style="font-size:12px;" type="text" id="task_editor_tags" name="task_editor_tags" form="form_task_editor"
                        onfocus="if (value =='<?php echo $this->FROM_ZH('标签') ?>'){value ='';}"onblur="if (value ==''){value='<?php echo $this->FROM_ZH('标签') ?>';la_auto_grow(this);}" oninput="la_auto_grow(this);"><?php echo $this->FROM_ZH('标签') ?></textarea>
                </form>
                <div class="inline_block_height_spacer"></div>
                    <table style="table-style:fixed;"><tr>
                        <td style="text-align:left;"><a onClick="la_hideTaskEditor();"><?php echo $this->FROM_ZH('取消') ?></a></td>
                        <td><input style="width:100%;"class="btn form_btn" type="submit" value="<?php echo $this->FROM_ZH('保存') ?>" name="task_editor_confirm" form="form_task_editor" id='task_editor_confirm'></td>
                    </table>
                <div class="block_height_spacer"></div>
                <script> la_auto_grow(document.getElementById("task_editor_content")); la_auto_grow(document.getElementById("task_editor_tags"));</script>
                </div>
            </div>
        <?php } ?>
        <script>
        <?php for($j=0;$j<$this->GLOBAL_TASK_I;$j++){?>
            b = document.getElementById("task_item_<?php echo $j; ?>");
            b.addEventListener("click", function() {
                d = document.getElementById("task_detail_<?php echo $j; ?>");
                w = document.getElementById("task_item_wrapper_<?php echo $j; ?>");
                disp = d.style.display;
                cn = w.className;
                d.style.display = disp=="none"?"block":"none";
                w.className = cn==""?"plain_block":"";
                w.style.borderLeft = cn==""?"5px solid <?php echo $this->cblack; ?>":"";
            });
            <?php if($this->IsLoggedIn()){ ?>
                del = document.getElementById("task_delete_button_<?php echo $j; ?>");
                del.addEventListener("click", function() {
                    p = document.getElementById("task_delete_prompt_<?php echo $j; ?>");
                    b = document.getElementById("task_save_buttons_<?php echo $j; ?>");
                    disp = b.style.display;
                    b.style.display = disp=="none"?"block":"none";
                    disp = p.style.display;
                    p.style.display = disp=="none"?"block":"none";
                });
            <?php } ?>
        <?php } ?>
        <?php if($this->IsLoggedIn()){ ?>
            function la_showTaskEditor(path,id,i){
                editor = document.getElementById("task_editor_box");
                eid = document.getElementById("task_editing_id");
                epath = document.getElementById("task_editing_path");
                tc = document.getElementById("task_item_content_"+i);
                etc = document.getElementById("task_editor_content");
                tt = document.getElementById("task_item_tags_"+i);
                ett = document.getElementById("task_editor_tags");
                tef = document.getElementById("form_task_editor");
                footer = document.getElementById("task_manager_footer");
                
                editor.style.display="block";
                eid.innerHTML=id>=0?id:"<?php echo $this->FROM_ZH('新增') ?>";
                epath.innerHTML=path;
                etc.innerHTML=tc?tc.innerHTML.trim():"<?php echo $this->FROM_ZH('事件描述') ?>";
                tags = tt?tt.innerHTML.trim():"";
                ett.innerHTML=tags==""?"<?php echo $this->FROM_ZH('标签') ?>":tags;
                
                tef.action = "?page="+"<?php echo$this->PagePath?>"+"&operation=edit_task&target="+path+"&id="+id;
                
                la_auto_grow(document.getElementById("task_editor_content"));
                la_auto_grow(document.getElementById("task_editor_tags"));
                
                la_show_modal_blocker();
                
                if(footer) footer.style.display="none";
            }
            function la_hideTaskEditor(){
                editor = document.getElementById("task_editor_box");
                editor.style.display="none";
                footer = document.getElementById("task_manager_footer");
                if(footer) footer.style.display="block";
                
                la_hide_modal_blocker();
            }
        <?php } ?>
        </script>
        <?php
    }
    function MakeMailSubscribeBox($folder,$title,$more){
        if(!isset($title)) $title = $this->FROM_ZH("新闻稿");
        $subs = $folder.'/subscribers.md';
        ?>
            <div class='main_content' style='overflow:auto;'>
                <div>
                    <b><?php echo $title; ?></b>
                    <div style='float:right;'>
                        <a href='?page=<?php echo $folder; ?>'><?php echo $this->FROM_ZH("过往新闻稿"); ?></a>
                    </div>
                </div>
                <?php if(isset($more) && $more!=''){ ?>
                    <p><?php echo $more; ?></p>
                <?php } ?>
                <div>
                    <script>
                        function la_do_mail_validation(area){
                            area.value = area.value.toLowerCase();
                            val = document.getElementById("mail_validation_string");
                            btn = document.getElementById("button_new_subscriber");
                            if(area.value.match(/^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/)){
                                val.innerHTML = "";
                                btn.removeAttribute("disabled");
                            }else{
                                val.innerHTML = "<?php echo $this->FROM_ZH("不正确的邮件格式"); ?>";
                                btn.setAttribute("disabled",true);
                            }
                        }
                        function la_mail_validation_blur(){
                            val = document.getElementById("mail_validation_string");
                            val.innerHTML = "";
                        }
                    </script>
                    <form method = "post" style='display:none;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&subscribe_quick='.$folder.'&title='.$title.'&subscribe_language='.(isset($this->LanguageAppendix)?$this->LanguageAppendix:"zh");?>" id='form_subscribe'></form>
                    <textarea type='text' class='quick_post_string under_border' form='form_subscribe' id='data_subscriber_content' name='data_subscriber_content'
                              onfocus="la_mail_validation_blur(); if (value =='<?php echo $this->FROM_ZH("在这里填写您的邮箱"); ?>'){value =''} la_enter_block_editing(this);"onblur="if (value ==''){value='<?php echo $this->FROM_ZH("在这里填写您的邮箱"); ?>';la_auto_grow(this);}la_mail_validation_blur();la_exit_block_editing(this);"
                              oninput="la_auto_grow(this); la_do_mail_validation(this);"><?php echo $this->FROM_ZH("在这里填写您的邮箱"); ?></textarea>
                    <div class='block_height_spacer'></div>
                    <input class='btn' type="submit" disabled="true" value="<?php echo $this->FROM_ZH("订阅"); ?>" name="button_new_subscriber" id='button_new_subscriber' form='form_subscribe' />
                    <span id='mail_validation_string'></span>
                    <script>la_auto_grow(document.getElementById("data_subscriber_content"));</script>
                </div>
            </div>
        <?php
    }
    function FilterOutPreservedFiles($name_list){
        $result=[];
        foreach($name_list as $item){
            if (preg_match("/subscribers\.md/",$item) ||
                preg_match("/index.*\.md/",$item) ||
                preg_match("/la_config\.md/",$item)){
                    continue;
                }
            $result[] = $item;
        }
        return $result;
    }
    function PutUndatedFilesToTail($name_list){
        $result=[];
        $undated=[];
        foreach($name_list as $item){
            if (!preg_match("/^[0-9]/",$item)) $undated[] = $item;
            else $result[] = $item;
        }
        return array_merge($result,$undated);
    }
    function MakeAdditionalContent($folder,$position,$filter_season){
        if(!isset($folder)){
            $ad = $this->GetAdditionalDisplayData();
            $this->Additional = $ad;
            if(!isset($ad[0])) return;
        }else{
            if(!($this->IsLoggedIn() || $this->FolderIsPublic($folder))){
                return;
            }
            $aa['path'] = $folder;
            $aa['style'] = 3;
            $aa['complete'] = 1;
            $aa['count'] = 1000000;
            $ad[] = $aa;
            $this->Additional = $ad;
        }
        
        ?>
        <div class='the_body'>
        <?php
        
        if ($this->AdditionalLayout=='Gallery'){
        ?>
            <div class='gallery_right'>
        <?php
        }
        
        $additional_i = 0;
        $used_path = [];
        foreach($ad as $a){
            $additional_i++;
            $this->FileNameList=[];
            $path = $a['path'];
            if(!($this->IsLoggedIn() || ($this->FolderIsPublic($path)))){
                if(!(isset($a['style']) && ($a['style']==6)))
                    continue;
            }
            $current_dir = opendir($path);
            while(($file = readdir($current_dir)) !== false) {
                if (isset($a['style']) && ($a['style']==4||$a['style']==6)) break;
                $sub_dir = $path . '/' . $file;
                if($file == '.' || $file == '..' || $file=='index.md') {
                    continue;
                } else if(!is_dir($sub_dir)){
                    $ext=pathinfo($file,PATHINFO_EXTENSION);
                    if($a['style']==2){
                        if($ext=='jpg' || $ext=='jpeg' || $ext=='png' || $ext=='svg' || $ext=='webp' || $ext=='gif')
                            $this->FileNameList[] = $file;
                    }else{
                        if($ext=='md')
                            $this->FileNameList[] = $file;
                    }
                }
            }
            if($this->FileNameList)     sort($this->FileNameList);
            $this->FileNameList = $this->PutUndatedFilesToTail($this->FilterOutPreservedFiles(array_reverse($this->FileNameList)));
            
            $novel_mode = $this->FolderNovelMode($a['path']);
            
            ?>
            <div style="position:relative">
            <?php
            
            if(isset($folder)){
                $prev_page=0;
                $next_page=0;
                $max_page=0;
                $this->FileNameList = $this->GetAdditionalContent($position,$filter_season,$prev_page,$next_page,$max_page);
                
                ?>
                <div class='top_panel block'>
                    <a href='?page=<?php echo $this->PagePath?>'><?php echo $this->FROM_ZH('不看了') ?></a>
                    <?php if(!isset($filter_season)){ ?>
                        <a onClick="toggle_filter_panel();" ><?php echo $this->FROM_ZH('过滤') ?></a>
                        <script>
                            function toggle_filter_panel(){
                                fp = document.getElementById('FilterPanel');
                                fp.style.display = fp.style.display=="none"?"block":"none";
                            }
                        </script>
                    <?php }else{ ?>
                        <a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder?>&position=<?php echo $position?>' >
                            <del><b><?php echo ($filter_season==1?$this->FROM_ZH('春'):($filter_season==2?$this->FROM_ZH('夏'):($filter_season==3?$this->FROM_ZH('秋'):($filter_season==4?$this->FROM_ZH('冬'):"x"))))?></b></del>
                        </a>
                    <?php } ?>
                    <div style='text-align:right;float:right;right:0px;'>
                        <?php if($prev_page!==Null){?><a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder.'&position='.$prev_page?><?php echo isset($filter_season)?"&filter_season=".$filter_season:""?>'><b><?php echo $this->FROM_ZH('上一页') ?></b></a><?php } ?>
                        &nbsp;
                        <?php echo ($position+1).'/'.$max_page ?>
                        &nbsp;
                        <?php if($next_page!==Null){?><a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder.'&position='.$next_page?><?php echo isset($filter_season)?"&filter_season=".$filter_season:""?>'><b><?php echo $this->FROM_ZH('下一页') ?></b></a><?php } ?>
                    </div>
                    <?php if(!isset($filter_season)){ ?>
                        <div id='FilterPanel' style='display:none;'>
                            <div class="inline_height_spacer"></div>
                            <a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder?>&position=<?php echo $position?>&filter_season=1' ><?php echo $this->FROM_ZH('春') ?></a>
                            <a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder?>&position=<?php echo $position?>&filter_season=2' ><?php echo $this->FROM_ZH('夏') ?></a>
                            <a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder?>&position=<?php echo $position?>&filter_season=3' ><?php echo $this->FROM_ZH('秋') ?></a>
                            <a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder?>&position=<?php echo $position?>&filter_season=4' ><?php echo $this->FROM_ZH('冬') ?></a>
                        </div>
                    <?php } ?>
                </div>
                
                <?php
            }
            
            if(isset($_GET['operation']) && $_GET['operation'] == 'additional'){
            ?>
                <div style='text-align:right;'>
                    <div class = 'additional_options'>
                        <?php echo $this->FROM_ZH("附加显示"); ?> <?php echo $path?>&nbsp;
                        <?php if(!in_array($path,$used_path)){ 
                            $used_path[] = $path;
                            ?>
                            <div class='btn' id='additional_options_btn_<?php echo $additional_i?>'><?php echo $this->FROM_ZH("选项"); ?></div>
                            <div style='display:none' id='additional_options_dialog_<?php echo $additional_i?>'>
                                <div class='inline_height_spacer'></div>
                                <?php echo $this->FROM_ZH("显示为"); ?>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=0"?>'><?php echo $a['style']==0?"<b>项</b>":"项"?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=2"?>'><?php echo $a['style']==2?"<b>图</b>":"图"?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=1"?>'><?php echo $a['style']==1?"<b>块</b>":"块"?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=3"?>'><?php echo $a['style']==3?"<b>写</b>":"写"?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=4"?>'><?php echo $a['style']==4?"<b>说</b>":"说"?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=5"?>'><?php echo $a['style']==5?"<b>做</b>":"做"?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=6"?>'><?php echo $a['style']==6?"<b>订</b>":"订"?></a>
                                <div class='inline_height_spacer'></div>
                                <?php if($a['style']==0 || $a['style']==1 || $a['style']==2 || $a['style']==3){ ?>
                                    <?php echo $this->FROM_ZH("最近篇目数量"); ?>
                                    <form method = "post" style='display:inline;' 
                                    action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=set_additional_count&for='.$this->PagePath.'&target='.$path?>"
                                    id="form_additional_count<?php echo $additional_i?>">
                                        <input class="string_input no_horizon_margin title_string" style='width:4em;' type="text" value="<?php echo $a['count'] ?>" id="display_count_<?php echo $additional_i?>" name="display_count" form="form_additional_count<?php echo $additional_i?>">
                                        <input class="btn form_btn" type="submit" value="<?php echo $this->FROM_ZH("应用"); ?>" name="button_additional_count_confirm" form="form_additional_count<?php echo $additional_i?>" id='additional_count_confirm_<?php echo $additional_i?>'>
                                    </form>
                                    <div class='inline_height_spacer'></div>
                                <?php }else if($a['style']==5){ 
                                    $cc = $a['count'];?>
                                    <?php echo $this->FROM_ZH("显示"); ?>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=1"?>'><?php echo $cc==1?'<b>1</b>':'1'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=2"?>'><?php echo $cc==2?'<b>2</b>':'2'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=3"?>'><?php echo $cc==3?'<b>3</b>':'3'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=7"?>'><?php echo $cc==7?'<b>7</b>':'7'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=14"?>'><?php echo $cc==14?'<b>14</b>':'14'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=30"?>'><?php echo $cc==30?'<b>30</b>':'30'?></a>
                                    <?php echo $this->FROM_ZH("天内完成的"); ?>
                                    <div class='inline_height_spacer'></div>
                                <?php } ?>
                                <?php echo $this->FROM_ZH("区域标题"); ?>
                                <form method = "post" style='display:inline;' 
                                action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=set_additional_title&for='.$this->PagePath.'&target='.$path?>"
                                id="form_additional_title<?php echo $additional_i?>">
                                    <input class="string_input no_horizon_margin title_string" type="text" value="<?php echo (isset($a['title'])?$a['title']:'') ?>" id="display_title_<?php echo $additional_i?>" name="display_title" form="form_additional_title<?php echo $additional_i?>">
                                    <input class="btn form_btn" type="submit" value="<?php echo $this->FROM_ZH("应用"); ?>" name="button_additional_title_confirm" form="form_additional_title<?php echo $additional_i?>" id='additional_title_confirm_<?php echo $additional_i?>'>
                                </form>
                                <?php if($a['style']==1){ ?>
                                    <div class='inline_height_spacer'></div>
                                    <?php $cc = $a['column']?$a['column']:4?>
                                    <?php echo $this->FROM_ZH("方块列数量"); ?>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=1"?>'><?php echo $cc==1?'<b>1</b>':'1'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=2"?>'><?php echo $cc==2?'<b>2</b>':'2'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=3"?>'><?php echo $cc==3?'<b>3</b>':'3'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=4"?>'><?php echo $cc==4?'<b>4</b>':'4'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=5"?>'><?php echo $cc==5?'<b>5</b>':'5'?></a>
                                <?php } ?>
                                
                                <?php if($a['style']==2){ ?>
                                    <div class='inline_height_spacer'></div>
                                    <?php $cc = $a['column']?$a['column']:10?>
                                    <?php echo $this->FROM_ZH("一行的照片数"); ?>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=3"?>'><?php echo $cc==3?'<b>3</b>':'3'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=5"?>'><?php echo $cc==5?'<b>5</b>':'5'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=8"?>'><?php echo $cc==8?'<b>8</b>':'8'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=10"?>'><?php echo $cc==10?'<b>10</b>':'10'?></a>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=15"?>'><?php echo $cc==15?'<b>15</b>':'15'?></a>
                                <?php } ?>
                                
                                <?php if($a['style']==3){?>
                                
                                    <div class='inline_height_spacer'></div>
                                    <?php echo $this->FROM_ZH("时间线列表按钮"); ?>
                                    <form method = "post" style='display:inline;' 
                                    action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=set_additional_more_title&for='.$this->PagePath.'&target='.$path?>"
                                    id="form_additional_more_title<?php echo $additional_i?>">
                                        <input class="string_input no_horizon_margin title_string" type="text" value="<?php echo (isset($a['more'])?$a['more']:'') ?>" id="display_more_title_<?php echo $additional_i?>" name="display_more_title" form="form_additional_more_title<?php echo $additional_i?>">
                                        <input class="btn form_btn" type="submit" value="<?php echo $this->FROM_ZH("应用"); ?>" name="button_additional_more_title_confirm" form="form_additional_more_title<?php echo $additional_i?>" id='button_additional_more_title_confirm<?php echo $additional_i?>'>
                                    </form>
                                <?php if(isset($a['quick_post']) && $a['quick_post']==1){ ?>
                                    <div class='inline_height_spacer'></div>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_quick_post&for=".$this->PagePath."&target=".$path."&quick=0"?>'><?php echo $this->FROM_ZH("关闭快速发帖"); ?></a>
                                <?php }else if(!isset($a['quick_post']) || $a['quick_post']==0){?>
                                    <div class='inline_height_spacer'></div>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_quick_post&for=".$this->PagePath."&target=".$path."&quick=1"?>'><?php echo $this->FROM_ZH("启用快速发帖"); ?></a>
                                <?php }?>
                                <?php if(isset($a['complete']) && $a['complete']!=0){?>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_complete&for=".$this->PagePath."&target=".$path."&complete=0"?>'><?php echo $this->FROM_ZH("改显示为摘要"); ?></a>
                                <?php }else if(!isset($a['complete']) || $a['complete']==0){?>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_complete&for=".$this->PagePath."&target=".$path."&complete=1"?>'><?php echo $this->FROM_ZH("改显示为全文"); ?></a>
                                <?php }?>
                                <?php }?>
                                
                                <?php if($a['style']==4 || $a['style']==5){?>
                                    <div class='inline_height_spacer'></div>
                                    <?php echo $this->FROM_ZH("时间线列表按钮"); ?>
                                    <form method = "post" style='display:inline;' 
                                    action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=set_additional_more_title&for='.$this->PagePath.'&target='.$path?>"
                                    id="form_additional_more_title<?php echo $additional_i?>">
                                        <input class="string_input no_horizon_margin title_string" type="text" value="<?php echo (isset($a['more'])?$a['more']:'') ?>" id="display_more_title_<?php echo $additional_i?>" name="display_more_title" form="form_additional_more_title<?php echo $additional_i?>">
                                        <input class="btn form_btn" type="submit" value="<?php echo $this->FROM_ZH("应用"); ?>" name="button_additional_more_title_confirm" form="form_additional_more_title<?php echo $additional_i?>" id='button_additional_more_title_confirm<?php echo $additional_i?>'>
                                    </form>
                                    <?php if(isset($a['quick_post']) && $a['quick_post']==1){ ?>
                                        <div class='inline_height_spacer'></div>
                                        <a href='?page=<?php echo $this->PagePath."&operation=set_additional_quick_post&for=".$this->PagePath."&target=".$path."&quick=0"?>'><?php echo $this->FROM_ZH("关闭快速发帖"); ?></a>
                                    <?php }else if(!isset($a['quick_post']) || $a['quick_post']==0){?>
                                        <div class='inline_height_spacer'></div>
                                        <a href='?page=<?php echo $this->PagePath."&operation=set_additional_quick_post&for=".$this->PagePath."&target=".$path."&quick=1"?>'><?php echo $this->FROM_ZH("启用快速发帖"); ?></a>
                                    <?php }?>
                                <?php }?>
                                
                                <?php if($a['style']==6){?>
                                
                                    <div class='inline_height_spacer'></div>
                                    <?php echo $this->FROM_ZH("描述文字"); ?>
                                    <div class='inline_block_height_spacer'></div>
                                    <form method = "post" style='display:inline;' 
                                    action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=set_additional_more_title&for='.$this->PagePath.'&target='.$path?>"
                                    id="form_additional_more_title<?php echo $additional_i?>">
                                        <textarea class="quick_post_string under_border" onInput="la_auto_grow(this);" style="text-align:right;" id="display_more_title_<?php echo $additional_i?>" name="display_more_title" form="form_additional_more_title<?php echo $path?>"><?php echo (isset($a['more'])?$a['more']:'') ?></textarea>
                                        <div class='inline_block_height_spacer'></div>
                                        <input class="btn form_btn" type="submit" value="<?php echo $this->FROM_ZH("应用"); ?>" name="button_additional_more_title_confirm" form="form_additional_more_title<?php echo $additional_i?>" id='button_additional_more_title_confirm<?php echo $additional_i?>'>
                                    </form>
                                <?php } ?>
                                </div>
                           
                         <?php } ?>
                         </div>
                    <script>
                        var btn = document.getElementById("additional_options_btn_<?php echo $additional_i?>");
                        btn.addEventListener("click", function() {
                            var options_dialog = document.getElementById("additional_options_dialog_<?php echo $additional_i?>");
                            var disp = options_dialog.style.display;
                            options_dialog.style.cssText = disp=='none'?'display:block':'display:none';
                            <?php if($a['style']==6){ ?>
                                elem = document.getElementById("display_more_title_<?php echo $additional_i; ?>");
                                la_auto_grow(elem);
                            <?php } ?>
                        });
                    </script>
                </div>
            <?php
            }
            
            if(isset($a['title']) && $a['title']!='' && $a['style']!=4 && $a['style']!=2 && $a['style']!=6){
                ?>
                <div class='block_height_spacer'>&nbsp;</div>
                <div class='narrow_content' <?php echo isset($a['style'])&&$a['style']!=3?"style='position:sticky; top:80px; z-index:1;'":""; ?>>
                    <b><?php echo $a['title'] ?></b>
                    <div style="float:right;">
                    <?php if($this->IsLoggedIn()){ ?>
                        <a href="?page=<?php echo $a['path']?>&operation=new"><?php echo $this->FROM_ZH('写文')?></a>
                    <?php } ?>
                    <a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $a['path']?>'><?php echo $this->FROM_ZH("查看全部") ?></a>
                    </div>
                </div>
                <?php
            }
            
            if(!isset($a['style'])||$a['style']==0){
                $i=0;
                if (isset($this->FileNameList[0])) foreach ($this->FileNameList as $f){
                    if($f=='la_config.md') continue;
                    $this->GetFileNameDateFormat($f,$y,$m,$d,$is_draft);
                    if($is_draft && !$this->IsLoggedIn()) continue;
                    
                    $rows = $this->FirstRows($this->ContentOfMarkdownFile($path.'/'.$f),20);
                    $this->SetInterlinkPath($path.'/'.$f);
                    ?>
                    <div class='additional_content' <?php echo ($level=$this->PathUpdatedLevel($path.'/'.$f))?"style='background-color:".($level==1?$this->chighlight:$this->chalfhighlight).";'":""?> >
                        <div class='btn block' style='text-align:unset;overflow:hidden;' onclick='location.href="?page=<?php echo $path.'/'.$f;?>"'>
                            <div class='preview' style='max-height:300px;<?php echo $this->FileIsNSFW?"text-align:center;":""?>'><?php echo $this->HTMLFromMarkdown($rows);?></div>
                        </div>
                    </div>
                    <?php
                    $i++;
                    if($i>=$a['count']) break;
                }
            }else if (isset($a['style']) && $a['style']==1){
                $cc = $a['column']?$a['column']:4;
                ?><div class='tile_container'><?php
                $i=0;$j=0;
                if (isset($this->FileNameList[0])) foreach ($this->FileNameList as $f){
                    if($f=='la_config.md') continue;
                    $this->GetFileNameDateFormat($f,$y,$m,$d,$is_draft);
                    if($is_draft && !$this->IsLoggedIn()) continue;
                    
                    $rows = $this->FirstRows($this->ContentOfMarkdownFile($path.'/'.$f),20);
                    $this->SetInterlinkPath($path.'/'.$f);
                    ?>
                    <div class='tile_content tile_item' <?php echo ($level=$this->PathUpdatedLevel($path.'/'.$f))?"style='background-color:".($level==1?$this->chighlight:$this->chalfhighlight).";'":""?> >
                        □
                        <div class='btn block' style='text-align:unset;overflow:hidden;' onclick='location.href="?page=<?php echo $path.'/'.$f;?>"'>
                            <div class='preview' style='max-height:300px;<?php echo $this->FileIsNSFW?"text-align:center;":""?>'><?php echo $this->HTMLFromMarkdown($rows);?></div>
                        </div>
                    </div>
                    <?php
                    $i++;$j++;
                    if($j>=$a['count']) break;
                    if($i>=$cc){
                        ?><div style='display: table-row;'></div><?php
                        $i=0;
                    }
                }
                ?></div><?php
            }else if (isset($a['style']) && $a['style']==2){
                if($a['count']==0) break;
                $cc = $a['column']?$a['column']:10;
                $i=0;$j=0;
                ?>
                <div class='main_content'>
                <div>
                    <table style='table-layout:fixed;'><tbody>
                    <td><?php echo $a['title'] ?></td>
                    <td style="text-align:right;"><u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i><?php echo $this->FROM_ZH("那么的"); ?></i></u><i><b><?php echo $this->FROM_ZH("相册"); ?></b></i></td>
                    </tbody></table>
                    <div class='inline_height_spacer'></div>
                </div>
                <div class='image_tile'><?php
                if (isset($this->FileNameList[0])) foreach ($this->FileNameList as $f){
                    ?>
                    <div class="gallery_image_wrapper">
                    <div class="gallery_image_inner">
                        <img src='<?php echo $path.'/'.$f?>' class='gallery_image' />
                    </div>
                    </div>
                    <?php
                    $i++;$j++;
                    if($i>=$cc){
                        if($j>=$a['count']) break;
                        else { ?><div style='display:table-row;'></div><?php }
                        $i=0;
                    } 
                }
                if($j<$a['column']-1){
                    for($j; $j<$a['column']; $j++){
                        ?>
                        <div class="gallery_image_wrapper">
                        <div class="gallery_image_inner">
                            
                        </div>
                        </div>
                        <?php
                    }
                }
                ?>
                </div>
                </div><?php
            }else if (isset($a['style']) && $a['style']==3){
                if($this->IsLoggedIn() && isset($a['quick_post']) && $a['quick_post']!=0){
                    ?>
                    <div>
                        <div class='additional_content'>
                            <form method = "post" style='display:none;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&quick='.$path;?>" id='form_passage'></form>
                            <input style='display:none;' type="text" id="EditorFileName" name="editor_file_name" value='<?php echo $this->GetUniqueName(date("Ymd"));?>'/ form='form_passage'>
                            <textarea type='text' class='quick_post_string under_border' form='form_passage' id='data_passage_content' name='data_passage_content'
                                      onfocus="if (value =='我有一个想法…'){value =''} la_enter_block_editing(this);"onblur="if (value ==''){value='我有一个想法…';la_auto_grow(this);} la_exit_block_editing(this);" oninput="la_auto_grow(this)">我有一个想法…</textarea>
                            <div class='block_height_spacer'></div>
                            <div style='text-align:right;'>
                                <?php echo date("Y")?>/<?php echo date("m") ?>/<b><?php echo date("d")?></b>
                                <input class='btn' type="submit" value="与世界分享" name="button_new_passage" form='form_passage' />
                            </div>
                            <script> la_auto_grow(document.getElementById("data_passage_content"));</script>
                        </div>
                    </div>
                    <?php
                }
                $i=0;
                if (isset($this->FileNameList[0])) foreach ($this->FileNameList as $f){
                    if($f=='la_config.md') continue;
                    $y=''; $m=''; $d=''; $is_draft=False;
                    $show_complete = isset($a['complete'])&&$a['complete']==1;
                    $this->GetFileNameDateFormat($f,$y,$m,$d,$is_draft);
                    if($is_draft && !$this->IsLoggedIn()) continue;
                    $rows = $this->FirstRows($this->ContentOfMarkdownFile($path.'/'.$f),$show_complete?10000:10);
                    $title = $this->TitleOfFile($rows);
                    $last_interlink = $this->InterlinkPath();
                    $this->SetInterlinkPath($path.'/'.$f);
                    ?>
                    <div>
                    <div class='additional_content no_overflow' <?php echo ($level=$this->PathUpdatedLevel($path.'/'.$f))?"style='background-color:".($level==1?$this->chighlight:$this->chalfhighlight).";'":""?> >
                        <div style='clear:both;text-align:right;position:sticky;top:80px;z-index:1;'>
                            <div class='plain_block small_shadow' style='text-align:center;display:inline-block;background-color:<?php echo $this->cwhite ?>;'>
                                <div style='float:right'>
                                    &nbsp;<?php echo $is_draft?'<b>草稿</b>':($m?('于'.$y.'/'.$m.'/<b>'.$d.'</b>'):'<b>过去</b>的某一天') ?>
                                </div>
                                <div style='overflow:hidden;max-height:24px;'>
                                    <?php echo $this->HTMLFromMarkdown($title)?>
                                </div>
                            </div>
                            <div class='block_height_spacer'></div>
                        </div>
                        <div style=';'>
                        </div>
                        <div class='btn block' style="text-align:unset;"
                             onclick='location.href="?page=<?php echo $path.'/'.$f;?>"'>
                                <div class='preview <?php echo (!$folder && $background)?"gallery_box_when_bkg top_panel":""?>' style="<?php echo $show_complete?'':'max-height:200px;overflow:hidden;'?><?php echo $this->FileIsNSFW?'text-align:center;':''?>">
                                    <div class='<?php echo $novel_mode?"novel_content":"" ?>'>
                                        <?php echo $this->HTMLFromMarkdown($rows);?>
                                    </div>
                                </div>
                        </div>
                    </div>
                    </div>
                    
                    <?php
                    $i++;
                    if($i>=$a['count']) break;
                    $this->SetInterlinkPath($last_interlink);
                }
                if(isset($a['more']) && $a['more']!=''){
                    ?>
                    <div class='narrow_content' style='text-align: right; margin-top:-7px;'>
                        <a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $a['path']?>'><?php echo $a['more'] ?></a>
                    </div>
                    <div class='block_height_spacer'>&nbsp;</div>
                    <?php
                }
            }else if (isset($a['style']) && $a['style']==4){
                $this->MakeSmallQuoteAdditional($a['path'],$a['title'],$a['more'],$a['quick_post']);
            }else if (isset($a['style']) && $a['style']==5){
                $this->FileNameList = array_reverse($this->FileNameList);//old first
                $this->MakeTaskGroupAdditional($path, $a['count'],NULL,NULL,NULL,NULL);
            }else if (isset($a['style']) && $a['style']==6){
                $this->MakeMailSubscribeBox($a['path'],$a['title'],$a['more']);
            }
            if(isset($folder)){
                ?>
                <div style='text-align:center;position:sticky;bottom:0px;'>
                    <div class='top_panel inline_block'>
                        <div style='text-align:right;float:right;right:0px;'>
                            <?php if($prev_page!==Null){?><a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder.'&position='.$prev_page?><?php echo isset($filter_season)?"&filter_season=".$filter_season:""?>'><b><?php echo $this->FROM_ZH('上一页') ?></b></a><?php } ?>
                            &nbsp;
                            <?php echo ($position+1).'/'.$max_page ?>
                            &nbsp;
                            <?php if($next_page!==Null){?><a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder.'&position='.$next_page?><?php echo isset($filter_season)?"&filter_season=".$filter_season:""?>'><b><?php echo $this->FROM_ZH('下一页') ?></b></a><?php } ?>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
            </div>
            <?php
        }
        
        if ($this->AdditionalLayout=='Gallery'){
        ?>
            </div>
            
        <?php
        }
        ?></div><?php
    }
    function MakeFileList($moving,$viewing){
        $move_mode = $moving==''?$viewing:True;
        $path = $this->InterlinkPath();
        if(!is_readable($path)) return;
        $current_dir = opendir($path);
        while(($file = readdir($current_dir)) !== false) {
            $sub_dir = $path . DIRECTORY_SEPARATOR . $file;
            if($file == '.' || $file == '..') {
                continue;
            } else if(is_dir($sub_dir)) {
                $this->FolderNameList[] = $file;
            } else {
                $ext=pathinfo($file,PATHINFO_EXTENSION);
                if($ext=='md')
                    $this->FileNameList[] = $file;
                else
                    $this->OtherFileNameList[] = $file;
            }
        }
        if($this->FolderNameList)   sort($this->FolderNameList);
        if($this->FileNameList)     sort($this->FileNameList);
        if($this->OtherFileNameList)sort($this->OtherFileNameList);
        if (isset($this->FolderNameList[0])) foreach ($this->FolderNameList as $f){
            ?>
                <div class='the_body'>
                     <div class = 'narrow_content' style='float:left;margin-right:15px'>
                        <a href="?page=<?php echo $path.'/'.$f.($viewing?'&for='.$_GET['for'].'&operation='.$_GET["operation"].'&action=view':'&operation=list'.($move_mode?'&moving='.$moving:''));?>" class='btn'><b><?php echo $this->FROM_ZH('进入') ?></b></a>
                     </div>
                     <div class = 'narrow_content' style='float:right;margin-left:15px'>
                     <?php if (!$move_mode){ ?>
                        <div style='display:none;' id='folder_option_<?php echo $f;?>'>
                            <a id='folder_delete_btn_<?php echo $f;?>'><?php echo $this->FROM_ZH('删除') ?></a>
                            &nbsp;
                            <a id='folder_move_btn_<?php echo $f;?>' href='?page=<?php echo $path ?>&operation=list&moving=<?php echo $path.'/'.$f ?>'><?php echo $this->FROM_ZH('移动') ?></a>
                            <a id='folder_rename_btn_<?php echo $f;?>'><?php echo $this->FROM_ZH('改名') ?></a>
                            &nbsp;
                        </div>
                        <a class='btn' id='folder_option_btn_<?php echo $f;?>'><?php echo $this->FROM_ZH('调整') ?></a>
                     <?php }else if($viewing){ ?>
                        <a class='btn' id='folder_option_btn_<?php echo $f;?>' href='?page=<?php echo $path ?>&operation=<?php echo $_GET['operation']?>&action=add&for=<?php echo $_GET['for'] ?>&target=<?php echo $path.'/'.$f ?>'><?php echo $this->FROM_ZH('选这个') ?></a>
                     <?php }else{ ?>
                        <a class='btn' id='folder_option_btn_<?php echo $f;?>' href='?page=<?php echo $path ?>&moving=<?php echo $moving ?>&to=<?php echo $path.'/'.$f ?>'><?php echo $this->FROM_ZH('到这里') ?></a>
                     <?php } ?>
                     </div>
                     <div class = 'narrow_content' style='overflow:auto;'>
                        <b style='background-color:<?php echo $this->cwhite ?>;'><?php echo $f?></b>
                     </div>
                </div>
                <div class='the_body' style='clear:both;text-align:right'>
                    <div class = 'narrow_content' style='display:none' id='folder_delete_panel_<?php echo $f;?>'>
                    <?php echo $this->FROM_ZH('确认') ?> <a class='btn' href='?page=<?php echo $this->InterlinkPath();?>&operation=delete_folder&target=<?php echo $f?>'><?php echo $this->FROM_ZH('删除') ?> <?php echo $f?></a>
                    </div>
                    <div class = 'narrow_content' style='display:none' id='folder_rename_panel_<?php echo $f;?>'>
                    <?php echo $f;?> <?php echo $this->FROM_ZH('的新名字') ?>
                    <form method = "post" style='display:inline;' id='folder_rename_form_<?php echo $f?>' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=list&target='.$f;?>">
                        <input class="string_input title_string" type="text" id="RenameFolderName" name="rename_folder_name" form="folder_rename_form_<?php echo $f?>">
                        <input class="btn form_btn" type="submit" value="<?php echo $this->FROM_ZH('确定') ?>" name="button_rename_folder" form="folder_rename_form_<?php echo $f?>">
                    </form>
                    </div>
                </div>
            <?php
        }
        if (!$move_mode && isset($this->FileNameList[0])) foreach ($this->FileNameList as $f){
            $rows = $this->FirstRows($this->ContentOfMarkdownFile($this->InterlinkPath().'/'.$f),20);
            $title = $this->TitleOfFile($this->ContentOfMarkdownFile($this->InterlinkPath().'/'.$f));
            ?>
                <div class='the_body'>
                     <div class = 'narrow_content' style='overflow:hidden;'>
                     
                        <div style='float:right;text-align:right;margin-left:5px;' id='passage_filename_<?php echo $f;?>'>
                            <p style='display:inline;'><?php echo $f?></p>
                            <a class='btn' id='passage_show_detail_<?php echo $f;?>'>简介</a>
                        </div>
                        
                        <div class='passage_detail' id='passage_detail_<?php echo $f;?>' style='display:none;'>
                            <a class='btn' id='passage_operation_close_<?php echo $f;?>' style='display:none;'>取消操作</a>
                            <a class='btn' href="?page=<?php echo $path.'/'.$f;?>&translation=disabled">阅读全文</a>
                            <a class='btn' id='passage_close_detail_<?php echo $f;?>'>收起</a>
                            <div class='inline_block_height_spacer'></div>
                            <div style='width:100%;display:block;' id='passage_detail_inner_<?php echo $f;?>'>
                                <a class='btn block' href="?page=<?php echo $path.'/'.$f.'&operation=edit';?>">编辑文档</a>
                                <div class='block_height_spacer'></div>
                                <div class='plain_block'>
                                    <?php echo $f;?><br />
                                    <?php echo "创建：".date("Y-m-d H:i:s",filectime($path.'/'.$f));?><br />
                                    <?php echo "访问：".date("Y-m-d H:i:s",fileatime($path.'/'.$f));?><br />
                                    <?php echo "修改：".date("Y-m-d H:i:s",filemtime($path.'/'.$f));?><br />
                                </div>
                                <div class='block_height_spacer'></div>
                                <a class='btn block' id='passage_rename_button_<?php echo $f;?>'>重命名</a>
                                <div class='block_height_spacer'></div>
                                <a  class='btn block' href='?page=<?php echo $path ?>&operation=list&moving=<?php echo $path.'/'.$f ?>'>移动到</a>
                                <div class='block_height_spacer'></div>
                                <a class='btn block' href='?page=<?php echo $path.'/'.$f ?>&operation=additional'>附加选项</a>
                                <div class='inline_block_height_spacer'></div>
                                <a class='btn' id='passage_delete_button_<?php echo $f;?>'>删除文件</a>
                            </div>
                            <div style='display:none;' id='passage_rename_inner_<?php echo $f;?>'>
                                <div style='text-align:right;'>
                                    <div class='inline_height_spacer'></div>
                                    将文件<?php echo $f;?>改名为<br />
                                    <div class='inline_block_height_spacer'></div>
                                    <form method = "post" style='display:inline;' id='passage_rename_form_<?php echo $f?>' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=list&target='.$f;?>">
                                        <input class="string_input title_string" style = 'margin:0px;' type="text" id="RenameFolderName" name="rename_passage_name" form="passage_rename_form_<?php echo $f?>">
                                        .md
                                        <div class='inline_height_spacer'></div>
                                        <input class="btn form_btn" type="submit" value="确定" name="button_rename_passage" form="passage_rename_form_<?php echo $f?>">
                                    </form>
                                </div>
                            </div>
                            <div style='display:none;' id='passage_delete_inner_<?php echo $f;?>'>
                                <div style='text-align:right;'>
                                    <div class='inline_height_spacer'></div>
                                    确认删除文件 <?php echo $f;?> ？<br />
                                    <div class='inline_height_spacer'></div>
                                    <a class='btn' href='?page=<?php echo $this->InterlinkPath();?>&operation=delete_file&target=<?php echo $f?>'>删除</a>
                                </div>
                            </div>
                        </div>
                        
                        <div class='name_preview' id='passage_title_<?php echo $f;?>'>
                            <a class='btn' href="?page=<?php echo $path.'/'.$f;?>&translation=disabled" ><?php echo $this->HTMLFromMarkdown($title);?></a>
                        </div>
                        
                        <div class='preview preview_large preview_block' style='overflow:hidden;display:none;' id='passage_preview_<?php echo $f;?>'>
                            <?php echo $this->HTMLFromMarkdown($rows); ?>
                        </div>
                        
                     </div>
                </div>
                <div style='clear:both;'></div>
            <?php
        }
        if (!$move_mode && isset($this->OtherFileNameList[0])) foreach ($this->OtherFileNameList as $f){
            ?>
                <div class='the_body'>
                     <div class = 'narrow_content' style='overflow:auto;'>
                        
                        <div style='float:right;'>
                            <?php echo $f?>
                            <div style='display:none;' id='other_files_option_<?php echo $f;?>'>
                                <a id='other_delete_btn_<?php echo $f;?>'>删除</a>
                                &nbsp;
                                <a id='other_move_btn_<?php echo $f;?>'>移动</a>
                                <a id='other_rename_btn_<?php echo $f;?>'>改名</a>
                                &nbsp;
                            </div>
                            <a id='other_files_option_btn_<?php echo $f;?>' class='widebtn'>操作</a>
                        </div>
                        <div class='name_preview' style='overflow:auto;'>
                            <?php 
                            $ext=pathinfo($f,PATHINFO_EXTENSION);
                            if ($ext=='jpg' || $ext=='png' || $ext=='bmp' || $ext=='gif'){
                                echo '<img class="file_image_preview" src="'.$path.'/'.$f.'" alt="图像"></img>';
                            }else if ($ext=='php' || $ext=='html'){
                                echo '网页';
                            }else echo'文件';
                            ?>
                        </div>
                     </div>
                </div>
                <div class='the_body'>
                <div style='clear:both;text-align:right;'>
                    <div class = 'narrow_content' style='display:none' id='other_delete_panel_<?php echo $f;?>'>
                    确认 <a class='btn' href='?page=<?php echo $this->InterlinkPath();?>&operation=delete_file&target=<?php echo $f?>'>删除 <?php echo $f?></a>
                    </div>
                    <div class = 'narrow_content' style='display:none' id='other_rename_panel_<?php echo $f;?>'>
                    <?php echo $f;?> 的新名字
                    <form method = "post" style='display:inline;' id='other_rename_form_<?php echo $f?>' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=list&target='.$f;?>">
                        <input class="string_input title_string" type="text" id="RenameFolderName" name="rename_folder_name" form="other_rename_form_<?php echo $f?>">
                        <input class="btn form_btn" type="submit" value="确定" name="button_rename_folder" form="other_rename_form_<?php echo $f?>">
                    </form>
                    </div>
                </div>
                </div>
            <?php
        }
    }
    function MakePassageTiles(){
        $path = $this->InterlinkPath();
        $current_dir = opendir($path);
        
        $upper='.';
        if($path!='.')$upper = $this->GetInterlinkPath('..');
        
        while(($file = readdir($current_dir)) !== false) {
            $sub_dir = $path . DIRECTORY_SEPARATOR . $file;
            if($file == '.' || $file == '..') {
                continue;
            } else if(is_dir($sub_dir)) {
                if(file_exists($sub_dir.'/__LAMDWIKI__')) continue;
                $this->FolderNameList[] = $file;
            } else {
                $ext=pathinfo($file,PATHINFO_EXTENSION);
                if($ext=='md')
                    $this->FileNameList[] = $file;
                else
                    $this->OtherFileNameList[] = $file;
            }
        }
        if($this->FileNameList)     sort($this->FileNameList);
        if($this->OtherFileNameList)sort($this->OtherFileNameList);
        
        ?>
        <div class='the_body'>
        <div class='tile_container'>
        <?php
        $column_count=-1;
        if ($upper!=$path){
            $column_count++;
            ?>
            
            <div class = 'tile_content tile_item' style='overflow:auto;'>
            ■ ■ ■ ■ ■
            
            <?php if (isset($_GET['static_generator'])){?>
                <a href="../_la_list.html" class='btn block preview_btn'><h2>上级</h2><br />...</a>
            <?php }else{ ?>
                <a href="?page=<?php echo $upper.'&operation=tile';?>" class='btn block preview_btn'><h2>上级</h2><br />...</a>
            <?php } ?>
            
            </div>
            <?php
        }
            if (isset($this->FolderNameList[0])) foreach ($this->FolderNameList as $f){
                $fp = $this->FolderIsPublic($path.'/'.$f);
                if(!$fp){
                    if(!$this->IsLoggedIn()) continue;
                }
                
                $column_count++;
                if ($column_count>3){
                    $column_count=0;
                    ?>
                        <div style='display: table-row;'></div>
                    <?php
                }
                ?>
                    <div class = 'tile_content tile_item' style='overflow:auto;'>
                    <?php echo !$fp?'▣':'■' ?>
                    
                    <?php if (isset($_GET['static_generator'])){?>
                        <a href="<?php echo $f ?>/_la_list.html" class='btn block preview_btn'><h1><?php echo $f;?></h1><br />进入文件夹</a>
                    <?php }else{ ?>
                        <a href="?page=<?php echo $path.'/'.$f.'&operation=tile&translation=disabled';?>" class='btn block preview_btn'><h1><?php echo $f;?></h1><br />进入文件夹</a>
                    <?php } ?>
                    
                    
                    </div>
                <?php
            }
            if (isset($this->FileNameList[0])) foreach ($this->FileNameList as $f){
                if ($f=='LAUsers.md' || $f=='la_config.md') continue;
                $this->GetFileNameDateFormat($f,$y,$m,$d,$is_draft);
                if($is_draft && !$this->IsLoggedIn()) continue;
                
                $column_count++;
                if ($column_count>3){
                    $column_count=0;
                    ?>
                        <div style='display: table-row;'></div>
                    <?php
                }
                $rows = $this->FirstRows($this->ContentOfMarkdownFile($this->InterlinkPath().'/'.$f),20);
                $use_url = '?page='.$path.'/'.$f.'&translation=disabled';
                if(isset($_GET['static_generator'])){
                    $use_url = preg_replace('/\.md$/','.html',$f);
                }
                ?>
                    <div class = 'tile_content tile_item' style='overflow:auto;'>
                         □
                         <div onclick='location.href="<?php echo $use_url ?>"' class='btn block preview_btn' style='font-size:12px; text-align:left;'><?php echo $this->HTMLFromMarkdown($rows);?></div>
                    </div>
                <?php
            }

        ?>
        </div>
        </div>
        <?php
    }
    function MakeAudioPlayer(){
        if(!isset($this->AudioList[0])) return;
        ?>
        <div class='audio_player_box'>
        
            <div id='audio_player_playlist' style='display:none;'>
                <table><tbody>
                <?php foreach($this->AudioList as $audio){ ?>
                    <tr>
                    <td><div class='audio_selector btn' id='audio_selector_<?php echo $audio['id']; ?>' style="white-space:nowrap; display:block;">放这个</div></td>
                    <td id='audio_selector_backdrop_<?php echo $audio['id']; ?>' style="width:100%;">
                        <?php echo $audio['id']; ?>
                    </td>
                    </tr>
                <?php } ?>
                </tbody></table>
                <div class="block_height_spacer"></div>
            </div>
            
            <table><tbody><tr>
            
                <td style="white-space:nowrap;">
                    <b><a id='audio_player_btn_play' class='btn'>播放</a></b>
                    <a id='audio_player_btn_list' class='btn' <?php echo count($this->AudioList)>1?"":"style='display:none'"; ?> >&nbsp;#&nbsp;</a>
                    &nbsp;
                </td>
                
                <td id='audio_player_bar' class='plain_block' style='width:100%; position:relative;'>
                    
                    <div id='audio_player_progress' style='width:0%; background-color:<?php echo $this->cblack ?>; position:absolute; display:inline_block; z-index:-1; margin: -4px; height: 100%;'>
                        &nbsp;
                    </div>
                    
                    <div id='audio_player_buffer' class='halftone1' style='width:0%; position:absolute; display:inline_block; z-index:-2; margin: -4px; height: 100%;'>
                        &nbsp;
                    </div>
                    
                    <div id='audio_player_time' style='background-color:<?php echo $this->cwhite ?>; align-items: center; display: inline-block;'>
                        已停止
                    </div>
                    
                    <div id='audio_total_time' style='float:right; margin-right:4px; background-color:<?php echo $this->cwhite ?>; align-items: center; display: inline-block;'>
                        请稍候
                    </div>
                    
                </td>
                
            </tr></tbody></table>

        </div>
        <script>
            <?php if(True) { ?>
            var music = document.getElementById("<?php echo 'AUDIO_'.$this->AudioList[0]['id'] ?>");
            var music_list = document.getElementsByClassName("audio_item");
            var play = document.getElementById('audio_player_btn_play');
            var list_btn = document.getElementById('audio_player_btn_list');
            var list = document.getElementById('audio_player_playlist');
            var time = document.getElementById('audio_player_time');
            var duration = document.getElementById('audio_total_time');
            var progress = document.getElementById('audio_player_progress');
            var buffer = document.getElementById('audio_player_buffer');
            var bar = document.getElementById('audio_player_bar');
            var selectors = document.getElementsByClassName('audio_selector');
            var i;
            for (i=0;i<selectors.length;i++){
                selectors[i].addEventListener('click', function(event){
                    music.pause();
                    audio_back = document.getElementById('audio_selector_backdrop_'+music.id.match(/AUDIO_(.*)/)[1]);
                    audio_back.style.backgroundColor="";
                    
                    audio = 'AUDIO_'+this.id.match(/audio_selector_(.*)/)[1];
                    music = document.getElementById(audio);
                    music.pause();
                    play.innerHTML='播放';
                    
                    audio_back = document.getElementById('audio_selector_backdrop_'+this.id.match(/audio_selector_(.*)/)[1]);
                    audio_back.style.backgroundColor="<?php echo $this->chighlight; ?>";
                });
            }
            
            play.addEventListener("click", function() {
                if(music.paused){
                    music.play();
                    play.innerHTML='暂停';
                    list.style.display = 'none';
                    audio_back = document.getElementById('audio_selector_backdrop_'+music.id.match(/AUDIO_(.*)/)[1]);
                    audio_back.style.backgroundColor="<?php echo $this->chighlight; ?>";
                }else{
                    music.pause();
                    play.innerHTML='播放';
                }
                duration.innerHTML = (Math.floor(music.duration/60))+':'+la_pad((Math.round(music.duration)%60),2);
            });
            list_btn.addEventListener("click", function() {
                disp = list.style.display;
                list.style.display = disp=='none'?'block':'none';
            });
            bar.addEventListener('click', function(event){
                l = bar.getBoundingClientRect().left;
                r = bar.getBoundingClientRect().right;
                percent = ((event.clientX-l)/(r-l));
                music.currentTime = music.duration*Math.min(Math.max(percent,0),1);
            });
            for(i=0;i<music_list.length;i++){
                
                
                music_list[i].ontimeupdate = function(){
                    duration.innerHTML = (Math.floor(music.duration/60))+':'+la_pad((Math.round(music.duration)%60),2);
                    time.innerHTML=(Math.floor(music.currentTime/60))+':'+la_pad((Math.round(music.currentTime)%60),2);
                    progress.style.width=100*(music.currentTime/music.duration)+'%';
                    buffer.style.width = 100*(music.buffered.end(0)/music.duration)+'%';
                }

                if(i<music_list.length-1){
                    var next_music = music_list[i+1];
                    music_list[i].onended = function(){
                        music.pause();
                        music.currentTime=0;
                        
                        audio_back = document.getElementById('audio_selector_backdrop_'+music.id.match(/AUDIO_(.*)/)[1]);
                        audio_back.style.backgroundColor="";
                        
                        music = next_music;
                        
                        audio_back = document.getElementById('audio_selector_backdrop_'+music.id.match(/AUDIO_(.*)/)[1]);
                        audio_back.style.backgroundColor="<?php echo $this->chighlight; ?>";
                        
                        music.play();
                    }
                }
                
            }     
            <?php } ?>
        </script>
        <?php
    }
    function MakeTaskManagerFooter(){
        if($this->TaskManagerSelf && !$this->IsLoggedIn()) return;
    ?>
        <?php if(!$this->TaskManagerSelf) {?>
            <div class="bottom_sticky_menu_container modal_dialog">
                <div id="task_manager_group_switcher" class="bottom_sticky_menu_left" style="display:none;">
                    <?php if($this->IsLoggedIn()){ ?>
                        <div class="inline_block_height_spacer"></div>
                        <div>
                            <div class='btn' id='task_item_content_button'><?php echo $this->FROM_ZH('设置分组') ?></div>
                            <div style="float:right; display:none;" id='task_item_content_button_extra'>
                                <a class='btn' href='?page=<?php echo $this->PagePath?>&operation=edit'><?php echo $this->FROM_ZH('编辑文字') ?></a>
                                <a class='btn' href='?page=<?php echo $this->PagePath?>&operation=task&action=view&for=<?php echo $this->PagePath?>'><?php echo $this->FROM_ZH('添加组') ?></a>
                            </div>
                            <div class='inline_block_height_spacer'></div>
                            <div id='task_item_content_dialog' style='max-height: calc(-167px + 100vh); overflow: auto; display:none'>
                                <table>
                                <?php $tic=0;   ?>
                                <?php foreach ($this->TaskManagerGroups as $item){
                                    $pc=$item['past_count'];
                                    ?>
                                    <tr>
                                        <td><a class='btn' style="display:block" onclick='task_option_toggle_<?php echo $tic ?>()'><?php echo $item['title']?> <?php echo $this->FROM_ZH('位于') ?> <?php echo $item['path']?></a></td>
                                        <td style="width:30px;">
                                            <a class='btn' style="display:block" href='?page=<?php echo $this->PagePath?>&operation=task&action=delete&for=<?php echo $this->PagePath?>&target=<?php echo $item['path']?>'><?php echo $this->FROM_ZH('删') ?></a>
                                        </td>
                                    </tr>
                                    <tr id='task_item_option_<?php echo $tic ?>' style='display:none'>
                                        <td colspan="2">
                                        <div>
                                            <div class='inline_block_height_spacer'></div>
                                            显示
                                            <a href='?page=<?php echo $this->PagePath."&operation=set_task_past_count&for=".$this->PagePath."&target=".$item['path']."&count=1"?>'><?php echo $pc==1?'<b>1</b>':'1'?></a>
                                            <a href='?page=<?php echo $this->PagePath."&operation=set_task_past_count&for=".$this->PagePath."&target=".$item['path']."&count=2"?>'><?php echo $pc==2?'<b>2</b>':'2'?></a>
                                            <a href='?page=<?php echo $this->PagePath."&operation=set_task_past_count&for=".$this->PagePath."&target=".$item['path']."&count=3"?>'><?php echo $pc==3?'<b>3</b>':'3'?></a>
                                            <a href='?page=<?php echo $this->PagePath."&operation=set_task_past_count&for=".$this->PagePath."&target=".$item['path']."&count=7"?>'><?php echo $pc==7?'<b>7</b>':'7'?></a>
                                            <a href='?page=<?php echo $this->PagePath."&operation=set_task_past_count&for=".$this->PagePath."&target=".$item['path']."&count=14"?>'><?php echo $pc==14?'<b>14</b>':'14'?></a>
                                            <a href='?page=<?php echo $this->PagePath."&operation=set_task_past_count&for=".$this->PagePath."&target=".$item['path']."&count=30"?>'><?php echo $pc==30?'<b>30</b>':'30'?></a>
                                            天内完成的
                                            <div class='inline_block_height_spacer'></div>
                                        </div>
                                        <script>
                                            function task_option_toggle_<?php echo $tic ?>(){
                                                ta = document.getElementById("task_item_option_<?php echo $tic ?>");
                                                ta.style.display = ta.style.display=='none'?'table-row':'none';
                                            }
                                        </script>
                                        </td>
                                    </tr>
                                <?php $tic++; }?>
                                </table>
                            </div>
                            <script>
                                var content = document.getElementById("task_item_content_button");
                                content.addEventListener("click", function() {
                                    content_dialog = document.getElementById("task_item_content_dialog");
                                    extra_buttons = document.getElementById("task_item_content_button_extra");
                                    default_list = document.getElementById("task_default_list");
                                    disp = content_dialog.style.display;
                                    content_dialog.style.display = disp=='none'?'block':'none';
                                    extra_buttons.style.display = disp=='none'?'block':'none';
                                    default_list.style.display = disp=='none'?'none':'block';
                                });
                            </script>
                        </div>
                    <?php } ?>
                    <div style="max-height:calc(100vh - 167px); overflow:auto;" id="task_default_list">
                        <?php foreach ($this->TaskManagerGroups as $folder_item){?>
                            <div>
                                <table style="text-align:center;table-style:fixed;"><tr>
                                    <td><a style="display:block;"><?php echo $folder_item['title']?></a></td>
                                    <td style='width:80px;'>
                                    <?php if(is_readable($folder_item['path'].'/index.md')){ ?>
                                        <a style="display:block;" href="?page=<?php echo $folder_item['path']; ?>"><?php echo $this->FROM_ZH('进入分组') ?></a>
                                    <?php }else{?>
                                        <?php if($this->IsLoggedIn()){ ?>
                                            <a style="display:block;" href="?page=<?php echo $this->PagePath; ?>&operation=task_new_index&for=<?php echo $folder_item['path']; ?>"><?php echo $this->FROM_ZH('创建索引') ?></a>
                                        <?php }else{?>
                                            &nbsp;
                                        <?php }?></td>
                                    <?php }?></td>
                                </tr></table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            
                <div id="task_manager_group_adder" class="bottom_sticky_menu_right" style="display:none;">
                    <div style="max-height:calc(100vh - 167px); overflow:auto;">
                        <?php foreach ($this->TaskManagerGroups as $folder_item){?>
                            <div>
                                <table style="text-align:center;"><tr>
                                    <td width="70%;" ><a style="display:block;" onClick="la_task_adder_toogle();la_showTaskEditor('<?php echo $folder_item['path']; ?>',-1,-1);"><?php echo $this->FROM_ZH('到') ?> <?php echo $folder_item['title']?></a></td>
                                </tr></table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
        <div id="task_manager_footer" class="audio_player_box modal_dialog">
            <table style="text-align:center;"><tr>
                <?php if(!$this->TaskManagerSelf){ ?>
                    <td style="width:50%;"><a style="display:block;" onClick="la_task_group_switcher_toogle()">
                    <?php if($this->UseLanguage() == 'zh'){?>
                        共 <?php echo count($this->TaskManagerGroups); ?> 个事件组
                    <?php } else if($this->UseLanguage() == 'en'){ ?>
                        <?php echo count($this->TaskManagerGroups); ?> event groups in total
                    <?php } ?></a></td>
                    <?php if($this->IsLoggedIn()){ ?><td style="width:50%;"><a style="display:block;" onClick="la_task_adder_toogle()"><?php echo $this->FROM_ZH('新增事件') ?> +</a></td><?php } ?>
                <?php }else{ ?>
                    <td style="width:25%;"><a style="display:block;" href="?page=<?php echo $this->PagePath?>&operation=task&action=view&for=<?php echo $this->PagePath?>"><?php echo $this->FROM_ZH('选组') ?></a></td>
                    <td style="width:25%;"><a style="display:block;" href="?page=<?php echo $this->PagePath?>&operation=edit"><?php echo $this->FROM_ZH('编辑') ?></a></td>
                    <td style="width:50%;"><a style="display:block;" onClick="la_showTaskEditor('<?php echo $this->InterlinkPath(); ?>',-1,-1);"><?php echo $this->FROM_ZH('新增事件') ?> +</a></td>
                <?php } ?>
                
            </tr></table>
        </div>
        <script>
            function la_task_group_switcher_toogle(){
                sw = document.getElementById("task_manager_group_switcher");
                ad = document.getElementById("task_manager_group_adder");
                disp = sw.style.display=="none"?"block":"none";
                sw.style.display = disp;
                ad.style.display = "none";
                if(disp=="block") la_show_modal_blocker();
                else la_hide_modal_blocker();
            }
            function la_task_adder_toogle(){
                sw = document.getElementById("task_manager_group_switcher");
                ad = document.getElementById("task_manager_group_adder");
                disp = ad.style.display=="none"?"block":"none";
                ad.style.display = disp;
                sw.style.display = "none";
                if(disp=="block") la_show_modal_blocker();
                else la_hide_modal_blocker();
            }
        </script>
    <?php
    }
    function MakeFooter(){
        $have_stats = is_readable($this->StatsFile);
        ?>
        <div class='the_body'>
            <div style="text-align:right;">
            <table style="display:inline-block; width:unset; margin-bottom: 15px; text-align:right;"><tbody>
            <td class='footer' style="text-align:center;" >
                <div>
                    <p style='font-size:32px;margin:0px;'>
                    <?php if($have_stats) { echo "<a style='border:none;' href='?page=".$this->StatsFile."'>"; } ?>
                    <?php echo $this->CountTodayUpdates(); ?>
                    <?php if($have_stats) { echo "</a>"; } ?>
                    </p>
                    <p style='font-size:12px;margin:0px;'><?php echo $this->FROM_ZH('今日更新数'); ?></p>
                </div>
            </td>
            <td style="width:9px"></td>
            <td class='footer'>
                <div class="inline_block_height_spacer"></div>
                <div>
                    <a class='btn' href="javascript:scrollTo(0,0);"><?php echo $this->FROM_ZH('返回顶部') ?></a>
                    <br />
                    <div class = 'inline_block_height_spacer'></div>
                    <p style='font-size:12px;margin:0px;'><?php echo $this->LanguageAppendix=='zh'?$this->Footnote:$this->FootnoteEN; ?></p>
                    <?php if($this->UseLanguage() == 'zh'){?>
                        <p style='font-size:12px;margin:0px;'>使用 <a href='http://www.wellobserve.com/?page=MDWiki/index.md' style='padding:1px;border:none;'>LAMDWIKI</a> 创建
                    <?php }else if ($this->UseLanguage() == 'en'){?>
                        <p style='font-size:12px;margin:0px;'>Created using <a href='http://www.wellobserve.com/?page=MDWiki/index.md' style='padding:1px;border:none;'>LAMDWIKI</a></p>
                    <?php } ?>
                </div>
            </td>
            </tbody></table>
            </div>
        </div>
        
        <script>
            var tables = document.getElementsByClassName('la_actual_table');
            var i;
            for (i=0; i<tables.length; i++){
                var rows = tables[i].getElementsByTagName('tr');
                var j;
                for (j=0; j<rows.length; j++){
                    rows[j].addEventListener("click", function() {
                        if(!this.classList || !this.classList.length){
                            this.classList.add('recent_updated_half');
                        }else if(this.classList.contains('recent_updated_half')){
                            this.classList.remove('recent_updated_half');
                            this.classList.add('recent_updated');
                        }else if(this.classList.contains('recent_updated')){
                            this.classList.remove('recent_updated');
                        }else{
                            this.classList.add('recent_updated_half');
                        }
                    });
                }
            }
        
            var lg_toggle  = document.getElementById("LoginToggle");
            var lg_panel = document.getElementById("LoginPanel");

            if(lg_toggle && lg_panel) lg_toggle.addEventListener("click", function() {
                var shown = lg_panel.style.display == 'block';
                lg_panel.style.display = shown ? "none" : "block";
                header = document.getElementById("Header");
                if(!shown){
                    header.style.zIndex=100;
                    la_show_modal_blocker();
                }else{
                    header.style.zIndex="";
                    la_hide_modal_blocker();
                }
            });
            <?php if(!$this->IsTaskManager){ ?>
            var hb = document.getElementById("HomeButton");
            var nav = document.getElementById("Navigation");
            hb.addEventListener("click", function() {
                var disp = nav.style.display;
                nav.style.cssText = disp==''?'display:block':'';
                header = document.getElementById("Header");
                if(!disp){
                    header.style.zIndex=100;
                    la_show_modal_blocker();
                }else{
                    header.style.zIndex="";
                    la_hide_modal_blocker();
                }
            });
            <?php } ?>
            
            var img = [];
            
            function la_mark_div_highlight(div_id){
                div = document.getElementById(div_id);
                div.style.backgroundColor="<?php echo $this->chighlight; ?>";
            }
            function la_select_closest_parent(elem, selector){
                for(;elem && elem != document; elem = elem.parentNode){
                    if(elem.matches(selector)) return elem;
                }
            }
            function la_enter_block_editing(elem){
                block = la_select_closest_parent(elem,".main_content");
                if(!block) block = la_select_closest_parent(elem,".additional_content");
                if(!block) return;
                block.style.zIndex=50;
                block.style.position="relative";
                la_show_modal_blocker();
            }
            function la_exit_block_editing(elem){
                block = la_select_closest_parent(elem,".main_content");
                if(!block) block = la_select_closest_parent(elem,".additional_content");
                if(!block) return;
                block.style.zIndex="";
                block.style.position="";
                la_hide_modal_blocker();
            }
            
        </script>
            <div id='image_viewer' class='top_panel full_screen_window' style='display:none'>
                <div id='image_alt' style='float:left'></div>
                <div style='float:right'>
                    <div class='btn' id='image_viewer_close'>关闭</div>
                </div>
                <div id='image_white_area' class='plain_block'; style='top:50px;bottom:10px;left:10px;right:10px;position:absolute;'>
                    <img id='image_viewer_image' style='max-width:100%;max-height:100%;position:absolute;left:0;right:0;top:0;bottom:0;margin:auto;pointer-events:auto;'></img>
                </div>
            </div>
        <script>
            var dialog=document.getElementById('image_viewer')
            var image=document.getElementById("image_viewer_image")
            var close=document.getElementById("image_viewer_close")
            var image_alt=document.getElementById("image_alt")
            var image_white=document.getElementById("image_white_area")
            var img = document.getElementsByTagName("img");
            var bkg_img = document.getElementsByTagName("div");
            for (var i=0; i<img.length-1;i++){
                img[i].onclick=function(){
                    image.src=this.src;
                    image_alt.innerHTML=this.alt;
                    dialog.style.display="block";
                    la_show_modal_blocker();
                }
                
            }
            close.onclick=function(){
                dialog.style.display='none';
                la_hide_modal_blocker();
            }
            image_white.onclick=function(){
                dialog.style.display='none';
                la_hide_modal_blocker();
            }
            
            <?php
            if (!isset($_GET["moving"]) && isset($this->FolderNameList[0])) foreach ($this->FolderNameList as $f){
                ?>
                
                
                
                document.getElementById("<?php echo 'folder_option_btn_'.$f; ?>").
                    addEventListener("click", function() {
                    var fo = document.getElementById("<?php echo 'folder_option_'.$f; ?>");
                    var fd = document.getElementById("<?php echo 'folder_delete_panel_'.$f; ?>");
                    var fr = document.getElementById("<?php echo 'folder_rename_panel_'.$f; ?>");
                    var hidden = fo.style.display == 'none';
                    fo.style.display = hidden ? "inline-block" : "none";
                    fd.style.display = "none";
                    fr.style.display = "none";
                });
                
                document.getElementById("<?php echo 'folder_delete_btn_'.$f; ?>").
                    addEventListener("click", function() {
                    var fo = document.getElementById("<?php echo 'folder_option_'.$f; ?>");
                    var fd = document.getElementById("<?php echo 'folder_delete_panel_'.$f; ?>");
                    var fr = document.getElementById("<?php echo 'folder_rename_panel_'.$f; ?>");
                    var hidden = fd.style.display == 'none';
                    fd.style.display = hidden ? "inline-block" : "none";
                    fr.style.display = "none";
                });
                
                document.getElementById("<?php echo 'folder_rename_btn_'.$f; ?>").
                    addEventListener("click", function() {
                    var fo = document.getElementById("<?php echo 'folder_option_'.$f; ?>");
                    var fd = document.getElementById("<?php echo 'folder_delete_panel_'.$f; ?>");
                    var fr = document.getElementById("<?php echo 'folder_rename_panel_'.$f; ?>");
                    var hidden = fr.style.display == 'none';
                    fd.style.display = "none";
                    fr.style.display = hidden ? "inline-block" : "none";
                });
                
                <?php
            }

            if (!isset($_GET["moving"]) && isset($this->FileNameList[0])) foreach ($this->FileNameList as $f){
                ?>
                
                try{
                    document.getElementById("passage_show_detail_<?php echo $f;?>").
                        addEventListener("click", function() {
                        
                        var detail_l = document.getElementById("passage_preview_<?php echo $f;?>");
                        var detail_r = document.getElementById("passage_detail_<?php echo $f;?>");
                        var summary_l = document.getElementById("passage_title_<?php echo $f;?>");
                        var summary_r = document.getElementById("passage_filename_<?php echo $f;?>");
                        detail_l.style.display = "block";
                        detail_r.style.display = "block";
                        summary_l.style.display =" none";
                        summary_r.style.display = "none";
                    });
                    document.getElementById("passage_close_detail_<?php echo $f;?>").
                        addEventListener("click", function() {
                        
                        var detail_l = document.getElementById("passage_preview_<?php echo $f;?>");
                        var detail_r = document.getElementById("passage_detail_<?php echo $f;?>");
                        var summary_l = document.getElementById("passage_title_<?php echo $f;?>");
                        var summary_r = document.getElementById("passage_filename_<?php echo $f;?>");
                        detail_l.style.display = "none";
                        detail_r.style.display = "none";
                        summary_l.style.display =" block";
                        summary_r.style.display = "block";
                    });
                    
                    document.getElementById("passage_rename_button_<?php echo $f;?>").
                        addEventListener("click", function() {
                        var original_inner = document.getElementById("passage_detail_inner_<?php echo $f;?>");
                        var rename_inner = document.getElementById("passage_rename_inner_<?php echo $f;?>");
                        var delete_inner = document.getElementById("passage_delete_inner_<?php echo $f;?>");
                        var cancel_button = document.getElementById("passage_operation_close_<?php echo $f;?>");
                        original_inner.style.display='none';
                        rename_inner.style.display='block';
                        delete_inner.style.display='none';
                        cancel_button.style.display='inline';
                    });
                    
                    document.getElementById("passage_delete_button_<?php echo $f;?>").
                        addEventListener("click", function() {
                        var original_inner = document.getElementById("passage_detail_inner_<?php echo $f;?>");
                        var rename_inner = document.getElementById("passage_rename_inner_<?php echo $f;?>");
                        var delete_inner = document.getElementById("passage_delete_inner_<?php echo $f;?>");
                        var cancel_button = document.getElementById("passage_operation_close_<?php echo $f;?>");
                        original_inner.style.display='none';
                        rename_inner.style.display='none';
                        delete_inner.style.display='block';
                        cancel_button.style.display='inline';
                    });
                    
                    document.getElementById("passage_operation_close_<?php echo $f;?>").
                        addEventListener("click", function() {
                        var original_inner = document.getElementById("passage_detail_inner_<?php echo $f;?>");
                        var rename_inner = document.getElementById("passage_rename_inner_<?php echo $f;?>");
                        var delete_inner = document.getElementById("passage_delete_inner_<?php echo $f;?>");
                        var cancel_button = document.getElementById("passage_operation_close_<?php echo $f;?>");
                        original_inner.style.display='block';
                        rename_inner.style.display='none';
                        delete_inner.style.display='none';
                        cancel_button.style.display='none';
                    });
                }catch(err){
                }
   
                <?php
            }
            
            if (!isset($_GET["moving"]) && isset($this->OtherFileNameList[0])) foreach ($this->OtherFileNameList as $f){
                ?>
                document.getElementById("<?php echo 'other_files_option_btn_'.$f; ?>").
                    addEventListener("click", function() {
                    var fo = document.getElementById("<?php echo 'other_files_option_'.$f; ?>");
                    var fd = document.getElementById("<?php echo 'other_delete_panel_'.$f; ?>");
                    var fr = document.getElementById("<?php echo 'other_rename_panel_'.$f; ?>");
                    var hidden = fo.style.display == 'none';
                    fo.style.display = hidden ? "inline-block" : "none";
                    fd.style.display = "none";
                    fr.style.display = "none";
                });
                
                document.getElementById("<?php echo 'other_delete_btn_'.$f; ?>").
                    addEventListener("click", function() {
                    var fo = document.getElementById("<?php echo 'other_files_option_'.$f; ?>");
                    var fd = document.getElementById("<?php echo 'other_delete_panel_'.$f; ?>");
                    var fr = document.getElementById("<?php echo 'other_rename_panel_'.$f; ?>");
                    var hidden = fd.style.display == 'none';
                    fd.style.display = hidden ? "inline-block" : "none";
                    fr.style.display = "none";
                });
                
                document.getElementById("<?php echo 'other_rename_btn_'.$f; ?>").
                    addEventListener("click", function() {
                    var fo = document.getElementById("<?php echo 'other_files_option_'.$f; ?>");
                    var fd = document.getElementById("<?php echo 'other_delete_panel_'.$f; ?>");
                    var fr = document.getElementById("<?php echo 'other_rename_panel_'.$f; ?>");
                    var hidden = fr.style.display == 'none';
                    fd.style.display = "none";
                    fr.style.display = hidden ? "inline-block" : "none";
                });
                
                <?php
            }

            ?>
        </script>
        </div>
        </body>
        <?php
    }
    
    function ProcessLinksToStatic($html_content){
    
        if(!isset($_GET['static_generator'])) return $html_content;
        
        return preg_replace_callback('/<a([\s\S]*)href=[\'\"]\?page=([\s\S]*)\.md[\s\S]*[\'\"]([\s\S]*)>([\s\S]*)<\/a>/U',
                             function (&$matches) {
                                 return '<a'.$matches[1].'href="'.$this->GetRelativePath($this->PagePath,$matches[2]).'.html"'.$matches[3].'>'.$matches[4].'</a>';
                             },
                             $html_content);
    }
    function MakeModalBlocker(){
        ?>
        <div id="MODAL_BLOCKER" class="modal_block" style="display:none;"></div>
        <script>
        function la_show_modal_blocker(){
            mb = document.getElementById("MODAL_BLOCKER");
            mb.style.display="block";
        }
        function la_hide_modal_blocker(){
            mb = document.getElementById("MODAL_BLOCKER");
            mb.style.display="none";
        }
        </script>
        <?php
    }
    function MakeTaskMasterHeader(){
    ?>  
        <?php if($this->Trackable && pathinfo($this->PagePath,PATHINFO_BASENAME)!=pathinfo($this->TrackerFile,PATHINFO_BASENAME)){ ?><a href="?page=<?php echo $this->TrackerFile ?>" style='margin:0px;'><?php echo $this->FROM_ZH('总览') ?></a><?php } ?>
        <span class="hidden_on_desktop_inline" ><span id="task_master_header"> <?php echo $this->TaskManagerTitle; ?> </span></span>
        <span class="hidden_on_mobile"><span id="task_master_header_desktop"> <?php echo $this->FROM_ZH('当前在') ?> <?php echo $this->TaskManagerTitle; ?> </span></span>
    <?php
    }
}

?>
