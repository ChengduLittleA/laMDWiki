<?php

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
    
    protected $IsEditing;
    
    protected $IsTaskManager;
    protected $TaskManagerEntries;
    protected $TaskManagerTitle;
    protected $TaskManagerGroups;
    protected $TaskManagerSelf;
    protected $TrackerFile;
    protected $Trackable;
    protected $GLOBAL_TASK_I;
    
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
    protected $StringTitle;
    protected $Footnote;
    protected $SmallQuoteName;
    
    protected $BackgroundSemi;
    
    protected $MainContentAlreadyBegun;
    
    protected $unique_item_count;
    
    protected $force_last_line;
    
    protected $DICT;
    
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
    
    function __construct() {
        $this->PDE = new ParsedownExtra();
        $this->PDE->SetInterlinkPath('/');
        $this->AddTranslationEntry('返回','Back');
        $this->AddTranslationEntry('上级','Up');
        $this->AddTranslationEntry('首页','Home');
        $this->AddTranslationEntry('列表','List');
        $this->GLOBAL_TASK_I=0;
    }
    
    function LimitAccess($mode){
        if($mode==0){
            echo $this->MakeCenterContainerBegin();
            echo "<div class='the_body'>";
            echo "<div class='main_content' style='text-align:center;'>";
            echo "<h1>404</h1>";
            echo "<p>页面不存在。<br />Page does not exist.<br />".$_GET["page"]."</p><p>";
            if(isset($_SERVER["HTTP_REFERER"])) echo "<a href='".$_SERVER["HTTP_REFERER"]."'>🡰 返回/Back</a>";
            echo "&nbsp;<a href='?page=index.md'>⌂ 首页/Home</a></p>";
            if($this->IsLoggedIn()) echo "<p><a href='?page=".$_GET["page"]."&operation=new&title=".pathinfo($_GET["page"],PATHINFO_FILENAME)."'>创建这个页面</a></p>";
            echo "</div>";
            echo "</div>";
            echo $this->MakeCenterContainerEnd();  
        }else if($mode==1){
            echo $this->MakeCenterContainerBegin();
            echo "<div class='the_body'>";
            echo "<div class='main_content' style='text-align:center;'>";
            echo "<h1>停一下</h1>";
            echo "访客不允许访问这个页面。<br />Visitors can not access this page.<p>";
            if(isset($_SERVER["HTTP_REFERER"])) echo "<a href='".$_SERVER["HTTP_REFERER"]."'>🡰 返回/Back</a>";
            echo "&nbsp;<a href='?page=index.md'>⌂ 首页/Home</a></p>";
            echo "</div>";
            echo "</div>";
            echo $this->MakeCenterContainerEnd();
        }
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
        if ((!file_exists($path) || is_readable($path) == false)
            &&!isset($_GET['operation'])
            &&!isset($_GET['moving'])
            &&!isset($_POST['button_new_passage'])) {
            return false;
        }
        $this->PagePath = $path;
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
        if ($this->GetBlock($Config,$BlockName)==Null){
            $this->AddBlock($Config,$BlockName);
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
    
    function MakeAudioTag($name,$file){

        
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
            '<audio id="AUDIO_$1"><source src="'.$this->InterlinkPath().'/$2" type="audio/ogg"></audio>
<div class="btn" style="pointer-events:none;">音频：$1</div>'
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
    
    function HTMLFromMarkdown($Content){
        return $this->PDE->text($this->RemoveMarkdownConfig($Content));
    }
    
    function HTMLFromMarkdownFile($FileName){
        $Content = $this->ContentOfMarkdownFile($FileName);
        if($Content) return $this->HTMLFromMarkdown($this->InsertAdaptiveContents($Content));
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
        
        $file_orig = preg_replace('/_\D\D\.md$/','.md',$path_parts['basename']);
        $file_prefer = preg_replace('/\.md$/','_'.$appendix.'.md',$file_orig);
        
        $path_prefer = $path_parts['dirname'].'/'.$file_prefer;

        if (file_exists($path_prefer) && is_readable($path_prefer))
            return $path_prefer;
        
        return $path_parts['dirname'].'/'.$file_orig;
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
        }
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
            $_SESSION = array();
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
    function DoNewPassage(){
        if(isset($_POST['button_new_passage'])){
            $passage = $_POST['data_passage_content'];
            $file_path = $this->PagePath;

            if(isset($_POST['editor_file_name'])){  //new passage
                $file_name = $this->GetUniqueName($_POST['editor_file_name']);
                $file_path = (isset($_GET['quick'])?$_GET['quick']:$this->InterlinkPath()).'/'.$file_name.'.md';
                $file_path = $this->GetUniquePath($file_path);
            }

            $file = fopen($file_path, "w");
            fwrite($file,$passage);
            fclose($file);

            header('Location:?page='.(isset($_GET['quick'])?$this->PagePath:$file_path).'&translation=disabled');
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
            $file_path = $this->PagePath;
            if(!isset($_GET['quote_quick'])) return;
            $folder = $_GET['quote_quick'];
            $this->AddSmallQuoteEntry($folder,$passage);
            header('Location:?page='.$this->PagePath);
            exit;
        }
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
                header('Location:?page='.$this->InterlinkPath().'&operation=list');
                exit;
            }
        }
        if(isset($_GET['set_draft'])){
            $original_path = $this->PagePath;
            if($_GET['set_draft']==0){
                $target_path = preg_replace("/DRAFT/",'',$original_path);
                rename($original_path,$target_path);
            }else{
                $target_path = preg_replace("/.md/",'DRAFT.md',$original_path);
                rename($original_path,$target_path);
            }
            header('Location:?page='.$target_path);
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
    function PermissionForSingleFolder($path){
        $file = $path.'/la_config.md';
        if(is_readable($file) && filesize($file)!=0){
            $ConfRead = fopen($file,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($file)));
            fclose($ConfRead);
            if($this->CheckLineByNames($Config,'FolderConf','visible','0')) return False;
            else return True;
        }else return True;
    }
    
    function PermissionForFolderRecursive($path){
        $save=$this->InterlinkPath();
        $permission=True;
        if(!$this->PermissionForSingleFolder($path)) $permission=False;
        $a = $path;
        while($permission){
            $t = $this->GetInterlinkPath('..');
            if($t==$a) break;
            $a = $t;
            $permission = $this->PermissionForSingleFolder($path);
            $this->SetInterlinkPath($a);
        }
        $this->SetInterlinkPath($save);
        return $permission;
    }
    
    function SetFolderPermission($path,$visible){
        $file = $path.'/la_config.md';
        if(is_readable($file)){
            $ConfRead = fopen($file,'r');
            $Config = $this->ParseMarkdownConfig(fread($ConfRead,filesize($file)));
            fclose($ConfRead);
            $Block = $this->GetBlock($Config,'FolderConf');
            if(!isset($Block)) $this->AddBlock($Config,'FolderConf');
            $this->EditGeneralLineByName($Config,'FolderConf','visible',$visible?'1':'0');
            $ConfWrite = fopen($file,'w');
            $this->WriteMarkdownConfig($Config, $ConfWrite);
            fclose($ConfWrite);
        }else{
            $ConfWrite = fopen($file,'w');
            $Config = [];
            $this->AddBlock($Config,'FolderConf');
            $this->EditGeneralLineByName($Config,'FolderConf','visible',$visible?'1':'0');
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
            if(isset($_POST['settings_website_display_title'])){
                $this->EditGeneralLineByName($Conf,'Website','DisplayTitle',$_POST['settings_website_display_title']);
            }
            if(isset($_POST['settings_footer_notes'])){
                $this->EditGeneralLineByName($Conf,'Website','Footnote',$_POST['settings_footer_notes']);
            }
            if(isset($_POST['settings_small_quote_name'])){
                $this->EditGeneralLineByName($Conf,'Website','SmallQuoteName',$_POST['settings_small_quote_name']);
            }
            if(isset($_POST['settings_tracker_file'])){
                $this->EditGeneralLineByName($Conf,'Website','TrackerFile',$_POST['settings_tracker_file']);
            }
            if(isset($_POST['settings_admin_display']) && $_POST['settings_admin_display']!=''){
                $this->EditArgumentByNamesN($Conf,'Users',$this->UserID,0,'DisplayName',$_POST['settings_admin_display']);
            }
            if(isset($_POST['settings_admin_password']) && $_POST['settings_admin_password']!=''){
                $this->EditArgumentByNamesN($Conf,'Users',$this->UserID,0,'Password',$_POST['settings_admin_password']);
                $admin_changed=true;
            }
            if(isset($_POST['settings_admin_id']) && $_POST['settings_admin_id']!=''){
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
                    if($_POST['task_editor_content']=="事件描述" || $_POST['task_editor_content']=="") return;
                    if($_POST['task_editor_tags'] == "标签") $_POST['task_editor_tags']="";
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
    
    function GetWebsiteSettings(){
        $this->UserConfig = fopen("la_config.md",'r');
        $ConfContent = fread($this->UserConfig,filesize("la_config.md"));
        fclose($this->UserConfig);
        $Conf = $this->ParseMarkdownConfig($ConfContent);
        $this->Title          = $this->GetLineValueByNames($Conf,"Website","Title");
        $this->StringTitle    = $this->GetLineValueByNames($Conf,"Website","DisplayTitle");
        $this->Footnote       = $this->GetLineValueByNames($Conf,"Website","Footnote");
        $this->SmallQuoteName = $this->GetLineValueByNames($Conf,"Website","SmallQuoteName");
        $this->TrackerFile    = $this->GetLineValueByNames($Conf,"Website","TrackerFile");
        if(!$this->Title) $this->Title='LA<b>MDWIKI</b>';
        if(!$this->StringTitle) $this->StringTitle='LAMDWIKI';
        if(!$this->TrackerFile) $this->TrackerFile='events.md';
        $i=0;$item=null;
        while($this->GetLineByNamesN($Conf,'Redirect','Entry',$i)!==Null){
            $item['from']    = $this->GetArgumentByNamesN($Conf,'Redirect','Entry',$i,'From');
            $item['to']      = $this->GetArgumentByNamesN($Conf,'Redirect','Entry',$i,'To');
            $this->List301[] = $item;
            $i++;
        }
    }
    
    function MakeHTMLHead(){
        $append_title = NULL;
        if($this->PagePath!='./index.md' && $this->PagePath!='index.md'){
            $this->FileTitle = $this->TitleOfFile($this->ContentOfMarkdownFile($this->PagePath));
            $append_title = $this->FileTitle;
            $append_title = preg_replace('/[#*~\s]/',"",$append_title);
        }
        ?>
        <!doctype html>
        <head>
        <meta name="viewport" content="user-scalable=no, width=device-width" />
        <title><?php echo $this->StringTitle ?><?php echo isset($append_title)?" | $append_title":""?></title>
        <style>
        
            html{ text-align:center; }
            body{ width:100%; text-align:left; margin:0px;
                background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAAGUlEQVQImWNgYGD4z4AGMARwSvxnYGBgAACJugP9M1YqugAAAABJRU5ErkJggg==) repeat;
                background-attachment: fixed;
                font-size:16px;
            }
            
            .the_body{ width:60%; min-width:900px; margin: 0 auto; }
            
            del{ color: gray;}
            
            img{ max-width: 100%; margin: 5px auto; display: block; }
            h3 img{ float: right; margin-left: 10px; max-width:30%; clear: right;}
            h4 img{ float: left; margin-right: 10px; max-width:30%; clear: left;}
            a > img{ pointer-events: none; }
            .btn img{ pointer-events: none; }
            .gallery_left img{ float: unset; margin: 5px auto; max-width: 100%;}
            
            table{ width:100%; border-collapse: collapse;}
            
            pre {border-left: 3px double black; padding: 10px; position: relative; z-index: 1; text-align: left; }
            
            blockquote{ border-top:1px solid #000; border-bottom:1px solid #000; text-align: center; }
            
            ::-moz-selection{ background:#000; color:#FFF; }
            ::selection{ background:#000; color:#FFF; }
            ::-webkit-selection{ background:#000; color:#FFF; }
            
            #Header{ position: sticky; top:0px; left:15%; display: block; z-index:10; }
            #WebsiteTitle{ border:1px solid #000; display: inline-block; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; margin-left:0px; margin-right:0px; margin-bottom:15px;
                background-color:#FFF; box-shadow: 5px 5px #000;
            }	
            #HeaderQuickButtons{ border:1px solid #000; display: inline; right:0px; position: absolute; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; margin-right:0px;
                background-color:#FFF; box-shadow: 5px 5px #000;
            }
            
            .wide_title{ border:1px solid #000; display: inline-block; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; margin-left:0px; margin-right:0px; margin-bottom:15px;
                background-color:#FFF; box-shadow: 5px 5px #000; overflow: hidden; width: calc(100% - 22px);
            }

            
            .login_half{ float: right; right: 10px; text-align: right;}
            
            .wide_body              { margin-left: 10px; margin-right:10px; }
            
            .main_content           { padding:20px; padding-left:15px; padding-right:15px; border:1px solid #000; background-color:#FFF; box-shadow: 5px 5px #000; margin-bottom:15px; overflow: auto; scrollbar-color: #000 #ccc; scrollbar-width: thin;}
            .narrow_content         { padding:5px; padding-top:10px; padding-bottom:10px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 3px #000; margin-bottom:15px; max-height:350px; }
            .additional_content     { padding:5px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 3px #000; margin-bottom:15px; overflow: hidden; }
            .task_content           { padding:3px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 2px #000; margin-bottom:5px; overflow: hidden; }
            .inline_notes_outer     { padding:5px; border-left: 3px solid black; border-top: 3px solid black; padding-right: 8px; padding-bottom: 8px; margin-top: 5px; margin-bottom: 5px; }
            .inline_notes_content   { padding:5px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 3px #000; overflow: hidden; }
            .sidenotes_content      { position: absolute; right:10px; max-width: calc(50% - 470px); width: calc(20% - 20px); }
            .sidenotes_position     { position: absolute; width:calc(100% - 13px); min-width: 250px; right:0px; bottom: 15px; display: block; }
            .sidenotes_expander     { position: absolute; left:0px; bottom: 15px; display: none;}
            .gallery_left .sidenotes_content   { position: relative; right: unset; max-width: unset; width: unset; overflow: hidden; padding: 3px; margin-top: -25px; margin-bottom: -10px; }
            .gallery_left .sidenotes_position  { position: relative; width: unset; min-width: unset; right: unset; bottom: unset; display: none; margin-top: 10px; }
            .gallery_left .sidenotes_expander  { position: relative; left: unset; bottom: unset; display: block; float: right; width: 20px; }
            .additional_content_left{ margin-right: 15px; float: left; text-align: center; position: sticky; top:82px; margin-bottom:0px;}
            .novel_content          { max-width:600px; margin:0px auto; line-height:2; }
            .more_vertical_margin   { margin-top: 100px; margin-bottom: 100px; }
            .small_shadow           { box-shadow: 2px 2px #000; }
            .tile_content           { padding:10px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 3px #000; margin-bottom:15px; max-height:350px; }
            .top_panel              { padding:10px; padding-top:15px; padding-bottom:15px; border:1px solid #000; background-color:#FFF; box-shadow: 5px 5px #000; margin-bottom:15px; overflow: hidden; }
            .full_screen_window     { top:10px; bottom:10px; left:10px; right:10px; position: fixed; z-index:1000; max-height: unset;}
            .gallery_left           { height:calc(100% - 160px); position: fixed; width:350px; }
            .gallery_right          { width:calc(100% - 365px); left: 365px; position: relative;}
            .gallery_main_height    { max-height: 100%; }
            .gallery_multi_height   { position: relative;}
            .gallery_multi_height::before   { content: " "; display: block; padding-top: 100%; }
            .gallery_multi_content  { position: absolute;top: 5px; left: 5px; bottom: 5px; right: 5px; display: flex; align-items: center; overflow: hidden;}
            .gallery_image          { max-width: unset; min-width: 100%; min-height: 100%; object-fit: cover; }
            .gallery_box_when_bkg   { width:30%; max-width:300px;}
            .no_padding             { padding: 0px; }
            
            .center_container       { display: table; position: absolute; top: 0; left: 0; height: 100%; width: 100%; }
            .center_vertical        { display: table-cell; vertical-align: middle; }
            .center_box             { margin-left: auto; margin-right: auto; }
            
            .file_image_preview     { width:90%; max-width:300px; margin:5px; }
            
            .adaptive_column_container { text-align: center; display: table-cell; }
            
            .underline_when_hover:hover { text-decoration: underline; }
            
            .audio_player_box       { z-index:20; padding:10px; border:1px solid #000; background-color:#FFF; box-shadow: 5px 5px #000; bottom:15px; overflow: hidden; position: sticky; margin:15px auto; margin-top:0px; width:calc(60% - 55px); min-width:845px;}
            .bottom_sticky_menu_container { z-index:20; padding:10px; overflow: visible; position: sticky; bottom:80px; margin:15px auto; margin-bottom:0px; width:calc(60% - 33px); min-width:867px; }
            .bottom_sticky_menu_left      { z-index:20; position: absolute; padding:10px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 3px #000; left:10px; bottom:10px; overflow: hidden; margin:0px;  width:50%; }
            .bottom_sticky_menu_right     { z-index:20; position: absolute; padding:10px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 3px #000; right:10px; bottom:10px; overflow: hidden; margin:0px;  width:50%; }
            
            canvas                  { width:100%; height:100%; }
            .canvas_box_warpper_wide           { position: relative;}
            .canvas_box_warpper_wide::before   { content: " "; display: block; padding-top: 56.25%; }
            .canvas_box_warpper_super          { position: relative;}
            .canvas_box_warpper_super::before  { content: " "; display: block; padding-top: 41.8%; }
            .canvas_box                        { position: absolute;top: 0px; left: 0px; bottom: 0px; right: 0px; display: flex; align-items: center; overflow: hidden;}
            .canvas_box_expanded               { position: relative; height:100%; max-height:calc(100% - 250px); min-height:200px;}
            
            .block_image_normal                { position: relative; text-align: center; }
            .block_image_expanded              { position: relative; text-align: center; }
            .block_image_expanded img          { margin: 0px auto; max-height:100vh; max-width:100% }
            .block_image_normal   img          { margin: 0px auto; max-height:100vh; max-width:100% }
            
            .box_complete_background           { position: fixed; top: 0px; left: 0px; bottom: 0px; right: 0px; z-index: -1;}
            .box_hang_right                    { float: right; width:30%;}
            
            .white_bkg    { background-color:#FFF; }
            
            .modal_block  { background-color:rgba(0,0,0,0.2); position: fixed; z-index:30; top: 0px; left: 0px; bottom: 0px; right: 0px; }
            .modal_dialog { z-index:50; }
            .modal_on_mobile { z-index:0; }
            
            .btn          { border:1px solid #000; padding: 5px; color:#000; display: inline; background-color:#FFF; font-size:16px; cursor: pointer; text-align: center; }
            .btn:hover    { border:3px double #000; padding: 3px; }
            .btn:active   { border:5px solid #000; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px; }
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
            
            .halftone1  { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAGElEQVQYlWNgIBH8HxyKaQ+Icg71FGEAAMIRBftlPpkVAAAAAElFTkSuQmCC) repeat; }
            .halftone1w { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAMElEQVQYlWP4TyRg+P///38GBgbiFBKjGEUWn2LCdlJdIcw5eBUiuxmnQnSPEe1GAL6NfJLaO8bfAAAAAElFTkSuQmCC) repeat; }
            .halftone2  { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAD0lEQVQImWNgIAf8J10LADM2AQA1DEeOAAAAAElFTkSuQmCC) repeat; }
            .halftone2w { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAFklEQVQImWP4jwUw4BVkYGAgUiUyAADQo2Cg/XS+dwAAAABJRU5ErkJggg==) repeat; }
            .halftone3  { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHUlEQVQImWNgYGD4j4YZGLAI/GfAIgAXxKYD1VwA+JoT7dVZ0wkAAAAASUVORK5CYII=) repeat; }
            .halftone3w { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAJElEQVQImW3IoREAAAjEsO6/dBEI/gAREwCTKk9MRnSukBNgAQ7LJ9m50jTuAAAAAElFTkSuQmCC) repeat; }
            .halftone4  { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAYAAABWKLW/AAAADklEQVQImWNgQID/uBkANfEC/tK2Q2IAAAAASUVORK5CYII=) repeat; }
            .halftone4w { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAMAAAADCAYAAABWKLW/AAAAGElEQVQImWP4DwUMDAz/GWAMKM0Ak/wPAAg7Gub7fKZwAAAAAElFTkSuQmCC) repeat; }
            
            .inline            { display: inline; margin:0px; }
            .inline_components { display: inline; margin: 5px; }
            .plain_block       { border: 1px solid #000; text-align: left; padding:5px; }
            .preview_block     { border-right: 1px solid #000; margin:5px; }
            .border_only       { border: 1px solid #000; }
            
            .inline_p          { }
            .inline_p p, .inline_p h1, .inline_p h2, .inline_p h3, .inline_p h4, .inline_p h5, .inline_p h6, .inline_p { display: inline; margin:0px; }
            
            .string_input      { border:3px solid #000; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px; margin:5px; width:150px; }
            .quick_post_string { border:3px solid #000; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px; margin:0px; width:100%; resize: none; overflow: hidden; height: 25px; }
            .big_string        { width: calc(100% - 10px); height: 500px; resize: vertical; border:none; }
            .big_string_height { height: 500px; }
            .title_string      { margin-top:-5px; margin-bottom:-5px; font-size:16px; text-align: right; }
            
            .no_horizon_margin { margin-left:0px; margin-right:0px; }
            
            .navigation        { border:1px solid #000; display: inline; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; right:0px; background-color:#FFF; box-shadow: 5px 5px #000; white-space: nowrap; text-align: center;}
            .navigation p      { display: inline; }
            
            .navigation_task p { display: inline; }
            .navigation_task   { display: inline; }
            
            .tile_container    { display: table; table-layout: fixed; width: calc(100% + 30px); border-spacing:15px 7px; margin-left: -15px; margin-top: -7px; margin-bottom: 8px; }
            .tile_item         { display: table-cell; }
            
            .footer            { padding:10px; padding-top:15px; padding-bottom:5px; border:1px solid #000; background-color:#FFF; box-shadow: 5px 5px #000; margin-bottom:15px; overflow: hidden; display: inline-block; }
            .additional_options{ padding:5px; padding-top:10px; padding-bottom:10px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 3px #000; margin-bottom:-15px; overflow: hidden; display: inline-block; position: relative; z-index:100; }
            
            
            a        { border:1px solid #000; padding: 5px; color:#000; text-decoration: none; }
            a:hover  { border:3px double #000; padding: 3px; }
            a:active { border:5px solid #000; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px; }
            .main_content a       { padding: 0px; padding-left:3px; padding-right:3px; display: inline-block;}            
            .main_content a:hover { border:1px solid #000; text-decoration: underline; }
            .main_content a:active{ border:1px solid #000; color:#FFF; background-color:#000; }
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
            
            .appendix { text-align: right; font-size: 12px; line-height: 1.2;}
            
            .hidden_on_desktop       { display: none; }
            .hidden_on_desktop_inline{ display: none; }
            .only_on_print           { display: none; }
            
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
                
                .sidenotes_content   { position: relative; right: unset; max-width: unset; width: unset; overflow: hidden; padding: 3px; margin-top: -25px; margin-bottom: -10px; }
                .sidenotes_position  { position: relative; width: unset; min-width: unset; right: unset; bottom: unset; display: none; margin-top: 10px; }
                .sidenotes_expander  { position: relative; left: unset; bottom: unset; display: block; float: right; width: 20px; }
                
                .gallery_left           { height: unset; position: unset; width: unset; }
                .gallery_right          { width: unset; left: unset; z-index:10; position: unset; }
                .gallery_main_height    { height: unset; }
                .gallery_multi_height::before    { display: none; }
                .gallery_multi_content  { position: unset;}
                .gallery_image          { max-width: 100%; min-width: unset; min-height: unset; object-fit: unset;}
                .gallery_box_when_bkg   { width:60%; max-width: unset;}
                
                .box_hang_right         { float: unset; width: unset;}
                
                .audio_player_box       { margin:10px auto;  width:calc(100% - 60px); min-width:unset;}
                .bottom_sticky_menu_container { margin:10px auto;  width:calc(100% - 38px); min-width:unset;}
                .bottom_sticky_menu_left      { width: 300px; max-width:calc(100% - 42px); }
                .bottom_sticky_menu_right     { width: 300px; max-width:calc(100% - 42px); }
                
                .adaptive_column_container { display: block; }
                
                .passage_detail         { width:60%; }
                .big_string_height      { height: calc(100% - 10px); }
                
                .novel_content          { max-width: unset;}
                .more_vertical_margin   { margin-top: 0px; margin-bottom: 0px; }
                
                .no_overflow_mobile     { overflow: unset;}
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
                .top_panel              { padding:0;  border: none; background-color:#FFF; box-shadow: unset; margin:0; overflow: unset; }
                .full_screen_window     { top:10px; bottom:10px; left:10px; right:10px; position: fixed; z-index:1000; max-height: unset;}
                .gallery_left           { height: unset; width: unset; position: unset; padding:0;  border: none; background-color:#FFF; box-shadow: unset; margin:0; overflow: unset; }
                .gallery_right          { height: unset; width: unset; left: unset; z-index:10; position: unset; padding:0;  border: none; background-color:#FFF; box-shadow: unset; margin:0; overflow: unset; }
                .gallery_main_height    { max-height: unset }
                .no_padding             { padding: 0px; }
                
                .print_document h1{ border-left: 10px solid black; padding-left:10px; border-bottom: 1px solid black; margin-bottom: 5px }
                
                .print_document h2{ border-left: 5px solid black; padding-left:5px;}
                
                .print_document h3{ border-left: 1px solid black; padding-left:9px;}
                
                .gallery_left h1, .gallery_left h2, .gallery_left h3, .gallery_left h4, .gallery_left h5, .gallery_left h6 { display: inline; }
                
                pre{ white-space: pre-wrap; border: 1px dotted black; }
                
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
        ?>
        <div class='hidden_on_print' style='background-color:#000; height:10px; margin-top: -20px;margin-left: -15px;margin-right: -15px; margin-bottom:15px;'>
            <div style='width:600px; max-width:100%; height:100%; font-size:0px; overflow:hidden;'>
            <?php
                $this->SpetialStripeSegment('3.97%','#550000');
                $this->SpetialStripeSegment('2.26%','#800000');
                $this->SpetialStripeSegment('0.53%','#d40000');
                $this->SpetialStripeSegment('1.75%','#cd4a00');
                $this->SpetialStripeSegment('5.15%','#cd6200');
                $this->SpetialStripeSegment('2.06%','#f6a400');
                $this->SpetialStripeSegment('1.76%','#ffed22');
                $this->SpetialStripeSegment('0.80%','#f6ff0f');
                $this->SpetialStripeSegment('1.04%','#c7fb00');
                $this->SpetialStripeSegment('3.50%','#59e800');
                $this->SpetialStripeSegment('4.12%','#00c000');
                $this->SpetialStripeSegment('1.02%','#009245');
                $this->SpetialStripeSegment('0.55%','#00875f');
                $this->SpetialStripeSegment('1.28%','#00796f');
                $this->SpetialStripeSegment('3.14%','#006879');
                $this->SpetialStripeSegment('5.14%','#005b7c');
                $this->SpetialStripeSegment('1.58%','#004897');
                $this->SpetialStripeSegment('1.02%','#001cb7');
                $this->SpetialStripeSegment('0.76%','#190087');
                $this->SpetialStripeSegment('1.90%','#4600a7');
                $this->SpetialStripeSegment('4.35%','#6c00bd');
                $this->SpetialStripeSegment('1.30%','#8e00c2');
                $this->SpetialStripeSegment('1.08%','#cb00d5');
                $this->SpetialStripeSegment('0.70%','#ff2ad4');
                $this->SpetialStripeSegment('1.39%','#ff179c');
                $this->SpetialStripeSegment('4.00%','#ff016b');
                $this->SpetialStripeSegment('1.96%','#e8004e');
                $this->SpetialStripeSegment('1.02%','#c40028');
                $this->SpetialStripeSegment('1.95%','#a0000f');
                $this->SpetialStripeSegment('5.54%','#900000');
                $this->SpetialStripeSegment('11.52%','#780000');
                $this->SpetialStripeSegment('5.20%','#5e0000');
                $this->SpetialStripeSegment('6.14%','#4e0000');
                $this->SpetialStripeSegment('10.51%','#3e0000');
            ?>
            </div>
        </div>
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
        ob_start();
        ?>
        <?php if(!$this->IsTaskManager){ ?>
            <div id='WebsiteTitle'>
                <a class='hidden_on_mobile' href="?page=index.md"><?php echo $this->Title;?></a>
                <a class='hidden_on_desktop_inline' id='HomeButton' ><?php echo $this->Title;?>...</a>
                <?php if($this->Trackable){ ?><a class='hidden_on_mobile' href="?page=<?php echo $this->TrackerFile; ?>">跟踪</a> <?php } ?>
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
            }
            </script>
        <?php } ?>
        <?php
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
    function MakeMainContentBegin(){
        $layout = $this->GetAdditionalLayout();
        $this->AdditionalLayout = $layout;
        $novel_mode = $this->FolderNovelMode($this->InterlinkPath());
        
        if(!$this->MainContentAlreadyBegun){
            ?>
            <div class='the_body'>
            <?php
        }
        
        if($layout == 'Gallery' && (!isset($_GET['operation'])||($_GET['operation']!='edit'&&$_GET['operation']!='new'))){
        ?>
            <div class='gallery_left'>
            <div class='main_content gallery_main_height'>
            <?php echo $this->MakeSpecialStripe(); ?>
        <?php
        }else{
        ?>
            <div class='main_content <?php echo $novel_mode?"":"print_document" ?>' style='<?php echo $this->BackgroundSemi?"background-color:rgba(255,255,255,0.95);":""?>'>
            <?php echo $this->MakeSpecialStripe(); ?>
            <div class='<?php echo ($novel_mode && !$this->GetEditMode())?"novel_content more_vertical_margin":""?>'>
        <?php
        }
    }
    function MakeMainContentEnd(){
        ?>
            </div>
            </div>
            </div>
        <?php
    }
    
    function RemoveBlankAfterInserts($html){
        return preg_replace('/<div.*class=[\'\"]the_body[\'\"].*>\s*<div.*class=[\'\"]main_content[\'\"].*>\s*<div>\s*<\/div>\s*<\/div>\s*<\/div>/U',
                            "",
                            $html);
    }
    
    function InsertAdaptiveContents($markdown){
        $op1 = preg_replace_callback("/```([\s\S]*)```/U",
                            function($matches){
                                return preg_replace('/\[adaptive\]/','[@adaptive]',$matches[0]);
                            },
                            $markdown);
        $res = preg_replace_callback('/\[adaptive\]([\s\S]*)\[\/adaptive\]/U',
                                     function($matches){
                                         return "<table style='table-layout: fixed;'> <tr>".
                                                preg_replace_callback('/\[column\]([\s\S]*)\[\/column\]/U',
                                                                      function($matches){
                                                                          return "<td class='adaptive_column_container'>".
                                                                                 $this->HTMLFromMarkdown($matches[1]).
                                                                                 "</td>";
                                                                      },
                                                                      $matches[1]).
                                                "</tr> </table>";
                                     },
                                     $op1);
        return preg_replace('/```([\s\S]*)\[@adaptive\]([\s\S]*)```/U',
                             '```$1[adaptive]$2```',
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
            <div class='main_content' style="<?php echo $no_padding?'padding:0px;':''?> <?php echo $this->BackgroundSemi?"background-color:rgba(255,255,255,0.95);":""?>">
                 <div>
        <?php } 
        if($hang){
            ?>
            <div class='additional_content box_hang_right' style='padding:0px;'>
            <?php
        }
        }// not background
        ?>
                
                <div class="<?php echo $is_background?'box_complete_background':($expanded?'canvas_box_expanded':'canvas_box_warpper_wide')?>">
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
                    
                    var solid_mat<?php echo $id?> = new THREE.MeshBasicMaterial({color:0xffffff, polygonOffset: true,
                                                                    polygonOffsetFactor: 1,
                                                                    polygonOffsetUnits: 1});
			        //var line_mat<?php echo $id?> = 
			        
			        scene<?php echo $id?>.background = new THREE.Color( 0xffffff );
			        
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
                                            line_mat = new THREE.LineBasicMaterial( { color: 0x000000, linewidth: 1+child.material.roughness, linecap: 'round', linejoin:  'round'} );
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
                ?>          </div>
                        </div>
                    </div>
                <?php if($hooked || !$this->AfterPassage3D) {?>
                    <div class='the_body'>
                    <?php 
                    $this->MainContentAlreadyBegun=True;
                }
                if($hooked) echo '<div class="main_content" style="'.($this->BackgroundSemi?"background-color:rgba(255,255,255,0.95);":"").'"><div>';
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
                ?>          </div>
                        </div>
                    </div>
                <?php if($hooked || !$this->AfterPassage2D) {?>
                    <div class='the_body'>
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
        if(!$this->AfterPassage2D){
            echo $Content2D;
        }
        if(!$this->AfterPassage3D){
            echo $Content3D;
        }
    }
    function HandleInsertsAfterPassage($Content2D,$Content3D){
        if($this->AfterPassage2D){
            echo $Content2D;
        }
        if($this->AfterPassage3D){
            echo $Content3D;
        }
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
            $this->ReadTaskFolderDescription(NULL,$actual,$this->TaskManagerTitle);
            return True;
        }else{
            return False;
        }
    }
    function IsTaskManager(){
        return $this->IsTaskManager;
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
    function ReadTaskFolderDescription($folder, $override_file, &$group_name){
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
            $item['past_count'] = 30;
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
                        
                        $this->ReadTaskItems($path, $this->FileNameList, $pc, date('Y'), date('m'), date('d'), $unfinished_items, $finished_items, $active_items);
                        
                        $this->ReadTaskFolderDescription($path, NULL,$folder_title);
                        $folder_item['title'] = $folder_title;
                        $folder_item['path'] = $path;
                        $folder_item['past_count']=$pc;
                        $groups[] = $folder_item;
                    }else{
                        $folder_item['title'] = '无法读取';
                        $folder_item['path'] = $path;
                        $folder_item['past_count']=1;
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
            $this->MakeTaskGroupAdditional(NULL, 30,$unfinished_items, $finished_items, $active_items);
            $this->TaskManagerGroups = $groups;
        ?>
        </div>
        <?php
    }
    
    function InsertSideNotes($html){
        global $sn_i;
        $sn_i=0;
        $new = preg_replace_callback('/<p>([\(（]注意[:：])([\s\S]*)([\)）])(\s*)<\/p>/Uu',
                                     function($matches){
                                        return '<div class="inline_notes_outer halftone4"> <div class="inline_notes_content">'.
                                               $matches[2].
                                               '</div> </div>';
                                     },$html);
        $new = preg_replace_callback('/<p>([\(（]旁注[:：])([\s\S]*)([\)）])(\s*)<\/p>/Uu',
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
            <div class='btn' onclick='location.href="?page=<?php echo $this->PagePath;?>"'>退出</div>
            <form method="post" id='settings_form' style='display:none;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=settings';?>"></form>
            <h1>设置中心</h1>
            <a id='ButtonWebsiteSettings' style='font-weight:bold'>网站信息</a>
            <a id='Button301Settings'>链接跳转项目</a>
            <a id='ButtonAdminSettings'>管理员</a>
            <div class='inline_height_spacer'></div>
            <div id='TabWebsiteSettings'>
                <input class='string_input no_horizon_margin' type='text' id='settings_website_title' name='settings_website_title' form='settings_form' value='<?php echo $this->Title ?>' />
                网站标题
                <br />
                <input class='string_input no_horizon_margin' type='text' id='settings_website_display_title' name='settings_website_display_title' form='settings_form' value='<?php echo $this->StringTitle ?>' />
                标签显示标题
                <br />
                <input class='string_input no_horizon_margin' type='text' id='settings_footer_notes' name='settings_footer_notes' form='settings_form' value='<?php echo $this->Footnote ?>' />
                页脚附加文字
                <br />
                <br />
                <input class='string_input no_horizon_margin' type='text' id='settings_small_quote_name' name='settings_small_quote_name' form='settings_form' value='<?php echo $this->SmallQuoteName ?>' />
                “我说”名片抬头文字
                <br />
                <br />
                <input class='string_input no_horizon_margin' type='text' id='settings_tracker_file' name='settings_tracker_file' form='settings_form' value='<?php echo $this->TrackerFile ?>' />
                站点事件跟踪器
            </div>

            <div id='Tab301Settings' style='display:none'>
                自动重定向的链接
                <?php if(isset($this->List301)) foreach($this->List301 as $item){ ?>
                    <div>
                        <div style='float:right;width:50%'>到&nbsp;<?php echo $item['to']; ?></div>
                        <?php echo $item['from']; ?>
                    </div>
                <?php } ?>
                <a href='?page=la_config.md&operation=edit'>编辑la_config.md</a>&nbsp;以详细配置。
            </div>
            
            <div id='TabAdminSettings' style='display:none'>
                <input class='string_input no_horizon_margin' type='text' id='settings_admin_display' name='settings_admin_display' form='settings_form' value='<?php echo $this->UserDisplayName ?>' />
                修改账户昵称
                <br /><br />
                <input class='string_input no_horizon_margin' type='text' id='settings_admin_id' name='settings_admin_id' form='settings_form' />
                重设管理账户名
                <br />
                <input class='string_input no_horizon_margin' type='text' id='settings_admin_password' name='settings_admin_password' form='settings_form' />
                重设管理密码
                <br />
            </div>
            
            <hr />
            <div class='inline_block_height_spacer'></div>
            <input class='btn form_btn' type='submit' value='保存所有的更改' name="settings_button_confirm" form='settings_form' />
            <script>
                var btn_website = document.getElementById("ButtonWebsiteSettings");
                var btn_301 = document.getElementById("Button301Settings");
                var btn_admin = document.getElementById("ButtonAdminSettings");
                var div_website = document.getElementById("TabWebsiteSettings");
                var div_301 = document.getElementById("Tab301Settings");
                var div_admin = document.getElementById("TabAdminSettings");
                btn_website.addEventListener("click", function() {
                    div_website.style.cssText = 'display:block';
                    div_301.style.cssText = 'display:none';
                    div_admin.style.cssText = 'display:none';
                    btn_website.style.cssText = 'font-weight:bold;';
                    btn_301.style.cssText = '';
                    btn_admin.style.cssText = '';
                }); 
                btn_301.addEventListener("click", function() {
                    div_website.style.cssText = 'display:none';
                    div_301.style.cssText = 'display:block';
                    div_admin.style.cssText = 'display:none';
                    btn_website.style.cssText = '';
                    btn_301.style.cssText = 'font-weight:bold;';
                    btn_admin.style.cssText = '';
                });
                btn_admin.addEventListener("click", function() {
                    div_website.style.cssText = 'display:none';
                    div_301.style.cssText = 'display:none';
                    div_admin.style.cssText = 'display:block';
                    btn_website.style.cssText = '';
                    btn_301.style.cssText = '';
                    btn_admin.style.cssText = 'font-weight:bold;';
                });
            </script>
        <?php
    }
    function MakeLoginDiv(){
        ob_start();
        ?> 
    
        <?php if(!$this->IsTaskManager){?><div id='LoginPanel' class='top_panel' style='display:none;'>
        <?php }else{ ?><div id="task_manager_login" style="display:none;"><?php } ?>
            
            <?php if ($this->IsLoggedIn()) { ?>
                <?php if(!$this->IsTaskManager){ ?>
                    <a href='?page=<?php echo $this->PagePath;?>&operation=settings'>网站设置</a>
                    查看为
                    <a href='?page=<?php echo $this->PagePath;?>&set_translation=en'>English</a>
                    <a href='?page=<?php echo $this->PagePath;?>&set_translation=zh'>中文</a>
                <?php } ?>
            <?php } ?>
            
        
            <div class='login_half'>
        
                <?php
                if(!$this->IsLoggedIn()){
                    ?>
                    <?php if(!$this->IsTaskManager){ ?>
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
                    <?php } ?>
                    <?php 
                    if(!isset($_GET['static_generator'])){
                    ?>
                    <div id="login_again_dialog" style="display:none;">
                        <?php if(!$this->IsTaskManager){ ?>
                            <div class='inline_block_height_spacer'></div>
                            <hr />
                        <?php }else{ ?>
                            <div class="inline_height_spacer"></div>
                        <?php } ?>
                        <form method = "post" action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath;?>" style='margin-bottom:10px;'>
                            <div class = "inline_components">用户名:</div>
                            <input class='string_input' type="text" id="username" name="username" style='margin-right:0px;'
                            value="<?php if(!empty($user_username)) {echo $user_username;} ?>" />
                            <br />
                            <div class='inline_components'>密码:</div>
                            <input class='string_input' type="password" id="password" name="password" style='margin-right:0px;margin-bottom:15px;'/>
                            <br />
                            <input class='btn form_btn' style="float:right" type="submit" value="登录" name="button_login"/>
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
                    echo '<p class = "inline_components">'.'不是您本人？'.'</p>';
                    ?>
                    <input class='btn form_btn' type="button" name="logout" value="登出" onclick="location.href='<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath;?>&logout=True'" />
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
                <div id="login_again_button" class='btn' style='display:none' onClick="la_toggle_login_again();">登录</div>
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
                <a href="?page=<?php echo $this->PagePath?>&operation=list">管理</a> 
                <a href="?page=<?php echo $this->PagePath?>&operation=new">写文</a>
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
                </div>                
                <span class="hidden_on_desktop_inline"><div id="login_again_button" class='btn' style='display:none' onClick="la_toggle_login_task_desktop();"><?php echo $this->IsLoggedIn()?$this->UserDisplayName:"登录"?></div></span>
                <span class="hidden_on_desktop_inline"><div class='btn' onClick="la_toggle_login_task_mobile()">查看</div></span>
                <span class="hidden_on_mobile"><div class='btn' onClick="la_toggle_login_task_desktop()"><?php echo $this->IsLoggedIn()?$this->UserDisplayName:"登录"?></div></span>
            </div>
            <span class="hidden_on_desktop_inline">
                <div id="task_view_buttons" style="display:block;text-align:right;display:none;">
                    <div class="inline_height_spacer"></div>
                    <a>正常</a>
                    <a>总表</a>
                    <a>日历</a>
                </div>
            </span>
        <?php }
        ?>
        <script>
        function la_toggle_login_again(){
            dialog = document.getElementById("login_again_dialog");
            dialog.style.display = dialog.style.display=="none"?"block":"none";
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
                    <?php if($this->Trackable){ ?><td><a href="?page=<?php echo $this->TrackerFile ?>" style='margin:0px;'>跟踪</a><?php } ?>
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
    function MakePassageEditButtons(){
        $this->GetFileNameDateFormat($this->PagePath,$y,$m,$d,$is_draft);
        ?>
        <div class='hidden_on_print' style='float:right;z-index:1;text-align:right;'>
            <a href="?page=<?php echo $this->PagePath ?>&operation=additional">附加</a>
            <a href="?page=<?php echo $this->PagePath;?>&operation=edit"><b>编辑</b></a>
            <div class='block_height_spacer'></div>
            <?php if ($is_draft){ ?>
                <a href="?page=<?php echo $this->PagePath ?>&set_draft=0">设为公开</a>
            <?php }else{ ?> 
                <a href="?page=<?php echo $this->PagePath ?>&set_draft=1">设为草稿</a>
            <?php } ?>
        </div>
        <?php
    }
    function MakeEditorHeader(){
        ?>
        <div class='the_body'>
        <div id = "EditorHeader" class="top_panel">
            <a id='EditorToggleMore' class='btn'>更多</a>
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
                <a id='EditorCancel' class='btn' style='display:none;' href='?page=<?php echo $this->PagePath?>'>放弃修改</a>
            </div>
            
            <div class='hidden_on_desktop' >
                <div id='EditorSpacer2' class='inline_height_spacer' style='display:none;'></div>
                <a id='EditorCancelMobile' class='btn' style='display:none;' href='?page=<?php echo $this->PagePath?>'>放弃</a>
            </div>            
            
            <div class='inline_height_spacer hidden_on_desktop'></div>
            
            <div style='text-align:right; float:right; right:0px;'>
                <form method = "post" style='display:inline;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath;?>" id='form_passage'>
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
                        <input class='string_input title_string' type="text" id="EditorFileName" name="editor_file_name" value='<?php echo $this->GetUniqueName(isset($_GET['title'])?$_GET['title']:'Untitled');?>'/>
                        .md
                        <?php
                    }
                    ?>
                    
                    &nbsp;
                    <input class='btn form_btn' type="submit" value="完成" name="button_new_passage" form='form_passage' onClick='destroy_unload_dialog()' />
                </form>
            </div>
            
        </div>
        </div>
        <?php
    }
    function MakeEditorBody($text){
        ?>
        <div>
            <div id="editor_fullscreen_container" class="mobile_force_fullscreen modal_on_mobile white_bkg">
                
                <textarea class='string_input big_string big_string_height' form='form_passage' id='data_passage_content' name='data_passage_content'><?php echo $text;?></textarea>
                <div class="hidden_on_desktop"><a class="white_bkg modal_on_mobile" style="position:fixed; right:10px; top:10px; text-align:center;" onClick="editor_toggle_fullscreen_mobile()">切换全屏</a></div>
            </div>
            <div>
                <span id='data_passage_character_count'>字数</span>
            </div>
            <script>
                function editor_toggle_fullscreen_mobile(){
                    c = document.getElementById("editor_fullscreen_container");
                    e = document.getElementById("data_passage_content");
                    b = document.getElementById("editor_fullscreen_button");
                    shown = c.className != "";
                    c.className = shown?"":"mobile_force_fullscreen modal_on_mobile white_bkg";
                    e.style.height = "";
                    e.className = shown?"editor_shrink string_input big_string":"string_input big_string big_string_height";
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
                
                count.innerHTML=text_area.value.length+" 个字符";
                
                text_area.addEventListener("input",function(){
                    count.innerHTML=this.value.length+" 个字符";
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
            </script>
        </div>
        <?php
    }
    function MakeFolderHeader(){
        $additional_mode = (isset($_GET['action']) && $_GET['action']=='view');
        $move_mode = isset($_GET['moving'])||$additional_mode;
        $moving = isset($_GET['moving'])?$_GET['moving']:'';
        
        $path = $this->InterlinkPath();
        $upper='.';
        if($path!='.')$upper = $this->GetInterlinkPath('..');
        $permission = $this->PermissionForSingleFolder($path);
        $display_as = $this->FolderDisplayAs($path);
        $novel_mode = $this->FolderNovelMode($path);
        $show_list  = $this->FolderShowListButton($path);
        ?>
        <div class='top_panel'>
        
            <a href="?page=<?php echo $upper.($additional_mode?'&operation='.$_GET["operation"].'&action=view&for='.$_GET['for']:'&operation=list'.($move_mode?'&moving='.$moving:''));?>" class='btn'><b>上级</b></a>
            
            <div style="float:right;text-align:right;margin-left:5px;">
                <?php if(!$move_mode){ ?>
                    <div class='btn' id='folder_permission'>选项</div>
                    &nbsp;
                    <a class='btn' id='folder_upload'>上传</a> 
                    <a class='btn' id='folder_new_folder'>新文件夹</a>
                    <div id='new_folder_dialog' style='display:none'>
                        <div class='inline_height_spacer'></div>
                        <form method = "post" style='display:inline;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=list';?>" id="form_new_folder">
                            <div>新文件夹名</div>
                            <div class='inline_block_height_spacer'></div>
                            <input class="string_input title_string" type="text" id="NewFolderName" name="new_folder_name" value="NewFolder" form="form_new_folder">
                            <input class="btn form_btn" type="submit" value="确定" name="button_new_folder" form="form_new_folder" id='folder_new_folder_confirm'>
                        </form>
                    </div>
                    <div id='upload_dialog' style='display:none'>
                        <div class='inline_height_spacer'></div>
                        <form method = "post" enctype="multipart/form-data" style='display:inline;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=list';?>" id="form_upload">
                            <div>选择要上传的文件</div>
                            <div class='inline_block_height_spacer'></div>
                            <input class="string_input title_string" type="file" id="NewFileName" name="upload_file_name" form="form_upload">
                            <input class="btn form_btn" type="submit" value="确定" name="button_upload" form="form_upload" id='upload_confirm'>
                        </form>
                    </div>
                    <div id='permission_dialog' style='display:none'>
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
                        });
                        static_gen_btn.addEventListener("click", function() {
                            var disp = static_gen_dialog.style.display;
                            static_gen_dialog.style.cssText = disp=='none'?'display:block':'display:none';
                        });
                    </script>   
                <?php }else if(!$additional_mode){ ?>
                    <a class='btn' href='?page=<?php echo $moving ?>&operation=list'>取消</a>
                    <a class='btn' href='?page=<?php echo $path ?>&moving=<?php echo $moving ?>&to=<?php echo $path ?>'>到这里</a>
                <?php }else{ ?>
                    <a class='btn' href='?page=<?php echo $_GET["for"] ?><?php echo $_GET['operation']!='task'?'&operation='.$_GET['operation']:""?>'>取消</a>
                    <a class='btn' href='?page=<?php echo $path ?>&operation=<?php echo $_GET['operation']?>&action=add&for=<?php echo $_GET["for"] ?>&target=<?php echo $path ?>'>选这个</a>
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
                    <a class='btn' href='?page=<?php echo $this->PagePath?>&operation=set_additional_layout&layout=Gallery&for=<?php echo $this->PagePath?>'>设为画廊布局</a>
                <?php }else if($layout && $layout=='Gallery'){ ?>
                    <a class='btn' href='?page=<?php echo $this->PagePath?>&operation=set_additional_layout&layout=Normal&for=<?php echo $this->PagePath?>'>设为普通布局</a>
                <?php } ?>
                <div class='btn' id='additional_display_button'>附加内容</div>
                <div id='additional_display_dialog' style='display:none'>
                    <div class='inline_height_spacer'></div>
                    在正文下方附加显示选择的内容：
                    <div class='inline_height_spacer'></div>
                    <?php if($additional_disp!=Null) foreach ($additional_disp as $item){?>
                        <div>
                            来自 <?php echo $item['path']?> 的新帖子&nbsp;
                            <a>顶</a>
                            &nbsp;
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
    function GetAdditionalContent($page,&$prev,&$next,&$max){
        $list = $this->FileNameList;
        $ret=Null;
        if($page<0) $page=0;
        $prev=$page-1;
        if(!$page) $prev=Null;
        $i=0;
        $skip=$page*10;
        $to = $skip+10;
        while($i<$skip){
            $i++;
        }
        while(isset($list[$i])){
            $ret[]=$list[$i];
            $i++;
            if($i>=$to) break;
        }
        if(isset($list[$i])) $next=$page+1;
        else $next=Null;
        $max = ceil(count($list)/10);
        return $ret;
    }
    function GetAdditionalContentBackground($for){
        $ad = $this->GetAdditionalDisplayData($for);
        if(!isset($ad[0])) return null;
        
        $list=Null;
        
        foreach($ad as $a){
            $path = $a['path'];
            $current_dir = opendir($path);
            while(($file = readdir($current_dir)) !== false) {
                $sub_dir = $path . '/' . $file;
                if($file == '.' || $file == '..' || $file=='index.md') {
                    continue;
                } else if(!is_dir($sub_dir)){
                    $ext=pathinfo($file,PATHINFO_EXTENSION);
                    if($ext=='jpg' || $ext=='jpeg' || $ext=='png' || $ext=='svg' || $ext=='webp' || $ext=='gif'){
                        $list[] = $this->GetRelativePath($this->InterlinkPath(),$sub_dir);
                    }
                }
            }
        }
        
        if(!isset($list[0])) return Null;
        
        sort($list);
        $list = array_reverse($list);
        
        return $list[0];
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
        <div class='main_content'>
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
        <div class='main_content'>
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
                <div style='width:calc(100% - 160px);display:inline-block;'>
                <form method = "post" style='display:none;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&quote_quick='.$folder;?>" id='form_passage'></form>
                <textarea type='text' class='quick_post_string' form='form_passage' id='data_small_quote_content' name='data_small_quote_content'
                          onfocus="if (value =='小声哔哔…'){value =''}"onblur="if (value ==''){value='小声哔哔…';la_auto_grow(this);}" oninput="la_auto_grow(this)">小声哔哔…</textarea>
                <div class='block_height_spacer'></div>
                </div>
                <div style='float:right;'>
                    <input class='btn' type="submit" value="发出去给大家看看" name="button_new_quote" form='form_passage' />
                </div>
                <script> la_auto_grow(document.getElementById("data_small_quote_content"));</script>
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
        return $to->getTimeStamp() - $from->getTimeStamp();   
    }
    function TaskTimeDifferences($time_from,$time_to){
        $from = new DateTime($time_from['Y'].'-'.$time_from['M'].'-'.$time_from['D'].' '.$time_from['h'].':'.$time_from['m'].':'.$time_from['s']);
        $to = new DateTime($time_to['Y'].'-'.$time_to['M'].'-'.$time_to['D'].' '.$time_to['h'].':'.$time_to['m'].':'.$time_to['s']);
        return $to->getTimeStamp() - $from->getTimeStamp();
    }
    function ReadTaskItems($folder, $file_list, $done_day_lim, $today_y, $today_m, $today_d, &$unfinished_items, &$finished_items, &$active_items){
        $group_name=Null;
        $this->ReadTaskFolderDescription($folder, NULL ,$group_name);
        
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

                    if($ma2[3] == 0 && $ma2[5] == 0 && $this->DayDifferences($today_y, $today_m, $today_d, $ma[1], $ma[2], 31) > $done_day_lim) continue;
                    
                    if(preg_match_all("/\*\*([TDCA])([0-9]{5})\*\*[\s]*\[(.*)\][\s]*\[(.*)\][\s]*(.*)/m",$ma2[6],$ma3,PREG_SET_ORDER)){
                        
                        if(isset($ma3)) foreach($ma3 as $m){
                            $item = Null;
                            if(preg_match_all("/([0-9]{4})-([0-9]{2})-([0-9]{2})[\s]*([0-9]{2}):([0-9]{2}):([0-9]{2})/U",$m[3],$ma_time,PREG_SET_ORDER)){
                                
                                if(($m[1]=='D'||$m[1]=='C') && $this->DayDifferences($today_y, $today_m, $today_d, $ma_time[1][1], $ma_time[1][2], $ma_time[1][3])>$done_day_lim) continue;
                                
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
                            
                            if($m[1]=='D'||$m[1]=='C') $finished_items[] = $item;
                            else if($m[1]=='A') $active_items[] = $item;
                            else $unfinished_items[] = $item;
                        }
                    }
                }
            }
        }
    }
    function MakeTaskListItem($i, $it, $show_group_name){
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
            <div id = 'task_item_wrapper_<?php echo $i; ?>'>
                
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
                            <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=C">丢弃</a>
                        <?php }else{ ?>
                            <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=D">完成</a>
                        <?php } ?>
                        <a id="task_delete_button_<?php echo $i; ?>">删除</a>
                        <div id="task_save_buttons_<?php echo $i; ?>" style="float:right;">
                            <a onclick="la_showTaskEditor('<?php echo $it['folder']; ?>','<?php echo $it['id']; ?>','<?php echo $i; ?>');">修改</a>
                            <?php if ($it['status']=='T'){ ?>
                                <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=A">
                                    <b>&nbsp;进行&nbsp;</b>
                                </a>
                                <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=D">
                                    &nbsp;完成&nbsp;
                                </a>
                            <?php }else{ ?>
                                <?php if($it['status']=='A'){?>
                                    <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=T">
                                        &nbsp;暂缓&nbsp;
                                    </a>
                                    <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=D">
                                        <b>&nbsp;完成&nbsp;</b>
                                    </a>
                                <?php }else{ ?>
                                    <a href="?page=<?php echo $this->PagePath; ?>&operation=set_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>&state=T">
                                        &nbsp;放回队列&nbsp;
                                    </a>
                                <?php } ?>
                            <?php } ?>
                        </div>
                        <div id="task_delete_prompt_<?php echo $i; ?>" style="display:none;float:right;">
                            删除#<?php echo $it['id']; ?>条目
                            <a href="?page=<?php echo $this->PagePath; ?>&operation=delete_task&target=<?php echo $it['folder']?>&id=<?php echo $it['id']; ?>">确认</a>
                        </div>
                    <?php }?>
                </div>
            </div>
        </li>
        <?php
    }
    function MakeTaskGroupAdditional($folder, $done_limit, $override_unfinished_items, $override_finished_items, $override_active_items){
        $override = (isset($override_unfinished_items)||isset($override_finished_items)||isset($override_active_items));
        if(!$override){
            $task_files = $this->FileNameList;
            $this->ReadTaskItems($folder, $task_files, $done_limit, date('Y'), date('m'), date('d'), $unfinished_items, $finished_items, $active_items);
            $this->ReadTaskFolderDescription($folder, NULL,$folder_title);
        }else{
            $unfinished_items = $override_unfinished_items;
            $finished_items = $override_finished_items;
            $active_items = $override_active_items;
        }
        if($this->TaskManagerSelf){
            $folder_title = $this->TaskManagerTitle;
            $folder = $this->InterlinkPath();
        }
        ?>
        <div class='main_content' style='overflow:unset;'>
            <?php if(!$override){ ?>
            <div>
                <b>跟踪：<?php echo $folder_title;?></b>
            </div>
            <?php } ?>
            <ul class="task_ul"><?php
            $show_group_name = $override && (!$this->TaskManagerSelf);
            if(isset($active_items)) foreach($active_items as $it){
                $this->MakeTaskListItem($this->GLOBAL_TASK_I,$it,$show_group_name);
                $this->GLOBAL_TASK_I++;
            }?>
            </ul>
            <ul class="task_ul"><?php
            if(isset($unfinished_items)) foreach($unfinished_items as $it){
                $this->MakeTaskListItem($this->GLOBAL_TASK_I,$it,$show_group_name);
                $this->GLOBAL_TASK_I++;
            }?>
            </ul>
            <ul class="task_ul">
            <?php
            if(isset($finished_items)) foreach($finished_items as $it){
                $this->MakeTaskListItem($this->GLOBAL_TASK_I,$it,$show_group_name);
                $this->GLOBAL_TASK_I++;
            }?>
            </ul>
            <?php if($this->IsLoggedIn() && !$override){ ?>
                <div class='additional_content' style="position:sticky; bottom:15px; margin-bottom:0px;">
                    <a class="no_border" style="display:block;text-align:center;"onClick="la_showTaskEditor('<?php echo $folder;?>',-1,-1);">在 <?php echo $folder_title;?> 中新增事件 +</a>
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
                    <b><span id="task_editing_id"></span></b>&nbsp;在事件组&nbsp;<span id="task_editing_path"></span>
                </p>
                <div>
                <form method = "post" style='display:inline;' 
                action=""
                id="form_task_editor">
                    <textarea class="quick_post_string no_border" type="text" id="task_editor_content" name="task_editor_content" form="form_task_editor"
                        onfocus="if (value =='事件描述'){value ='';}"onblur="if (value ==''){value='事件描述';la_auto_grow(this);}" oninput="la_auto_grow(this);">事件描述</textarea>
                    <textarea class="quick_post_string no_border" style="font-size:12px;" type="text" id="task_editor_tags" name="task_editor_tags" form="form_task_editor"
                        onfocus="if (value =='标签'){value ='';}"onblur="if (value ==''){value='标签';la_auto_grow(this);}" oninput="la_auto_grow(this);">标签</textarea>
                </form>
                <div class="inline_block_height_spacer"></div>
                    <table style="table-style:fixed;"><tr>
                        <td style="text-align:left;"><a onClick="la_hideTaskEditor();">取消</a></td>
                        <td><input style="width:100%;"class="btn form_btn" type="submit" value="保存" name="task_editor_confirm" form="form_task_editor" id='task_editor_confirm'></td>
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
                eid.innerHTML=id>=0?id:"新增";
                epath.innerHTML=path;
                etc.innerHTML=tc?tc.innerHTML.trim():"事件描述";
                tags = tt?tt.innerHTML.trim():"";
                ett.innerHTML=tags==""?"标签":tags;
                
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
    
    function MakeAdditionalContent($folder,$position){
        if(!isset($folder)){
            $ad = $this->GetAdditionalDisplayData();
            $this->Additional = $ad;
            if(!isset($ad[0])) return;
        }else{
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

        foreach($ad as $a){
            $this->FileNameList=[];
            $path = $a['path'];
            $current_dir = opendir($path);
            while(($file = readdir($current_dir)) !== false) {
                if (isset($a['style']) && $a['style']==4) break;
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
            $this->FileNameList = array_reverse($this->FileNameList);
            
            $novel_mode = $this->FolderNovelMode($a['path']);
            
            if(isset($folder)){
                $prev_page=0;
                $next_page=0;
                $max_page=0;
                $this->FileNameList = $this->GetAdditionalContent($position,$prev_page,$next_page,$max_page);
                
                ?>
                <div class='top_panel block'>
                    <a href='?page=<?php echo $this->PagePath?>'>不看了</a>
                    <div style='text-align:right;float:right;right:0px;'>
                        <?php if($prev_page!==Null){?><a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder.'&position='.$prev_page?>'><b>上一页</b></a><?php } ?>
                        &nbsp;
                        <?php echo ($position+1).'/'.$max_page ?>
                        &nbsp;
                        <?php if($next_page!==Null){?><a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder.'&position='.$next_page?>'><b>下一页</b></a><?php } ?>
                    </div>
                </div>
                <?php
            }
            
            if(isset($_GET['operation']) && $_GET['operation'] == 'additional'){
            ?>
                <div style='text-align:right;'>
                    <div class = 'additional_options'>
                        附加显示 <?php echo $path?>&nbsp;
                        <div class='btn' id='additional_options_btn_<?php echo $path?>'>选项</div>
                        <div style='display:none' id='additional_options_dialog_<?php echo $path?>'>
                            <div class='inline_height_spacer'></div>
                            显示为：
                            <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=0"?>'><?php echo $a['style']==0?"<b>项</b>":"项"?></a>
                            <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=2"?>'><?php echo $a['style']==2?"<b>图</b>":"图"?></a>
                            <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=1"?>'><?php echo $a['style']==1?"<b>块</b>":"块"?></a>
                            <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=3"?>'><?php echo $a['style']==3?"<b>写</b>":"写"?></a>
                            <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=4"?>'><?php echo $a['style']==4?"<b>说</b>":"说"?></a>
                            <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=5"?>'><?php echo $a['style']==5?"<b>做</b>":"做"?></a>
                            <div class='inline_height_spacer'></div>
                            <?php if($a['style']==0 || $a['style']==1 || $a['style']==2 || $a['style']==3){ ?>
                                最近篇目数量：
                                <form method = "post" style='display:inline;' 
                                action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=set_additional_count&for='.$this->PagePath.'&target='.$path?>"
                                id="form_additional_count<?php echo $path?>">
                                    <input class="string_input no_horizon_margin title_string" style='width:4em;' type="text" value="<?php echo $a['count'] ?>" id="display_count_<?php echo $path?>" name="display_count" form="form_additional_count<?php echo $path?>">
                                    <input class="btn form_btn" type="submit" value="设置" name="button_additional_count_confirm" form="form_additional_count<?php echo $path?>" id='additional_count_confirm_<?php echo $path?>'>
                                </form>
                                <div class='inline_height_spacer'></div>
                            <?php }else if($a['style']==5){ 
                                $cc = $a['count'];?>
                                显示
                                <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=1"?>'><?php echo $cc==1?'<b>1</b>':'1'?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=2"?>'><?php echo $cc==2?'<b>2</b>':'2'?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=3"?>'><?php echo $cc==3?'<b>3</b>':'3'?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=7"?>'><?php echo $cc==7?'<b>7</b>':'7'?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=14"?>'><?php echo $cc==14?'<b>14</b>':'14'?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_item_count&for=".$this->PagePath."&target=".$path."&count=30"?>'><?php echo $cc==30?'<b>30</b>':'30'?></a>
                                天内完成的
                                <div class='inline_height_spacer'></div>
                            <?php } ?>
                            区域标题：
                            <form method = "post" style='display:inline;' 
                            action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=set_additional_title&for='.$this->PagePath.'&target='.$path?>"
                            id="form_additional_title<?php echo $path?>">
                                <input class="string_input no_horizon_margin title_string" type="text" value="<?php echo (isset($a['title'])?$a['title']:'') ?>" id="display_title_<?php echo $path?>" name="display_title" form="form_additional_title<?php echo $path?>">
                                <input class="btn form_btn" type="submit" value="设置" name="button_additional_title_confirm" form="form_additional_title<?php echo $path?>" id='additional_title_confirm_<?php echo $path?>'>
                            </form>
                            <?php if($a['style']==1 || $a['style']==2){ ?>
                                <div class='inline_height_spacer'></div>
                                <?php $cc = $a['column']?$a['column']:4?>
                                方块列数量：
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=1"?>'><?php echo $cc==1?'<b>1</b>':'1'?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=2"?>'><?php echo $cc==2?'<b>2</b>':'2'?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=3"?>'><?php echo $cc==3?'<b>3</b>':'3'?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=4"?>'><?php echo $cc==4?'<b>4</b>':'4'?></a>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_column_count&for=".$this->PagePath."&target=".$path."&column_count=5"?>'><?php echo $cc==5?'<b>5</b>':'5'?></a>
                            <?php } ?>
                            
                            <?php if($a['style']==3){?>
                            
                                <div class='inline_height_spacer'></div>
                                时间线列表按钮：
                                <form method = "post" style='display:inline;' 
                                action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=set_additional_more_title&for='.$this->PagePath.'&target='.$path?>"
                                id="form_additional_more_title<?php echo $path?>">
                                    <input class="string_input no_horizon_margin title_string" type="text" value="<?php echo (isset($a['more'])?$a['more']:'') ?>" id="display_more_title_<?php echo $path?>" name="display_more_title" form="form_additional_more_title<?php echo $path?>">
                                    <input class="btn form_btn" type="submit" value="设置" name="button_additional_more_title_confirm" form="form_additional_more_title<?php echo $path?>" id='button_additional_more_title_confirm<?php echo $path?>'>
                                </form>
                            <?php if(isset($a['quick_post']) && $a['quick_post']==1){ ?>
                                <div class='inline_height_spacer'></div>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_quick_post&for=".$this->PagePath."&target=".$path."&quick=0"?>'>关闭快速发帖</a>
                            <?php }else if(!isset($a['quick_post']) || $a['quick_post']==0){?>
                                <div class='inline_height_spacer'></div>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_quick_post&for=".$this->PagePath."&target=".$path."&quick=1"?>'>启用快速发帖</a>
                            <?php }?>
                            <?php if(isset($a['complete']) && $a['complete']!=0){?>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_complete&for=".$this->PagePath."&target=".$path."&complete=0"?>'>改显示为摘要</a>
                            <?php }else if(!isset($a['complete']) || $a['complete']==0){?>
                                <a href='?page=<?php echo $this->PagePath."&operation=set_additional_complete&for=".$this->PagePath."&target=".$path."&complete=1"?>'>改显示为全文</a>
                            <?php }?>
                            <?php }?>
                            
                            <?php if($a['style']==4 || $a['style']==5){?>
                                <div class='inline_height_spacer'></div>
                                时间线列表按钮：
                                <form method = "post" style='display:inline;' 
                                action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=set_additional_more_title&for='.$this->PagePath.'&target='.$path?>"
                                id="form_additional_more_title<?php echo $path?>">
                                    <input class="string_input no_horizon_margin title_string" type="text" value="<?php echo (isset($a['more'])?$a['more']:'') ?>" id="display_more_title_<?php echo $path?>" name="display_more_title" form="form_additional_more_title<?php echo $path?>">
                                    <input class="btn form_btn" type="submit" value="设置" name="button_additional_more_title_confirm" form="form_additional_more_title<?php echo $path?>" id='button_additional_more_title_confirm<?php echo $path?>'>
                                </form>
                                <?php if(isset($a['quick_post']) && $a['quick_post']==1){ ?>
                                    <div class='inline_height_spacer'></div>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_quick_post&for=".$this->PagePath."&target=".$path."&quick=0"?>'>关闭快速发帖</a>
                                <?php }else if(!isset($a['quick_post']) || $a['quick_post']==0){?>
                                    <div class='inline_height_spacer'></div>
                                    <a href='?page=<?php echo $this->PagePath."&operation=set_additional_quick_post&for=".$this->PagePath."&target=".$path."&quick=1"?>'>启用快速发帖</a>
                                <?php }?>
                            <?php }?>
                        </div>
                    </div>
                    
                    <script>
                        var btn = document.getElementById("additional_options_btn_<?php echo $path?>");
                        btn.addEventListener("click", function() {
                            var options_dialog = document.getElementById("additional_options_dialog_<?php echo $path?>");
                            var disp = options_dialog.style.display;
                            options_dialog.style.cssText = disp=='none'?'display:block':'display:none';
                        });
                    </script>
                </div>
            <?php
            }
            
            if(isset($a['title']) && $a['title']!='' && $a['style']!=4){
                ?>
                <div style='text-align:center;'>
                    <div class='narrow_content inline_block'>
                        <?php echo $a['title'] ?>
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
                    <div class='additional_content'>
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
                    <div class='tile_content tile_item'>
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
                $cc = $a['column']?$a['column']:4;
                ?><div class='tile_container'><?php
                $i=0;$j=0;
                if (isset($this->FileNameList[0])) foreach ($this->FileNameList as $f){
                    ?>
                    <div class='tile_content tile_item <?php echo$cc==1?"":"gallery_multi_height" ?>' style='max-height:unset;'>
                        <?php if($cc==1){ ?>
                            <img src='<?php echo $path.'/'.$f?>' style='max-width:100%;'></img>
                        <?php }else{ ?>
                            <div class='gallery_multi_content'>
                            <img src='<?php echo $path.'/'.$f?>' class='gallery_image'></img>
                            </div>
                        <?php } ?>
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
            }else if (isset($a['style']) && $a['style']==3){
                if($this->IsLoggedIn() && isset($a['quick_post']) && $a['quick_post']!=0){
                    ?>
                    <div>
                        <div class='additional_content additional_content_left hidden_on_mobile'>
                            <div class='plain_block' style='text-align:center'>
                            <span style='font-size:24px;'><b><?php echo date("d")?></b></span><br /><?php echo date("Y")?>/<?php echo date("m") ?>
                            </div>
                        </div>
                        <div class='additional_content'>
                            <div class='hidden_on_desktop' style='clear:both;text-align:center'>
                                <span style='font-size:24px;'><b><?php echo date("d")?></b></span><br /><?php echo date("Y")?>/<?php echo date("m") ?>
                            </div>
                            <form method = "post" style='display:none;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&quick='.$path;?>" id='form_passage'></form>
                            <input style='display:none;' type="text" id="EditorFileName" name="editor_file_name" value='<?php echo $this->GetUniqueName(date("Ymd"));?>'/ form='form_passage'>
                            <textarea type='text' class='quick_post_string' form='form_passage' id='data_passage_content' name='data_passage_content'
                                      onfocus="if (value =='我有一个想法…'){value =''}"onblur="if (value ==''){value='我有一个想法…';la_auto_grow(this);}" oninput="la_auto_grow(this)">我有一个想法…</textarea>
                            <div class='block_height_spacer'></div>
                            <div style='text-align:right;'>
                                <input class='btn' type="submit" value="和世界分享您刚编的故事" name="button_new_passage" form='form_passage' />
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
                    $background = $this->GetAdditionalContentBackground($path.'/'.$f);
                    $last_interlink = $this->InterlinkPath();
                    $this->SetInterlinkPath($path.'/'.$f);
                    ?>
                    <div>
                    <div class='additional_content additional_content_left hidden_on_mobile'>
                        <div class='plain_block' style='text-align:center'>
                        <span style='font-size:24px;'><b><?php echo $is_draft?'草稿':$d?></b></span><br /><?php echo $y?><?php echo $m?'/'.$m:'' ?>
                        </div>
                    </div>
                    <div class='additional_content no_overflow_mobile'>
                        <div class='hidden_on_desktop' style='clear:both;text-align:right;position:sticky;top:80px;'>
                            <div class='plain_block small_shadow' style='text-align:center;display:inline-block;background-color:#FFF;'>
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
                        <div class='btn block' style="text-align:unset;<?php if(!$folder && $background) echo "background-image:url('".$background."');background-repeat:no-repeat;background-size:cover;background-position:center;" ?>"
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
                    <div style='text-align:center;'>
                        <div class='narrow_content inline_block'>
                            <a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $a['path']?>'><?php echo $a['more'] ?></a>
                        </div>
                    </div>
                    <?php
                }
            }else if (isset($a['style']) && $a['style']==4){
                $this->MakeSmallQuoteAdditional($a['path'],$a['title'],$a['more'],$a['quick_post']);
            }else if (isset($a['style']) && $a['style']==5){
                $this->FileNameList = array_reverse($this->FileNameList);//old first
                $this->MakeTaskGroupAdditional($path, $a['count'],NULL,NULL,NULL);
            }
            if(isset($folder)){
                ?>
                <div style='text-align:center;position:sticky;bottom:0px;'>
                    <div class='top_panel inline_block'>
                        <div style='text-align:right;float:right;right:0px;'>
                            <?php if($prev_page!==Null){?><a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder.'&position='.$prev_page?>'><b>上一页</b></a><?php } ?>
                            &nbsp;
                            <?php echo ($position+1).'/'.$max_page ?>
                            &nbsp;
                            <?php if($next_page!==Null){?><a href='?page=<?php echo $this->PagePath?>&operation=timeline&folder=<?php echo $folder.'&position='.$next_page?>'><b>下一页</b></a><?php } ?>
                        </div>
                    </div>
                </div>
                <?php
            }
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
                        <a href="?page=<?php echo $path.'/'.$f.($viewing?'&for='.$_GET['for'].'&operation='.$_GET["operation"].'&action=view':'&operation=list'.($move_mode?'&moving='.$moving:''));?>" class='btn'><b>进入</b></a>
                     </div>
                     <div class = 'narrow_content' style='float:right;margin-left:15px'>
                     <?php if (!$move_mode){ ?>
                        <div style='display:none;' id='folder_option_<?php echo $f;?>'>
                            <a id='folder_delete_btn_<?php echo $f;?>'>删除</a>
                            &nbsp;
                            <a id='folder_move_btn_<?php echo $f;?>' href='?page=<?php echo $path ?>&operation=list&moving=<?php echo $path.'/'.$f ?>'>移动</a>
                            <a id='folder_rename_btn_<?php echo $f;?>'>改名</a>
                            &nbsp;
                        </div>
                        <a class='btn' id='folder_option_btn_<?php echo $f;?>'>调整</a>
                     <?php }else if($viewing){ ?>
                        <a class='btn' id='folder_option_btn_<?php echo $f;?>' href='?page=<?php echo $path ?>&operation=<?php echo $_GET['operation']?>&action=add&for=<?php echo $_GET['for'] ?>&target=<?php echo $path.'/'.$f ?>'>选这个</a>
                     <?php }else{ ?>
                        <a class='btn' id='folder_option_btn_<?php echo $f;?>' href='?page=<?php echo $path ?>&moving=<?php echo $moving ?>&to=<?php echo $path.'/'.$f ?>'>到这里</a>
                     <?php } ?>
                     </div>
                     <div class = 'narrow_content' style='overflow:auto;'>
                        <b style='background-color:#FFF;'><?php echo $f?></b>
                     </div>
                </div>
                <div class='the_body' style='clear:both;text-align:right'>
                    <div class = 'narrow_content' style='display:none' id='folder_delete_panel_<?php echo $f;?>'>
                    确认 <a class='btn' href='?page=<?php echo $this->InterlinkPath();?>&operation=delete_folder&target=<?php echo $f?>'>删除 <?php echo $f?></a>
                    </div>
                    <div class = 'narrow_content' style='display:none' id='folder_rename_panel_<?php echo $f;?>'>
                    <?php echo $f;?> 的新名字
                    <form method = "post" style='display:inline;' id='folder_rename_form_<?php echo $f?>' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=list&target='.$f;?>">
                        <input class="string_input title_string" type="text" id="RenameFolderName" name="rename_folder_name" form="folder_rename_form_<?php echo $f?>">
                        <input class="btn form_btn" type="submit" value="确定" name="button_rename_folder" form="folder_rename_form_<?php echo $f?>">
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
                $fp = $this->PermissionForFolderRecursive($f);
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
                <div class='inline_block_height_spacer'></div>
                <?php foreach($this->AudioList as $audio){ ?>
                    <a>放这个</a>
                    <?php echo pathinfo($audio['src'],PATHINFO_BASENAME); ?>
                    <div class='inline_height_spacer'></div>
                <?php } ?>
            </div>
            
            <div style='display:inline;'>
            
                <div style='margin-right:5px;display:inline-block'>
                    <b><a id='audio_player_btn_play' class='btn'>播放</a></b>
                    <a id='audio_player_btn_list' class='btn'>&nbsp;#&nbsp;</a>
                </div>
                
                <div id='audio_player_bar' class='plain_block' style='display: inline-block; width: calc(100% - 115px); position:relative;'>
                    
                    <div id='audio_player_progress' style='width:0%; background-color:#000; position:absolute; display:inline_block; z-index:-1; margin: -5px; height: 100%;'>
                        &nbsp;
                    </div>
                    
                    <div id='audio_player_buffer' class='halftone1' style='width:0%; position:absolute; display:inline_block; z-index:-2; margin: -5px; height: 100%;'>
                        &nbsp;
                    </div>
                    
                    <div id='audio_player_time' style='background-color:#FFF; align-items: center; display: inline-block;'>
                        已停止
                    </div>
                    
                    <div id='audio_total_time' style='float:right; margin-right:4px; background-color:#FFF; align-items: center; display: inline-block;'>
                        请稍候
                    </div>
                    
                </div>
                
            </div>

        </div>
        <script>
            <?php if(True) { ?>
            var music = document.getElementById("<?php echo 'AUDIO_'.$this->AudioList[0]['id'] ?>");
            var play = document.getElementById('audio_player_btn_play');
            var list_btn = document.getElementById('audio_player_btn_list');
            var list = document.getElementById('audio_player_playlist');
            var time = document.getElementById('audio_player_time');
            var duration = document.getElementById('audio_total_time');
            var progress = document.getElementById('audio_player_progress');
            var buffer = document.getElementById('audio_player_buffer');
            var bar = document.getElementById('audio_player_bar');
            play.addEventListener("click", function() {
                if(music.paused){
                    music.play();
                    play.innerHTML='暂停';
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
            music.ontimeupdate = function(){
                duration.innerHTML = (Math.floor(music.duration/60))+':'+la_pad((Math.round(music.duration)%60),2);
                time.innerHTML=(Math.floor(music.currentTime/60))+':'+la_pad((Math.round(music.currentTime)%60),2);
                progress.style.width=100*(music.currentTime/music.duration)+'%';
                buffer.style.width = 100*(music.buffered.end(0)/music.duration)+'%';
            }
            music.oncanplay = function(){
                duration.innerHTML = (Math.floor(music.duration/60))+':'+la_pad((Math.round(music.duration)%60),2);
            }
            music.oncanplaythrough = function(){
                duration.innerHTML = (Math.floor(music.duration/60))+':'+la_pad((Math.round(music.duration)%60),2);
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
                            <div class='btn' id='task_item_content_button'>设置分组</div>
                            <div style="float:right; display:none;" id='task_item_content_button_extra'>
                                <a class='btn' href='?page=<?php echo $this->PagePath?>&operation=edit'>编辑文字</a>
                                <a class='btn' href='?page=<?php echo $this->PagePath?>&operation=task&action=view&for=<?php echo $this->PagePath?>'>添加组</a>
                            </div>
                            <div class='inline_block_height_spacer'></div>
                            <div id='task_item_content_dialog' style='display:none'>
                                <table>
                                <?php $tic=0;   ?>
                                <?php foreach ($this->TaskManagerGroups as $item){
                                    $pc=$item['past_count'];
                                    ?>
                                    <tr>
                                        <td><a class='btn' style="display:block" onclick='task_option_toggle_<?php echo $tic ?>()'><?php echo $item['title']?> 位于 <?php echo $item['path']?></a></td>
                                        <td style="width:30px;">
                                            <a class='btn' style="display:block" href='?page=<?php echo $this->PagePath?>&operation=task&action=delete&for=<?php echo $this->PagePath?>&target=<?php echo $item['path']?>'>删</a>
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
                                    content_dialog.style.cssText = disp=='none'?'display:block':'display:none';
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
                                        <a style="display:block;" href="?page=<?php echo $folder_item['path']; ?>">进入分组</a>
                                    <?php }else{?>
                                        <?php if($this->IsLoggedIn()){ ?>
                                            <a style="display:block;" href="?page=<?php echo $this->PagePath; ?>&operation=task_new_index&for=<?php echo $folder_item['path']; ?>">创建索引</a>
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
                                    <td width="70%;" ><a style="display:block;" onClick="la_task_adder_toogle();la_showTaskEditor('<?php echo $folder_item['path']; ?>',-1,-1);">到 <?php echo $folder_item['title']?></a></td>
                                </tr></table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        <?php } ?>
        <div id="task_manager_footer" class="audio_player_box modal_dialog">
            
            <div class="block_height_spacer"></div>
            <table style="text-align:center;"><tr>
                <?php if(!$this->TaskManagerSelf){ ?>
                    <td style="width:50%;"><a style="display:block;" onClick="la_task_group_switcher_toogle()">共 <?php echo count($this->TaskManagerGroups); ?> 个事件组</a></td>
                    <?php if($this->IsLoggedIn()){ ?><td style="width:50%;"><a style="display:block;" onClick="la_task_adder_toogle()">新增事件 +</a></td><?php } ?>
                <?php }else{ ?>
                    <td style="width:25%;"><a style="display:block;" href="?page=<?php echo $this->PagePath?>&operation=task&action=view&for=<?php echo $this->PagePath?>">选组</a></td>
                    <td style="width:25%;"><a style="display:block;" href="?page=<?php echo $this->PagePath?>&operation=edit">编辑</a></td>
                    <td style="width:50%;"><a style="display:block;" onClick="la_showTaskEditor('<?php echo $this->InterlinkPath(); ?>',-1,-1);">新增事件 +</a></td>
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
        $this->GetPrevNextPassage($this->PagePath);
        
        ?>
        <div class='the_body'>
        <div style='text-align:right;'>
            <div class='footer'>
                <a class='btn' href="javascript:scrollTo(0,0);">返回顶部</a>
                <br />
                <div class = 'inline_block_height_spacer'></div>
                <p style='font-size:12px;margin:0px;'><?php echo $this->Footnote ?></p>
                <p style='font-size:12px;margin:0px;'>使用 <a href='http://www.wellobserve.com/?page=MDWiki/index.md' style='padding:1px;border:none;'>LAMDWIKI</a> 创建</p>
            </div>
        </div>
        </div>
        
        <script>
            var lg_toggle  = document.getElementById("LoginToggle");
            var lg_panel = document.getElementById("LoginPanel");

            if(lg_toggle && lg_panel) lg_toggle.addEventListener("click", function() {
                var shown = lg_panel.style.display == 'block';
                lg_panel.style.display = shown ? "none" : "block";
                //lg_toggle.innerHTML = shown? "收起":"登录";
            });
            <?php if(!$this->IsTaskManager){ ?>
            var hb = document.getElementById("HomeButton");
            var nav = document.getElementById("Navigation");
            hb.addEventListener("click", function() {
                var disp = nav.style.display;
                nav.style.cssText = disp==''?'display:block':'';
            });
            <?php } ?>
            
            var img = [];
            
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
                }
                
            }
            //for (var i=0; i<bkg_img.length;i++){
            //    if(!bkg_img[i].style.backgroundImage) continue;
            //    
            //    bkg_img[i].onclick=function(){
            //    
            //        image.src=this.style.backgroundImage.match(/url\(["']?([^"']*)["']?\)/)[1];
            //        image_alt.innerHTML=image.src;
            //        dialog.style.display="block";
            //        alert("SSSS");
            //    }  
            //}
            close.onclick=function(){
                dialog.style.display='none';
            }
            image_white.onclick=function(){
                dialog.style.display='none';
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
        <?php if($this->Trackable && pathinfo($this->PagePath,PATHINFO_BASENAME)!=pathinfo($this->TrackerFile,PATHINFO_BASENAME)){ ?><a href="?page=<?php echo $this->TrackerFile ?>" style='margin:0px;'>总览</a><?php } ?>
        <span class="hidden_on_desktop_inline" ><span id="task_master_header"> <?php echo $this->TaskManagerTitle; ?> </span></span>
        <span class="hidden_on_mobile"><span id="task_master_header_desktop"> 当前在 <?php echo $this->TaskManagerTitle; ?> </span></span>
    <?php
    }
}

?>
