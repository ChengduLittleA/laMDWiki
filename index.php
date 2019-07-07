<?php


$url = dirname(__FILE__);

chdir(dirname(__FILE__));

include 'lawebmanagement.php';

?>
<script language="javascript"> 
function setCookie(cname, cvalue, exdays) {
    var d = new Date();
    d.setTime(d.getTime() + (exdays*24*60*60*1000));
    var expires = "expires="+d.toUTCString();
    document.cookie = cname + "=" + cvalue + "; " + expires;
}
function delCookie(name){
        setCookie(name, "", -1);  
} 
function getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for(var i=0; i<ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1);
        if (c.indexOf(name) != -1) return c.substring(name.length, c.length);
    }
    return "";
}

    query=location.href.split("#!"); 
    if(query[1]){ 
        document.cookie='mdwikipath='+query[1]; 
        if (query[1] != "<?php echo isset($_COOKIE['mdwikipath'])?$_COOKIE['mdwikipath']:""; ?>") { 
            window.location.reload(); 
        } 
    }else{
        if(getCookie('mdwikipath')!=""){
            delCookie('mdwikipath');
            window.location.reload(); 
        }
    }
</script>
<?php

$la_page_path = null;
if (isset($_GET["page"])) $la_page_path = $_GET["page"];
else{
    if ($la_page_path==null){
        if (isset($_COOKIE['mdwikipath'])){
            $la_page_path=$_COOKIE['mdwikipath'];
            unset($_COOKIE['mdwikipath']);
        }
        else $la_page_path = 'index.md';
    }
}
if($la_page_path == '') $la_page_path = 'index.md';



$la_operation = null;
if (isset($_GET["operation"])) $la_operation = $_GET["operation"];



$LA = new LAManagement();
$page_success = $LA->SetPagePath($la_page_path);
$LA->SetInterlinkPath($la_page_path);

//test---------------
//$ConfSave = fopen('MarkdownConf.md','w');
//$LA->AddBlock($Config,"Fuck Me");
//$Block = $LA->GetBlock($Config,"Users");
//$LA->RemoveBlockByName($Config,"Users");
//$LA->RemoveBlock($Config,$Block);
//$LA->SetBlockName($Config,$Block,'FUCK');
//$Line = $LA->AddGeneralLine($Config,$Block,'Fuck You!!!',123);
//$Line = $LA->AddGeneralLine($Config,$Block,'Fuck You One More Time!!!',123456);
//$LA->RemoveGeneralLine($Config,$Block,$Line);
//$Arg = $LA->AddArgument($Config,$Block,$Line,"Want to","Suck My Dick?");
//$Arg = $LA->AddArgument($Config,$Block,$Line,"Yes","You Do.");
//$Arg = $LA->AddArgument($Config,$Block,$Line,"Say that to me","One more time");
//$LA->RemoveArgument($Config,$Block,$Line,$Arg);
//$LA->WriteMarkdownConfig($Config, $ConfSave);
//fclose($ConfSave);
//if($LA->CheckArgumentByNames($Config,'Users','admin','password','abc')) echo 'OH YEAH BAE! <br />';
//if(!$LA->CheckArgumentByNames($Config,'Users','admin','password','ab2c')) echo 'FUCK YOU BITCH! <br />';
//if($LA->CheckArgumentByNames($Config,'Users','admin','my data','123123')) echo 'OH YEAH BAE! <br />';
//if(!$LA->CheckArgumentByNames($Config,'Users','admin','ab2c','ab2c')) echo 'FUCK YOU BITCH! <br />';

echo $LA->DoSetTranslation();

echo $LA->DoLogin();

if($LA->IsLoggedIn()){
    echo $LA->DoNewPassage();
    echo $LA->DoNewSmallQuote();
    echo $LA->DoEditTask();
    echo $LA->DoNewFolder();
    echo $LA->DoDeleteFolder();
    echo $LA->DoRenameFolder();
    echo $LA->DoDeleteFile();
    echo $LA->DoRenameFile();
    echo $LA->DoChangePermission();
    echo $LA->DoChangeFolderDisplay();
    echo $LA->DoChangeFolderLayout();
    echo $LA->DoMoveFile();
    echo $LA->DoAdditionalConfig();
    echo $LA->DoTaskManagerConfig();
    echo $LA->DoApplySettings();
}

echo $LA->MakeHTMLHead();

if(!$page_success){
    $LA->LimitAccess(0);
}

if(!$LA->IsLoggedIn()){
    if (isset($la_operation)
        && $la_operation!='tile'
        && $la_operation!='timeline'){
        $LA->LimitAccess(1);
    }
}

echo $LA->SwitchToTargetLanguageIfPossible();

if(isset($_GET['small_quote_only'])){
    echo $LA->MakeCenterContainerBegin();
    echo $LA->MakeSmallQuotePanel($_GET['small_quote_only'],null,$LA->GetSmallQuoteName());
    echo $LA->MakeCenterContainerEnd();
    exit;
}else if(isset($_GET['small_quote'])&&isset($_GET['quote_folder'])){
    echo $LA->MakeCenterContainerBegin();
    echo $LA->MakeSmallQuotePanel($_GET['quote_folder'],$_GET['small_quote'],$LA->GetSmallQuoteName());
    echo $LA->MakeCenterContainerEnd();
    exit;
}


