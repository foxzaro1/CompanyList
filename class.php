<?php
use Bitrix\Main\Localization\Loc,
Bitrix\Main\SystemException,
Bitrix\Main\Loader,
Bitrix\Main\Type\Date,
Bitrix\Main\Page\Asset,
Bitrix\Main\Diag\Debug, 
Bitrix\Main\Data\Cache;

if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

class CTreeSections extends CBitrixComponent
{
    protected $users    = [];
    protected $errors   = [];
    protected $sections = [];
    protected $cache_id = "";
    protected $default_iblock_id = 8;

    public function onPrepareComponentParams($arParams)
    {
        if(!isset($arParams["CACHE_TIME"]))
            $arParams["CACHE_TIME"] = 3600;

        $arParams["IBLOCK_ID"] = intval($arParams["IBLOCK_ID"]);

        return $arParams;
    }
    public function initAssets()
    {
        Asset::getInstance()->addJs('https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.1/jquery.min.js'); 
        Asset::getInstance()->addCss('https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/themes/default/style.min.css');
        Asset::getInstance()->addCss('https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css'); 
        Asset::getInstance()->addJs('https://cdnjs.cloudflare.com/ajax/libs/jstree/3.2.1/jstree.min.js'); 
    }

    public function executeComponent()
    {
        try
        {
            $this->checkModules();
            $this->initCache();
            $this->initAssets();
            $this->getResult();
            $this->includeComponentTemplate();

        }
        catch (SystemException $e)
        {
            Debug::dumpToFile(date("Y-m-d H:i:s")." ".$e->getMessage(),"","logs/CTreeSections_error.log");
        }
    }

    protected function checkModules()
    {
        if (!Loader::includeModule('iblock'))
            throw new SystemException(Loc::getMessage('CPS_MODULE_NOT_INSTALLED', array('#NAME#' => 'iblock')));
    }

    protected function prepareDateUser(&$user) {

        if (empty($user["FULL_NAME"])){
            $user["FULL_NAME"] = $user['LAST_NAME']." ".$user['NAME']." ".$user['SECOND_NAME'];
        }
        if (empty($user["DEPARTAMENT_ID"])){
            $user["DEPARTAMENT_ID"] = $user['UF_DEPARTMENT'][0];
        }
    }
    protected function getUsers($cacheMode = false){
        
        $users = [];
        $order = ['TIMESTAMP_X' => 'DESC'];
        $select = ['ID', 'NAME','SECOND_NAME','LAST_NAME','UF_DEPARTMENT','TIMESTAMP_X'];
        $filter = ['!UF_DEPARTMENT' => false,'ACTIVE' => 'Y'];
        $limit = false;
        if($cacheMode){
            $limit = 1; 
        }
        $dbItems = \Bitrix\Main\UserTable::getList([
            'order' => $order,
            'select' => $select,
            'filter' => $filter,
            'limit'  => $limit,
            'data_doubling' => false,
        ]);
        $arUsers = $dbItems->fetchAll();
        foreach ($arUsers as $key => $val) {
            $this->prepareDateUser($val);
            $users[$val['DEPARTAMENT_ID']][$val['ID']] = [
                "NAME" => $val['FULL_NAME'],
                "ID" => $val['ID'],
            ];
            if($cacheMode){
                $users[$val['DEPARTAMENT_ID']][$val['ID']]['TIMESTAMP'] = $val['TIMESTAMP_X'];
            }
        }
        if($cacheMode){
            return array_shift(array_shift($users));
        }
        else{
            return $users;
        }
    }

    protected function getSections($cacheMode = false){

        $iblock_id = ($this->arParams['IBLOCK_ID']) ? $this->arParams['IBLOCK_ID'] : $this->default_iblock_id;
        $sections = [];
        $order = [];
        $filter = ['IBLOCK_ID' => $iblock_id, 'ACTIVE'=>'Y'];
        $limit = false;
        if($cacheMode){
            $order = ['TIMESTAMP_X'=>'DESC'];
            $limit   = 1;
        }
        $rsSection = \Bitrix\Iblock\SectionTable::getList([
            'order'  => $order,
            'filter' => $filter,
            'limit'  => $limit, 
        ]);
        $arSection = $rsSection->fetchAll();
        foreach ($arSection as $key => $section) {
                $sections[$section['ID']] = [
                    'IBLOCK_SECTION_ID' => $section['IBLOCK_SECTION_ID'],
                    'ITEMS'             => $this->users[$section['ID']],
                    'NAME_TREE'         => $section['NAME'],
                    'TIMESTAMP'         => $section['TIMESTAMP_X'],
                ];
        }
        if($cacheMode){
            return array_shift($sections);
        }
        else{
            return $sections;
        }
    }
    protected function getResult(){
        if ($this->errors)
            throw new SystemException(current($this->errors));

        $obCache  = new CPHPCache;
        if ($obCache->InitCache($arParams['CACHE_TIME'], $this->cache_id, '/')) {
            $vars     = $obCache->GetVars();
            $arResult = $vars['arResult'];
            $this->arResult = $arResult;
        }
        else{
        $arParams = $this->arParams;

        $this->users = $this->getUsers(false);
        $arResult['USERS'] = $this->users;
        $arResult['DATA'] = $this->CreateTree($this->getSections(false));
        $this->arResult = $arResult;
        $obCache->EndDataCache(['arResult' => $arResult]);
        }


    }
    private function initCache(){
        $userInfo    = $this->getUsers(true);
        $sectionInfo = $this->getSections(true);
        $this->cache_id = ((new DateTime($userInfo['TIMESTAMP']))->format('U')).$userInfo['ID']."_".((new DateTime($sectionInfo['TIMESTAMP']))->format('U')).$sectionInfo['ID'];
    }
    private function CreateTree($trees){
        $tree = array();
        $this->AddSection($trees,$tree,null);
        return $tree;
    }
    
    private function AddSection(&$trees,&$tree,$parent){
        foreach ($trees as $key=>$value)
        {
            if($value['IBLOCK_SECTION_ID']==$parent)
            {
                $tree[$key]=$trees[$key];
                $tree[$key]['SECTIONS']=array();
                $this->AddSection($trees,$tree[$key]['SECTIONS'],$key);
            }
            if(empty($tree['SECTIONS'])) unset ($tree['SECTIONS']);
        }
        unset($trees[$parent]);

        return ;
    }
}