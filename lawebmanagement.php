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
    
    protected $UserDsipName;
    protected $UserID;
    protected $userIsMature;
    
    protected $PagePath;
    
    protected $FolderNameList;
    protected $FileNameList;
    protected $OtherFileNameList;
    
    protected $IsEditing;
    
    protected $PrevFile;
    protected $NextFile;
    
    protected $Additional;
    protected $AdditionalLayout;
    
    protected $IsMainPassage;
    
    protected $AudioList;
    
    protected $MainFileTitle;
    protected $MainFileIsNSFW;
    protected $FileIsNSFW;
    
    
    protected $Title;
    protected $StringTitle;
    protected $Footnote;
    
    function __construct() {
        $this->PDE = new ParsedownExtra();
        $this->PDE->SetInterlinkPath('/');
    }
    
    function LimitAccess($mode){
        if($mode==0){
            echo "<div style='text-align:center;'>";
            echo "<h1>404</h1>";
            echo "页面不存在。<br />Page does not exist.";
            echo "</div>";
            exit;
        }else if($mode==1){
            echo "<div style='text-align:center;'>";
            echo "<h1>停一下</h1>";
            echo "未登录用户不允许访问。<br />Access not allowed for non-logged-in users.";
            echo "</div>";
            exit;
        }
    }
    
    function GetRelativePath($from, $to) {
        $dir = explode('/', is_file($from) ? dirname($from) : rtrim($from, '/'));
        $file = explode('/', $to);

        while ($dir && $file && ($dir[0] == $file[0])) {
            array_shift($dir);
            array_shift($file);
        }
        return str_repeat('..'.'/', count($dir)) . implode('/', $file);
    }
    
    function SetPagePath($path){
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
            $this->LimitAccess(0);
        }
        $this->PagePath = $path;
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
        while (preg_match("/(([\S\s](?<!<!--))*)(<!--)(.*)(-->)(([\S\s](?!<!--))*)(([\S\s](?<!<!--))*)(<!--)(.*)(-->)/", $Content, $Matches, PREG_OFFSET_CAPTURE)){
            $BlockName = trim($Matches[4][0]);
            
            $Returns[$i]["IsConfig"] = False;
            $Returns[$i]["Content"] = trim($Matches[1][0]);
            $i++;
            
            $Returns[$i]["IsConfig"] = True;
            $Returns[$i]["BlockName"] = $BlockName;
            
            $j = -1; $k = 0;
            foreach(explode("\n",trim($Matches[6][0])) as $Line){
                if (!isset($Line[0])) continue;
                if ($Line[0] == "-"){
                    if ($j<0) continue;
                    else{
                        $Analyze = explode("=",$Line,2);
                        if(count($Analyze)<2) continue;
                        $Returns[$i]["Items"][$j]["Items"][$k]["Argument"] = trim(substr($Analyze[0],1));
                        $Returns[$i]["Items"][$j]["Items"][$k]["Value"] = trim($Analyze[1]);
                        $k++;
                    }
                }else{
                    $j++;
                    $k=0;
                    $Analyze = explode("=", $Line,2);
                    if(count($Analyze)<2){
                        $Returns[$i]["Items"][$j]["Name"] = trim($Line);
                        $Returns[$i]["Items"][$j]["Value"] = "";
                    }else{
                        $Returns[$i]["Items"][$j]["Name"] = trim($Analyze[0]);
                        $Returns[$i]["Items"][$j]["Value"] = trim($Analyze[1]);
                    }
                }
            }
            
            $i++;
            
            $Content = preg_replace("/(([\S\s](?<!<!--))*)(<!--)(.*)(-->)(([\S\s](?!<!--))*)(([\S\s](?<!<!--))*)(<!--)(.*)(-->)/", "", $Content, $limit=1);
        }
        return $Returns;
    }
    
    function WriteMarkdownConfig($Config,$File){
        foreach($Config as $Block){
            if(!isset($Block["IsConfig"])) continue;
            if($Block["IsConfig"]==False){
                fwrite($File,$Block["Content"]);
                fwrite($File,"\n\n");
            }else{
                fwrite($File,"<!-- ".$Block["BlockName"]." -->\n\n");
                if(isset($Block["Items"])){
                   foreach($Block["Items"] as $Name){
                        fwrite($File,$Name["Name"]);
                        if(isset($Name["Value"])&&$Name["Value"]!='') fwrite($File," = ".$Name["Value"]);
                        fwrite($File,"\n");
                        if(isset($Name["Items"]))
                            foreach($Name["Items"] as $Argument){
                                fwrite($File,"- ".$Argument["Argument"]." = ".$Argument["Value"]."\n");
                            }
                    fwrite($File,"\n");
                    }
                }
                fwrite($File,"<!-- End of ".$Block["BlockName"]." -->\n\n");
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
        preg_match_all("/<audio[\s\S]*id=[\"']([\S]*)[\"'][\s\S]*<source[\s\S]*src=[\"']([\S]*)[\"']/U", $Content, $Matches, PREG_SET_ORDER);
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
            $this->ScanForTagsInContent($R);
        }else{
            $this->FileIsNSFW=False;
        }
        
        return $R;
    }
    function HTMLFromMarkdown($Content){
        return $this->PDE->text($Content);
    }
    
    function HTMLFromMarkdownFile($FileName){
        $Content = $this->ContentOfMarkdownFile($FileName);
        if($Content) return $this->HTMLFromMarkdown($Content);
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
        if(preg_match('/# [ ]*(.*)\n[\s\S]*\n## [ ]*(.*)\n/U',$content,$match,PREG_OFFSET_CAPTURE)){
            return '**'.$match[1][0].'**: '.$match[2][0];
        }else{
            if(preg_match('/# [ ]*(.*)\n/U',$content,$match,PREG_OFFSET_CAPTURE)){
                return '**'.$match[1][0].'**';
            }
            return $this->FirstRows($content,1);
        }
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

            header('Location:?page='.(isset($_GET['quick'])?$this->PagePath:$file_path));
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
    function DoApplySettings(){
        if(isset($_POST['settings_button_confirm'])){
        
            $this->UserConfig = fopen("la_config.md",'r');
            $ConfContent = fread($this->UserConfig,filesize("la_config.md"));
            $Conf = $this->ParseMarkdownConfig($ConfContent);
            $this->EditBlock($Config,'Website');
            $this->EditBlock($Config,'Users');
            
            if(isset($_POST['settings_website_title'])){
                $this->EditGeneralLineByName($Conf,'Website','Title',$_POST['settings_website_title']);
            }
            if(isset($_POST['settings_website_display_title'])){
                $this->EditGeneralLineByName($Conf,'Website','DisplayTitle',$_POST['settings_website_display_title']);
            }
            if(isset($_POST['settings_footer_notes'])){
                $this->EditGeneralLineByName($Conf,'Website','Footnote',$_POST['settings_footer_notes']);
            }
            if(isset($_POST['settings_admin_password']) && $_POST['settings_admin_password']!=''){
                $this->EditArgumentByNames($Conf,'Website','Users',$this->UserID,'Password',$_POST['settings_admin_password']);
            }
            
            fclose($this->UserConfig);
            $this->UserConfig = fopen("la_config.md",'w');
            $this->WriteMarkdownConfig($Conf,$this->UserConfig);
            fclose($this->UserConfig);
            
            header('Location:?page='.$this->PagePath.'&operation=settings');
            exit;
        }
    }
    
    function GetWebsiteSettings(){
        $this->UserConfig = fopen("la_config.md",'r');
        $ConfContent = fread($this->UserConfig,filesize("la_config.md"));
        fclose($this->UserConfig);
        $Conf = $this->ParseMarkdownConfig($ConfContent);
        $this->Title       = $this->GetLineValueByNames($Conf,"Website","Title");
        $this->StringTitle = $this->GetLineValueByNames($Conf,"Website","DisplayTitle");
        $this->Footnote    = $this->GetLineValueByNames($Conf,"Website","Footnote");
        if(!$this->Title) $this->Title='LA<b>MDWIKI</b>';
        if(!$this->StringTitle) $this->StringTitle='LAMDWIKI';
    }
    
    function MakeHTMLHead(){
        $this->GetWebsiteSettings();
        ?>
        <!doctype html>
        <head>
        <meta name="viewport" content="user-scalable=no, width=device-width" />
        <title><?php echo $this->StringTitle ?></title>
        <style>
        
            html{ text-align:center; }
            body{ width:60%; text-align:left; min-width:900px; margin: 0 auto;
                background:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAQAAAAECAYAAACp8Z5+AAAAGUlEQVQImWNgYGD4z4AGMARwSvxnYGBgAACJugP9M1YqugAAAABJRU5ErkJggg==) repeat;
                background-attachment: fixed;
                font-size:16px;
            }
            
            img{ max-width: 100%; margin: 5px auto; display: block; }
            h3 img{ float: right; margin-left: 10px; max-width:30%; clear: right;}
            h4 img{ float: left; margin-right: 10px; max-width:30%; clear: left;}
            a > img{ pointer-events: none; }
            .btn img{ pointer-events: none; }
            .gallery_left img{ float: unset; margin: 5px auto; max-width: 100%;}
            
            table{ width:100%; }
            
            blockquote{ border-top:1px solid #000; border-bottom:1px solid #000; text-align: center; }
            
            ::-moz-selection{ background:#000; color:#FFF; }
            ::selection{ background:#000; color:#FFF; }
            ::-webkit-selection{ background:#000; color:#FFF; }
            
            #Header{ position: sticky; top:0px; left:15%; display: block; z-index:100; }
            #WebsiteTitle{ border:1px solid #000; display: inline-block; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; margin-left:0px; margin-right:0px; margin-bottom:15px;
                background-color:#FFF; box-shadow: 5px 5px #000;
            }	
            #HeaderQuickButtons{ border:1px solid #000; display: inline; right:0px; position: absolute; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; margin-right:0px;
                background-color:#FFF; box-shadow: 5px 5px #000;
            }

            #LoginToggle {
                display: inline;
            }
            .login_half{ float: right; right: 10px; width: 50%; text-align: right;}
            
            .main_content           { padding:20px; padding-left:15px; padding-right:15px; border:1px solid #000; background-color:#FFF; box-shadow: 5px 5px #000; margin-bottom:15px; overflow: auto; scrollbar-color: #000 #ccc; scrollbar-width: thin; }
            .narrow_content         { padding:5px; padding-top:10px; padding-bottom:10px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 3px #000; margin-bottom:15px; max-height:350px; }
            .additional_content     { padding:5px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 3px #000; margin-bottom:15px; overflow: hidden; }
            .additional_content_left{ margin-right: 15px; float: left; text-align: center; position: sticky; top:82px; margin-bottom:0px;}
            .small_shadow           { box-shadow: 2px 2px #000; }
            .tile_content           { padding:10px; border:1px solid #000; background-color:#FFF; box-shadow: 3px 3px #000; margin-bottom:15px; max-height:350px; }
            .top_panel              { padding:10px; padding-top:15px; padding-bottom:15px; border:1px solid #000; background-color:#FFF; box-shadow: 5px 5px #000; margin-bottom:15px; overflow: hidden; }
            .full_screen_window     { top:10px; bottom:10px; left:10px; right:10px; position: fixed; z-index:1000; max-height: unset;}
            .gallery_left           { height:calc(100% - 160px); position: fixed; width:350px; }
            .gallery_right          { width:calc(100% - 365px); left: 365px; z-index:10; position: relative;}
            .gallery_main_height    { max-height: 100%; }
            .gallery_multi_height   { position: relative;}
            .gallery_multi_height::before   { content: " "; display: block; padding-top: 100%; }
            .gallery_multi_content  { position: absolute;top: 5px; left: 5px; bottom: 5px; right: 5px; display: flex; align-items: center; overflow: hidden;}
            .gallery_image          { max-width: unset; min-width: 100%; min-height: 100%; object-fit: cover; }
            .gallery_box_when_bkg   { width:30%; max-width:300px;}
            .no_padding             { padding: 0px; }
            
            .audio_player_box       { padding:10px; border:1px solid #000; background-color:#FFF; box-shadow: 5px 5px #000; bottom:15px; overflow: hidden; position: sticky; margin:15px;}
            
            .btn          { border:1px solid #000; padding: 5px; color:#000; display: inline; background-color:#FFF; font-size:16px; cursor: pointer; text-align: center; }
            .btn:hover    { border:3px double #000; padding: 3px; }
            .btn:active   { border:5px solid #000; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px; }
            .btn_nopadding          { padding: 2px; }
            .btn_nopadding:hover    { padding: 0px; }
            .btn_nopadding:active   { padding: 0px; }
            .block        { display: block; }
            .inline_block { display: inline-block; }
            .form_btn     { float: right; margin-top:-6px; margin-bottom:-6px; margin-left:5px; }
            .preview_btn  { height:250px; overflow: hidden; }
            .full_btn     { width:100%; }
            .no_float     { float: unset; }
            
            .inline_height_spacer      { display: block; height:15px; width:100%; }
            .inline_block_height_spacer{ display: block; height:10px; width:100%; }
            .block_height_spacer { display: block; height:4px; width:100%; }
            
            .halftone1  { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAGElEQVQYlWNgIBH8HxyKaQ+Icg71FGEAAMIRBftlPpkVAAAAAElFTkSuQmCC) repeat; }
            .halftone1w { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAMElEQVQYlWP4TyRg+P///38GBgbiFBKjGEUWn2LCdlJdIcw5eBUiuxmnQnSPEe1GAL6NfJLaO8bfAAAAAElFTkSuQmCC) repeat; }
            .halftone2  { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAD0lEQVQImWNgIAf8J10LADM2AQA1DEeOAAAAAElFTkSuQmCC) repeat; }
            .halftone2w { background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAFklEQVQImWP4jwUw4BVkYGAgUiUyAADQo2Cg/XS+dwAAAABJRU5ErkJggg==) repeat; }
            .halftone3  { color:#FFF; background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHUlEQVQImWNgYGD4j4YZGLAI/GfAIgAXxKYD1VwA+JoT7dVZ0wkAAAAASUVORK5CYII=) repeat; }
            .halftone3w { color:#FFF; background: url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAJElEQVQImW3IoREAAAjEsO6/dBEI/gAREwCTKk9MRnSukBNgAQ7LJ9m50jTuAAAAAElFTkSuQmCC) repeat; }
            
            .inline            { display: inline; margin:0px; }
            .inline_components { display: inline; margin: 5px; }
            .plain_block       { border: 1px solid #000; text-align: left; padding:5px; }
            .preview_block     { border-right: 1px solid #000; margin:5px; }
            
            .string_input      { border:3px solid #000; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px; margin:5px; width:150px; }
            .quick_post_string { border:3px solid #000; border-bottom: 1px solid #000; border-right: 1px solid #000; padding: 3px; margin:0px; width:100%; resize: none; overflow: hidden; height: 25px; }
            .big_string        { width: calc(100% - 10px); height: 500px; resize: vertical; }
            .title_string      { margin-top:-5px; margin-bottom:-5px; font-size:16px; text-align: right; }
            
            .no_horizon_margin { margin-left:0px; margin-right:0px; }
            
            .navigation        { border:1px solid #000; display: inline; padding:10px; padding-top:15px; padding-bottom:15px; margin:10px; right:0px; background-color:#FFF; box-shadow: 5px 5px #000; white-space: nowrap; text-align: center;}
            .navigation p      { display: inline; }
            
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
            
            .preview{ margin: 10px; }
            .preview h1, .preview h2, .preview h3, .preview h4, .preview h5, .preview h6, .preview blockqoute                                  { margin-top: 3px; margin-bottom: 3px; }
            .preview a {pointer-events: none;}
            .name_preview h1, .name_preview h2, .name_preview h3 .name_preview h4, .name_preview h5, .name_preview h6, .name_preview p         { font-size:16px; display: inline; }
            .preview_large h1, .preview_large h2, .preview_large h3, .preview_large h4, .preview_large h5, .preview_large h6, .preview_large p { display: block; }
            .preview_large h1{ font-size:24px; }
            .preview_large h2{ font-size:20px; }
            .preview_large h3{ font-size:18px; }
            .passage_detail{ float: right; text-align: right; margin-left:5px; width:20%; min-width:210px; }
            .small_shadow p{ display: inline; }
            
            .hidden_on_desktop       { display: none; }
            .hidden_on_desktop_inline{ display: none; }
            
            @media screen and (max-width: 1000px) {
            
                body{ left:10px; width:calc(100% - 20px); min-width: unset; }
                
                h3 img{ float: unset; max-width:100%; margin: 5px auto;}
                h4 img{ float: unset; max-width:100%; margin: 5px auto;}
                
                .navigation { display: none; margin: 0px; margin-bottom: 15px; }
                .navigation p{ display: block; }
                
                .hidden_on_mobile        { display: none; }
                .hidden_on_desktop       { display: inherit; }
                .hidden_on_desktop_inline{ display: inline; }
                
                #HeaderQuickButtons{ top:0px; }
                
                .tile_container{ display: block; table-layout: unset; width: 100%; margin: 0px; }
                .tile_item{ display: block; }
                
                .gallery_left           { height: unset; position: unset; width: unset; }
                .gallery_right          { width: unset; left: unset; z-index:10; position: unset; }
                .gallery_main_height    { height: unset; }
                .gallery_multi_height::before    { display: none; }
                .gallery_multi_content  { position: unset;}
                .gallery_image          { max-width: 100%; min-width: unset; min-height: unset; object-fit: unset;}
                .gallery_box_when_bkg   { width:60%; max-width: unset;}
                
                .passage_detail         { width:60%; }
                .login_half             { width:75%; }
                .big_string             { height:100px; }
                
                .no_overflow_mobile     { overflow: unset;}
            }
            
            @media print {
                body{ width:100%; min-width: unset; }
                #Header                 { display: none; }
                .top_panel,
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
            element.style.height = "20px";
            element.style.height = (element.scrollHeight+10)+"px";
        }
        function la_pad(num, n) {
            return (Array(n).join(0) + num).slice(-n);
        }
        </script>
        </head>
        <body>
        <?php
    }
    function PageHeaderBegin(){
        ?>
        <div id='Header'>
        <?php
    }
    function PageHeaderEnd(){
        ?>
            </div>
        <?php
    }
    function MakeTitleButton(){
        ?>
        <div id='WebsiteTitle'>
            <a class='home_button hidden_on_mobile' href="?page=index.md"><?php echo $this->Title;?></a>
            <a class='home_button hidden_on_desktop_inline' id='HomeButton' ><?php echo $this->Title;?>...</a>
        </div>
        <?php
    }
    function MakeMainContentBegin(){
        $layout = $this->GetAdditionalLayout();
        $this->AdditionalLayout = $layout;
        if($layout == 'Gallery' && (!isset($_GET['operation'])||$_GET['operation']=='additional')){
        ?>
            <div class='gallery_left'>
            <div class='main_content gallery_main_height'>
        <?php
        }else{
        ?>
            <div class='main_content'>
        <?php
        }
    }
    function MakeMainContentEnd(){
        $layout = $this->AdditionalLayout;
        if($layout == 'Gallery' && (!isset($_GET['operation'])||$_GET['operation']=='additional')){
        ?>
            </div>
            </div>
        <?php
        }else{
        ?>
            </div>
        <?php
        }
    }
    function MakeSettings(){
        $Title='LAMDWIKI';
        $Footnote='';
        ?>
            <div class='btn' onclick='location.href="?page=<?php echo $this->PagePath;?>"'>退出</div>
            <form method="post" id='settings_form' style='display:none;' action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=settings';?>"></form>
            <h1>设置中心</h1>
            <h2>网站设置</h2>
            <div>
                <input class='string_input no_horizon_margin' type='text' name='settings_website_title' name='settings_website_title' form='settings_form' value='<?php echo $this->Title ?>' />
                网站标题
                <br />
                <input class='string_input no_horizon_margin' type='text' name='settings_website_display_title' name='settings_website_display_title' form='settings_form' value='<?php echo $this->StringTitle ?>' />
                标签显示标题
                <br />
                <input class='string_input no_horizon_margin' type='text' name='settings_footer_notes' name='settings_footer_notes' form='settings_form' value='<?php echo $this->Footnote ?>' />
                页脚附加文字
            </div>
            <h2>管理员设置</h2>
            <div>
                <input class='string_input no_horizon_margin' type='text' name='settings_admin_id' name='settings_admin_id' form='settings_form' />
                重新设置管理账户名(INOP)
                <br />
                <input class='string_input no_horizon_margin' type='text' name='settings_admin_password' name='settings_admin_password' form='settings_form' />
                重新设置管理密码
            </div>
            <input class='btn form_btn' type='submit' value='确定' name="settings_button_confirm" form='settings_form' />
        <?php
    }
    function MakeLoginDiv(){
    ?> 
    
        <div id='LoginPanel' class='top_panel' style='display:none;'>
            
            <?php if ($this->IsLoggedIn()) { ?>
                <a href='?page=<?php echo $this->PagePath;?>&operation=settings'>网站设置</a>
            <?php } ?>
        
            <div class='login_half'>
        
                <?php
                
                if(!$this->IsLoggedIn()){
                    echo '<h3 class = "inline_components">'.'欢迎'.'</h3>';
                    echo '<p class = "inline_components">'.'您尚未登录'.'</p>';
                ?>
            
                <form method = "post" action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath;?>" style='margin-bottom:10px;'>
                    
                    <div class = "inline_components">用户名:</div>
                    
                    <input class='string_input' type="text" id="username" name="username" style='margin-right:0px;'
                    value="<?php if(!empty($user_username)) echo $user_username; ?>" />
                    <br />
                    <div class='inline_components'>密码:</div>
                    <input class='string_input' type="password" id="password" name="password" style='margin-right:0px;margin-bottom:15px;'/>
                    <br />
                    <input class='btn form_btn' style="float:right" type="submit" value="登录" name="button_login"/>
                   
                </form>
                <?php
                }else{
                    echo '<p class = "inline_components">'.$this->UserDisplayName.'</p>';
                    echo '<p class = "inline_components">'.'不是您本人？'.'</p>';
                    ?>
                    <input class='btn form_btn' type="button" name="logout" value="登出" onclick="location.href='<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath;?>&logout=True'" />
                    <?php
                }
                ?>
            </div> 
        </div>
        <?php
        
    }
    function MakeHeaderQuickButtons(){
        $path = $this->InterlinkPath();
        $disp = $this->FolderDisplayAs($path)=='Timeline'?1:0;
        ?>
        <div id='HeaderQuickButtons'>
        <?php

            if(!isset($_SESSION['user_id'])){
            ?>
                <a class='btn' href="?page=<?php echo $path ?><?php echo $disp?('&operation=timeline&folder='.$path):'&operation=tile' ?>">文章列表</a>
                <div id='LoginToggle' class='btn'>欢迎</div>
            <?php
            }else{
                ?>
                <div id='LoginToggle' class='btn'><?php echo $this->UserDisplayName ?></div>
                <a class='btn' href="?page=<?php echo $path ?><?php echo $disp?('&operation=timeline&folder='.$path):'&operation=tile' ?>">文章</a>
                <a href="?page=<?php echo $this->PagePath?>&operation=list">管理</a> 
                <a href="?page=<?php echo $this->PagePath?>&operation=new">写文</a>
                <?php
            }
            ?>
                   
        </div>
        <?php
        
    }
    function MakeNavigationBegin(){
        ?>
        <div class="navigation" id='Navigation'>
            <a class='home_button hidden_on_desktop' href="?page=index.md"><b>前往首页</b></a>
        <?php
    }
    function MakeNavigationEnd(){
        ?>
        </div>
        <?php
    }
    function MakePassageEditButtons(){
        ?>
        <div style='float:right;z-index:1;'>
            <a href="?page=<?php echo $this->PagePath ?>&operation=additional">附加</a>
            <a href="?page=<?php echo $this->PagePath;?>&operation=edit"><b>编辑</b></a>
        </div>
        <?php
    }
    function MakeEditorHeader(){
        ?>
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
                <div id='EditorToggleQoute' class='btn'><b>“</b></div>
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
                        <input class='string_input title_string' type="text" id="EditorFileName" name="editor_file_name" value='<?php echo $this->GetUniqueName('Untitled');?>'/>
                        .md
                        <?php
                    }
                    ?>
                    
                    &nbsp;
                    <input class='btn form_btn' type="submit" value="完成" name="button_new_passage" form='form_passage' />
                </form>
            </div>
            
        </div>
        <?php
    }
    function MakeEditorBody($text){
        ?>
        <div>
            <textarea class='string_input big_string ' form='form_passage' id='data_passage_content' name='data_passage_content'><?php echo $text;?></textarea>
            <div>
                <span id='data_passage_character_count'>字数</span>
            </div>
            <script>
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
                var btn_q = document.getElementById("EditorToggleQoute");
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
                function toggleQoute(content,line_begin){
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
                    toggleQoute(content,line_begin);
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
        ?>
        <div class='top_panel'>
        
            <a href="?page=<?php echo $upper.($additional_mode?'&operation=additional&action=view&for='.$_GET['for']:'&operation=list'.($move_mode?'&moving='.$moving:''));?>" class='btn'><b>上级</b></a>
            
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
                            文件夹对外公开 &nbsp;<a class='btn' id='folder_upload' href='?page=<?php echo $path?>&operation=set_permission_off'>设为不公开</a>
                        <?php }else{ ?>
                            文件夹不公开 &nbsp;<a class='btn' id='folder_upload' href='?page=<?php echo $path?>&operation=set_permission_on'>设为公开</a>
                        <?php }?>
                        <div class='inline_height_spacer'></div>
                        <?php if($display_as=='Timeline'){ ?>
                            文件显示为时间线 &nbsp;<a class='btn' id='folder_upload' href='?page=<?php echo $path?>&operation=set_display_normal'>设为瓷砖</a>
                        <?php }else{ ?>
                            文件显示为瓷砖 &nbsp;<a class='btn' id='folder_upload' href='?page=<?php echo $path?>&operation=set_display_timeline'>设为时间线</a>
                        <?php }?>
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
                    </script>
                <?php }else if(!$additional_mode){ ?>
                    <a class='btn' href='?page=<?php echo $moving ?>&operation=list'>取消</a>
                    <a class='btn' href='?page=<?php echo $path ?>&moving=<?php echo $moving ?>&to=<?php echo $path ?>'>到这里</a>
                <?php }else{ ?>
                    <a class='btn' href='?page=<?php echo $_GET["for"] ?>&operation=additional'>取消</a>
                    <a class='btn' href='?page=<?php echo $path ?>&operation=additional&action=add&for=<?php echo $_GET["for"] ?>&target=<?php echo $path ?>'>选这个</a>
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
                if($arr[$i]['count']===Null) $arr[$i]['count'] = 4;
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
    
    function GetFileNameDateFormat($file,&$year,&$month,&$day){
        if(preg_match("/(\d{4})(\d{2})(\d{2})/",$file,$matches,PREG_OFFSET_CAPTURE)){
            $year =  $matches[1][0];
            $month = $matches[2][0];
            $day =   $matches[3][0];
        }else{
            $year='的某一天';
            $month='';
            $day ='过去';
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
                            <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=0"?>'>列表</a>
                            <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=2"?>'>画廊</a>
                            <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=1"?>'>方块</a>
                            <a href='?page=<?php echo $this->PagePath."&operation=set_additional_style&for=".$this->PagePath."&target=".$path."&style=3"?>'>我说</a>
                            <div class='inline_height_spacer'></div>
                            最近篇目数量：
                            <form method = "post" style='display:inline;' 
                            action="<?php echo $_SERVER['PHP_SELF'].'?page='.$this->PagePath.'&operation=set_additional_count&for='.$this->PagePath.'&target='.$path?>"
                            id="form_additional_count<?php echo $path?>">
                                <input class="string_input no_horizon_margin title_string" style='width:4em;' type="text" value="<?php echo $a['count'] ?>" id="display_count_<?php echo $path?>" name="display_count" form="form_additional_count<?php echo $path?>">
                                <input class="btn form_btn" type="submit" value="设置" name="button_additional_count_confirm" form="form_additional_count<?php echo $path?>" id='additional_count_confirm_<?php echo $path?>'>
                            </form>
                            <div class='inline_height_spacer'></div>
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
            
            if(isset($a['title']) && $a['title']!=''){
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
                                <span style='font-size:24px;'><b><?php echo date("d")?></b></span><br /><?php echo date("Y")?><?php echo date("m") ?>
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
                    $y=''; $m=''; $d='';
                    $show_complete = isset($a['complete'])&&$a['complete']==1;
                    $this->GetFileNameDateFormat($f,$y,$m,$d);
                    $rows = $this->FirstRows($this->ContentOfMarkdownFile($path.'/'.$f),$show_complete?10000:10);
                    $title = $this->TitleOfFile($rows);
                    $background = $this->GetAdditionalContentBackground($path.'/'.$f);
                    $last_interlink = $this->InterlinkPath();
                    $this->SetInterlinkPath($path.'/'.$f);
                    ?>
                    <div>
                    <div class='additional_content additional_content_left hidden_on_mobile'>
                        <div class='plain_block' style='text-align:center'>
                        <span style='font-size:24px;'><b><?php echo $d?></b></span><br /><?php echo $y?><?php echo $m?'/'.$m:'' ?>
                        </div>
                    </div>
                    <div class='additional_content no_overflow_mobile'>
                        <div class='hidden_on_desktop' style='clear:both;text-align:right;position:sticky;top:80px;'>
                            <div class='plain_block small_shadow' style='text-align:center;display:inline-block;background-color:#FFF;'>
                                <div style='float:right'>
                                    &nbsp;<?php echo $m?($y.'/'.$m.'/<b>'.$d.'</b>'):'<b>过去</b>的某一天'?>
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
                                <?php echo $this->HTMLFromMarkdown($rows);?>
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
    }
    function MakeFileList($moving,$viewing){
        $move_mode = $moving==''?$viewing:True;
        $path = $this->InterlinkPath();
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
                <div>
                     <div class = 'narrow_content' style='float:left;margin-right:15px'>
                        <a href="?page=<?php echo $path.'/'.$f.($viewing?'&for='.$_GET['for'].'&operation=additional&action=view':'&operation=list'.($move_mode?'&moving='.$moving:''));?>" class='btn'><b>进入</b></a>
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
                        <a class='btn' id='folder_option_btn_<?php echo $f;?>' href='?page=<?php echo $path ?>&operation=additional&action=add&for=<?php echo $_GET['for'] ?>&target=<?php echo $path.'/'.$f ?>'>选这个</a>
                     <?php }else{ ?>
                        <a class='btn' id='folder_option_btn_<?php echo $f;?>' href='?page=<?php echo $path ?>&moving=<?php echo $moving ?>&to=<?php echo $path.'/'.$f ?>'>到这里</a>
                     <?php } ?>
                     </div>
                     <div class = 'narrow_content' style='overflow:auto;'>
                        <b style='background-color:#FFF;'><?php echo $f?></b>
                     </div>
                </div>
                <div style='clear:both;text-align:right'>
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
                <div>
                     <div class = 'narrow_content' style='overflow:hidden;'>
                     
                        <div style='float:right;text-align:right;margin-left:5px;' id='passage_filename_<?php echo $f;?>'>
                            <p style='display:inline;'><?php echo $f?></p>
                            <a class='btn' id='passage_show_detail_<?php echo $f;?>'>简介</a>
                        </div>
                        
                        <div class='passage_detail' id='passage_detail_<?php echo $f;?>' style='display:none;'>
                            <a class='btn' id='passage_operation_close_<?php echo $f;?>' style='display:none;'>取消操作</a>
                            <a class='btn' href="?page=<?php echo $path.'/'.$f;?>">阅读全文</a>
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
                            <a class='btn' href="?page=<?php echo $path.'/'.$f;?>" ><?php echo $this->HTMLFromMarkdown($title);?></a>
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
                <div>
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
                                echo '图像';
                            }else if ($ext=='php' || $ext=='html'){
                                echo '网页';
                            }else echo'文件';
                            ?>
                        </div>
                     </div>
                </div>
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
        <div class='tile_container'>
        <?php
        $column_count=-1;
        if ($upper!=$path){
            $column_count++;
            ?>
            
            <div class = 'tile_content tile_item' style='overflow:auto;'>
            ■ ■ ■ ■ ■
            <a href="?page=<?php echo $upper.'&operation=tile';?>" class='btn block preview_btn'><h2>上级</h2><br />...</a>
            
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
                    <a href="?page=<?php echo $path.'/'.$f.'&operation=tile';?>" class='btn block preview_btn'><h1><?php echo $f;?></h1><br />进入文件夹</a>
                    
                    </div>
                <?php
            }
            if (isset($this->FileNameList[0])) foreach ($this->FileNameList as $f){
                if ($f=='LAUsers.md' || $f=='la_config.md') continue;
                $column_count++;
                if ($column_count>3){
                    $column_count=0;
                    ?>
                        <div style='display: table-row;'></div>
                    <?php
                }
                $rows = $this->FirstRows($this->ContentOfMarkdownFile($this->InterlinkPath().'/'.$f),20);
                ?>
                    <div class = 'tile_content tile_item' style='overflow:auto;'>
                         □
                         <div onclick='location.href="?page=<?php echo $path.'/'.$f;?>"' class='btn block preview_btn' style='font-size:12px; text-align:left;'><?php echo $this->HTMLFromMarkdown($rows);?></div>
                    </div>
                <?php
            }

        ?>
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
            var music = document.getElementById("<?php echo $this->AudioList[0]['id'] ?>");
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
                time.innerHTML=(Math.floor(music.currentTime/60))+':'+la_pad((Math.round(music.currentTime)%60),2);
                progress.style.width=100*(music.currentTime/music.duration)+'%';
                buffer.style.width = 100*(music.buffered.end(0)/music.duration)+'%';
            }
            music.oncanplay = function(){
                duration.innerHTML = (Math.floor(music.duration/60))+':'+la_pad((Math.round(music.duration)%60),2);
            }
            
            <?php } ?>
        </script>
        <?php
    }
    function MakeFooter(){
        $this->GetPrevNextPassage($this->PagePath);
        
        ?>
        
        <div style='text-align:right;'>
            <div class='footer'>
                <a class='btn' href="javascript:scrollTo(0,0);">返回顶部</a>
                <br />
                <div class = 'inline_block_height_spacer'></div>
                <p style='font-size:12px;margin:0px;'><?php echo $this->Footnote ?></p>
                <p style='font-size:12px;margin:0px;'>使用 <a href='https://github.com/Nicksbest/lamdwiki' style='padding:1px;border:none;'>LAMDWIKI</a> 创建</p>
            </div>
        </div>
        
        <script>
            var lg_toggle  = document.getElementById("LoginToggle");
            var lg_panel = document.getElementById("LoginPanel");

            lg_toggle.addEventListener("click", function() {
                var shown = lg_panel.style.display == 'block';
                lg_panel.style.display = shown ? "none" : "block";
                //lg_toggle.innerHTML = shown? "收起":"登录";
            });
            
            var hb = document.getElementById("HomeButton");
            var nav = document.getElementById("Navigation");
            hb.addEventListener("click", function() {
                var disp = nav.style.display;
                nav.style.cssText = disp==''?'display:block':'';
            });
            
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
        </body>
        <?php
    }
    
}

?>
