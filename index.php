<?php


$url = dirname(__FILE__);

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



$LAManagement = new LAManagement();
$LAManagement->SetPagePath($la_page_path);

$ConfRead = fopen('la_config.md','r');
$Config = $LAManagement->ParseMarkdownConfig(fread($ConfRead,filesize('la_config.md')));

$WebsiteName = $LAManagement->GetLineValueByNames($Config,"Website","Title");

//test---------------
//$ConfSave = fopen('MarkdownConf.md','w');
//$LAManagement->AddBlock($Config,"Fuck Me");
//$Block = $LAManagement->GetBlock($Config,"Users");
//$LAManagement->RemoveBlockByName($Config,"Users");
//$LAManagement->RemoveBlock($Config,$Block);
//$LAManagement->SetBlockName($Config,$Block,'FUCK');
//$Line = $LAManagement->AddGeneralLine($Config,$Block,'Fuck You!!!',123);
//$Line = $LAManagement->AddGeneralLine($Config,$Block,'Fuck You One More Time!!!',123456);
//$LAManagement->RemoveGeneralLine($Config,$Block,$Line);
//$Arg = $LAManagement->AddArgument($Config,$Block,$Line,"Want to","Suck My Dick?");
//$Arg = $LAManagement->AddArgument($Config,$Block,$Line,"Yes","You Do.");
//$Arg = $LAManagement->AddArgument($Config,$Block,$Line,"Say that to me","One more time");
//$LAManagement->RemoveArgument($Config,$Block,$Line,$Arg);
//$LAManagement->WriteMarkdownConfig($Config, $ConfSave);
//fclose($ConfSave);
fclose($ConfRead);

//if($LAManagement->CheckArgumentByNames($Config,'Users','admin','password','abc')) echo 'OH YEAH BAE! <br />';
//if(!$LAManagement->CheckArgumentByNames($Config,'Users','admin','password','ab2c')) echo 'FUCK YOU BITCH! <br />';
//if($LAManagement->CheckArgumentByNames($Config,'Users','admin','my data','123123')) echo 'OH YEAH BAE! <br />';
//if(!$LAManagement->CheckArgumentByNames($Config,'Users','admin','ab2c','ab2c')) echo 'FUCK YOU BITCH! <br />';

$LAManagement->SetInterlinkPath($la_page_path);

echo $LAManagement->DoLogin();

if(!$LAManagement->IsLoggedIn()){
    if (isset($la_operation)
        && $la_operation!='tile'
        && $la_operation!='timeline'){
        $LAManagement->LimitAccess(1);
    }
}



echo $LAManagement->DoNewPassage();
echo $LAManagement->DoNewFolder();
echo $LAManagement->DoDeleteFolder();
echo $LAManagement->DoRenameFolder();
echo $LAManagement->DoDeleteFile();
echo $LAManagement->DoRenameFile();
echo $LAManagement->DoChangePermission();
echo $LAManagement->DoMoveFile();
echo $LAManagement->DoAdditionalConfig();


echo $LAManagement->MakeHTMLHead($WebsiteName);

echo $LAManagement->PageHeaderBegin();

echo $LAManagement->MakeTitleButton($WebsiteName);
echo $LAManagement->MakeNavigationBegin();
$LAManagement->SetInterlinkPath('index.md');
echo $LAManagement->HTMLFromMarkdownFile('navigation.md');
echo $LAManagement->MakeNavigationEnd();

$LAManagement->SetInterlinkPath($la_page_path);
echo $LAManagement->MakeHeaderQuickButtons();

echo $LAManagement->MakeLoginDiv();

if($la_operation == 'new'){
    echo $LAManagement->PageHeaderEnd();
    echo $LAManagement->MakeEditorHeader();
}if($la_operation == 'edit'){
    echo $LAManagement->PageHeaderEnd();
    $LAManagement->SetEditMode(True);
    echo $LAManagement->MakeEditorHeader();
}else if($la_operation == 'list' || ($la_operation == 'additional' && isset($_GET['action']) && $_GET['action']=='view') ){
    echo $LAManagement->MakeFolderHeader();
    echo $LAManagement->PageHeaderEnd();
}else if($la_operation == 'additional'){
    echo $LAManagement->MakeAdditionalHeader();
    echo $LAManagement->PageHeaderEnd();
}else{
    echo $LAManagement->PageHeaderEnd();
}




$LAManagement->SetInterlinkPath($la_page_path);


if($la_operation == 'new'){

    echo $LAManagement->MakeMainContentBegin();
    echo $LAManagement->MakeEditorBody('Some text here.');
    echo $LAManagement->MakeMainContentEnd();
    
}else if($la_operation == 'edit'){

    echo $LAManagement->MakeMainContentBegin();
    echo $LAManagement->MakeEditorBody($LAManagement->ContentOfMarkdownFile($la_page_path));
    echo $LAManagement->MakeMainContentEnd();
    
}else if($la_operation == 'list'){

    echo $LAManagement->MakeFileList(isset($_GET["moving"])?$_GET["moving"]:"",False);

}else if($la_operation == 'timeline' && isset($_GET['folder'])){
    
    $pos=0;
    if(isset($_GET['position'])) $pos = $_GET['position'];
    echo $LAManagement->MakeAdditionalContent($_GET['folder'],$pos);

}else if($la_operation == 'additional' && isset($_GET['action']) && $_GET['action']=='view'){

    echo $LAManagement->MakeFileList($_GET["page"],True);

}else if($la_operation == 'tile'){

    echo $LAManagement->MakePassageTiles();
    
}else if($la_operation == 'settings'){

    echo $LAManagement->MakeMainContentBegin();
    echo $LAManagement->MakeSettings();
    echo $LAManagement->MakeMainContentEnd();

}else{

    echo $LAManagement->MakeMainContentBegin();
    
    if($LAManagement->IsLoggedIn())
        echo $LAManagement->MakePassageEditButtons();
    
    $LAManagement->ConfirmMainPassage();
    
    echo $LAManagement->HTMLFromMarkdownFile($la_page_path);
    echo $LAManagement->MakeMainContentEnd();
    
    echo $LAManagement->MakeAdditionalContent(Null,Null);
}

echo $LAManagement->MakeFooter();

echo $LAManagement->MakeAudioPlayer();

?>
