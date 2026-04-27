<?php
namespace Concrete\Package\PageListMap;

defined('C5_EXECUTE') or die('Access Denied.');

use BlockType;
use Package;

class Controller extends Package
{
    protected $pkgHandle = 'page_list_map';
    protected $appVersionRequired = '9.0.0';
    protected $pkgVersion = '1.0';
    protected $pkgName = 'Page List Map';
    protected $pkgDescription = 'Interactive map page list view.';

    public function install()
    {
        $pkg = parent::install();
        BlockType::installBlockType('page_list_map', $pkg);
    }
}