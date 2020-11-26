<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();?>
<?
// recursive method for set and draw sections and users 
function setCompanyTree($tree){
    
    if ($tree['SECTIONS']) {
        foreach ($tree['SECTIONS'] as $key=>$section) {
            $sections[$key] = setCompanyTree($section);
        }

        return ['sections' => $sections,'currentName'=>$tree['NAME_TREE'],'currentItems'=>$tree['ITEMS']];
    } 
    else {return ['currentName'=>$tree['NAME_TREE'],'currentItems'=>$tree['ITEMS']];}
}
function drawCompanyTrees($tree){

    if($tree['sections']){
        $piece = "<li>".$tree['currentName'].'<ul>';
      foreach ($tree['sections'] as $subTree){
         $piece.= drawCompanyTrees($subTree);
      }
         $piece .= "</ul></li>";
    }
    else{$piece = "<li>".$tree["currentName"].drawCompanyElements($tree)."</li>";}

    return $piece;
}
function drawCompanyElements($tree){

    if($tree['currentItems']){
        $piece = '<ul>';
        foreach ($tree['currentItems'] as $item){
            $piece .= '<li data-jstree=\'{"icon":"//lh3.googleusercontent.com/-8WDHsF3OX4U/AAAAAAAAAAI/AAAAAAAAAAA/6KhNctiOmxU/photo.jpg?sz=32"}\' style="color:blue;"><a target="_blank" href="/company/personal/user/'.$item['ID'].'/">'.$item['NAME'].'</a></li>';
        }
        $piece .= '</ul>';
    
        return $piece;
    }
    return "";
}
?>

<div id="trees">
   <ul>
       <?
       foreach ($arResult['DATA'] as $key => $tree) {
            echo drawCompanyTrees(setCompanyTree($tree));
       }
       ?>
    </ul>
 </div>