echo $LA->TryExtractTaskManager();

echo $LA->PageHeaderBegin();

if($LA->IsTaskManager()){
    echo $LA->WideHeaderBegin();
}

echo $LA->ProcessLinksToStatic(
     $LA->MakeTitleButton());        

if(!$LA->IsTaskManager()){
    echo $LA->MakeNavigationBegin();
    $LA->SetInterlinkPath('index.md');

    echo $LA->ProcessLinksToStatic(
         $LA->ProcessHTMLLanguageForLinks(
         $LA->HTMLFromMarkdownFile($LA->ChooseLanguage('navigation.md'))));
         
    echo $LA->MakeNavigationEnd();
}else{
    echo $LA->MakeTaskMasterHeader();
}

$LA->SetInterlinkPath($la_page_path);

echo $LA->MakeHeaderQuickButtons();
echo $LA->ProcessLinksToStatic(
     $LA->MakeLoginDiv());
     
if($LA->IsTaskManager()){
    echo $LA->TaskNavigationBegin();
    
    $LA->SetInterlinkPath('index.md');

    echo $LA->ProcessLinksToStatic(
         $LA->ProcessHTMLLanguageForLinks(
         $LA->HTMLFromMarkdownFile($LA->ChooseLanguage('navigation.md'))));
         
    echo $LA->TaskNavigationEnd();
    
    echo $LA->WideHeaderEnd();
}


if($la_operation == 'new'){
    echo $LA->PageHeaderEnd();
    echo $LA->MakeEditorHeader();
}if($la_operation == 'edit'){
    echo $LA->PageHeaderEnd();
    $LA->SetEditMode(True);
    echo $LA->MakeEditorHeader();
}else if($la_operation == 'list' || (($la_operation == 'additional' || $la_operation=='task') && isset($_GET['action']) && $_GET['action']=='view')){
    if($LA->IsTaskManager()){
        
    }
    echo $LA->MakeFolderHeader();
    echo $LA->PageHeaderEnd();
}else if($la_operation == 'additional'){
    echo $LA->MakeAdditionalHeader();
    echo $LA->PageHeaderEnd();
}else if($LA->IsTaskManager()){
    //echo $LA->WideHeaderEnd();
    echo $LA->PageHeaderEnd();
}else{
    echo $LA->PageHeaderEnd();
}


$LA->SetInterlinkPath($la_page_path);


if($la_operation == 'new'){

    echo $LA->MakeMainContentBegin();
    echo $LA->MakeEditorBody('Some text here.');
    echo $LA->MakeMainContentEnd();
    
}else if($la_operation == 'edit'){

    echo $LA->MakeMainContentBegin();
    echo $LA->MakeEditorBody($LA->ContentOfMarkdownFile($la_page_path));
    echo $LA->MakeMainContentEnd();
    
}else if($la_operation == 'list'){

    echo $LA->MakeFileList(isset($_GET["moving"])?$_GET["moving"]:"",False);

}else if($la_operation == 'timeline' && isset($_GET['folder'])){
    
    $pos=0;
    if(isset($_GET['position'])) $pos = $_GET['position'];
    echo $LA->MakeAdditionalContent($_GET['folder'],$pos);

}else if(($la_operation == 'additional' || $la_operation == 'task') && isset($_GET['action']) && $_GET['action']=='view'){

    echo $LA->MakeFileList($_GET["page"],True);

}else if($la_operation == 'tile'){

    echo $LA->MakePassageTiles();
    
}else if($la_operation == 'settings'){

    echo $LA->MakeMainContentBegin();
    echo $LA->MakeSettings();
    echo $LA->MakeMainContentEnd();

}else if($LA->IsTaskManager()){
    echo $LA->MakeTaskList();
}else{
 
    $LA->ExtractPassageConfigFromFile($la_page_path);
    
    $Content3D = $LA->Make3DContent();
    $Content2D = $LA->Make2DContent();
    
    $LA->HandleInsertsBeforePassage($Content2D,$Content3D);

    echo $LA->MakeMainContentBegin();
    
    if($LA->IsLoggedIn())
        echo $LA->MakePassageEditButtons();
    
    $LA->ConfirmMainPassage();
    
    echo $LA->ProcessLinksToStatic(
         $LA->ProcessHREFForPrint(
         $LA->InsertSideNotes(
         $LA->RemoveBlankAfterInserts(
         $LA->Insert3DContent(
         $LA->Insert2DContent(
         $LA->ProcessHTMLLanguageForLinks(
         $LA->HTMLFromMarkdownFile($LA->ActuallPath()))))))));
    
    echo $LA->MakeHREFListForPrint();
         
    echo $LA->MakeMainContentEnd();
    
    $LA->HandleInsertsAfterPassage($Content2D,$Content3D);
    
    echo $LA->MakeAdditionalContent(Null,Null);
}

echo $LA->MakeFooter();

echo $LA->MakeAudioPlayer();

echo $LA->MakeTaskEditor();

if($LA->IsTaskManager() && !$la_operation){
    $LA->MakeTaskManagerFooter();
}

echo $LA->MakeModalBlocker();

?>
